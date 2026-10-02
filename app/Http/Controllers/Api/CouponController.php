<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\ApplyCouponRequest;
use App\Http\Requests\Coupon\SaveCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Services\CouponCalculator;
use App\Support\Js;
use App\Support\Like;
use App\Support\Pagination;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class CouponController extends Controller
{
    private const PRODUCT_RELATIONS = ['includedProducts.productable', 'excludedProducts.productable'];

    public function __construct(private readonly CouponCalculator $calculator) {}

    /**
     * GET /api/coupon?page=&limit=&search=
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagination = Pagination::lenient($request, defaultLimit: 20);
            $search = $request->query('search', '');

            $query = Coupon::query();

            if (Js::truthy($search)) {
                $query->where('code', 'like', Like::contains((string) $search));
            }

            $total = $query->count();
            $coupons = $pagination->apply($query->newestFirst())
                ->with(self::PRODUCT_RELATIONS)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Coupons fetched successfully',
                'data' => $coupons->map(fn (Coupon $coupon) => new CouponResource($coupon, withProductNames: true)),
                'pagination' => [
                    'total' => $total,
                    'page' => $pagination->page,
                    'limit' => $pagination->limit,
                    'totalPages' => $pagination->pages($total),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching coupons', $exception);
        }
    }

    /**
     * POST /api/coupon
     */
    public function store(SaveCouponRequest $request): JsonResponse
    {
        try {
            $code = $this->code($request->input('code'));

            if (Coupon::query()->where('code', $code)->exists()) {
                return response()->json(['success' => false, 'message' => 'Coupon code already exists'], 409);
            }

            $coupon = new Coupon(['code' => $code, 'status' => 'active', ...$this->attributes($request)]);
            [$included, $excluded] = $this->productLists($request);

            DB::transaction(function () use ($coupon, $included, $excluded) {
                $coupon->save();
                $coupon->replaceProducts($included, excluded: false);
                $coupon->replaceProducts($excluded, excluded: true);
            });

            return response()->json([
                'success' => true,
                'message' => 'Coupon created successfully',
                'data' => new CouponResource($coupon),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error creating coupon', $exception);
        }
    }

    /**
     * GET /api/coupon/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $coupon = Coupon::query()->with(self::PRODUCT_RELATIONS)->find($id);

            if ($coupon === null) {
                return response()->json(['success' => false, 'message' => 'Coupon not found'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Coupon fetched successfully',
                'data' => new CouponResource($coupon, withProductNames: true),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching coupon', $exception);
        }
    }

    /**
     * PUT /api/coupon/{id}
     */
    public function update(SaveCouponRequest $request, string $id): JsonResponse
    {
        try {
            $coupon = Coupon::query()->find($id);

            if ($coupon === null) {
                return response()->json(['success' => false, 'message' => 'Coupon not found'], 404);
            }

            $code = $this->code($request->input('code'));

            if (Coupon::query()->where('code', $code)->whereKeyNot($coupon->getKey())->exists()) {
                return response()->json(['success' => false, 'message' => 'Coupon code already exists'], 409);
            }

            [$included, $excluded] = $this->productLists($request);
            $coupon->fill(['code' => $code, ...$this->attributes($request)]);

            if (Js::truthy($request->input('status'))) {
                $coupon->status = TypeCaster::string($request->input('status'), 'status');
            }

            $coupon->validateSchema();

            DB::transaction(function () use ($coupon, $included, $excluded) {
                $coupon->save();
                $coupon->replaceProducts($included, excluded: false);
                $coupon->replaceProducts($excluded, excluded: true);
            });

            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully',
                'data' => new CouponResource($coupon),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating coupon', $exception);
        }
    }

    /**
     * DELETE /api/coupon/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $coupon = Coupon::query()->find($id);

            if ($coupon === null) {
                return response()->json(['success' => false, 'message' => 'Coupon not found'], 404);
            }

            $coupon->delete();

            return response()->json(['success' => true, 'message' => 'Coupon deleted successfully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting coupon', $exception);
        }
    }

    /**
     * POST /api/coupon/apply
     *
     * Body: couponCode, cartItems [{productId, price, qty, category}], shippingAmount.
     * Each successful call counts as one use of the coupon.
     */
    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        try {
            $coupon = Coupon::query()
                ->with(self::PRODUCT_RELATIONS)
                ->where('code', $this->code($request->input('couponCode')))
                ->where('status', 'active')
                ->first();

            if ($coupon === null) {
                return response()->json(['success' => false, 'message' => 'Coupon not found or inactive'], 404);
            }

            $result = $this->calculator->calculate(
                $coupon,
                $request->input('cartItems'),
                $request->exists('shippingAmount') ? $request->input('shippingAmount') : 0,
            );

            if ($result->error !== null) {
                return response()->json(['success' => false, 'message' => $result->error], 400);
            }

            $coupon->usage_count = ($coupon->usage_count ?? 0) + 1;
            $coupon->save();

            return response()->json([
                'success' => true,
                'message' => 'Coupon applied successfully',
                'data' => [
                    'couponCode' => $coupon->code,
                    'discountAmount' => Js::number($result->discountAmount),
                    'freeShippingAmount' => is_int($result->freeShippingAmount) || is_float($result->freeShippingAmount)
                        ? Js::number($result->freeShippingAmount)
                        : $result->freeShippingAmount,
                    'totalAfterDiscount' => Js::number($result->totalAfterDiscount),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error applying coupon', $exception);
        }
    }

    /**
     * Coupon codes are stored upper-case.
     */
    private function code(mixed $code): string
    {
        if (! is_string($code)) {
            throw new InvalidArgumentException('code.toUpperCase is not a function');
        }

        return mb_strtoupper($code);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request): array
    {
        $number = function (string $field, bool $required = false) use ($request): int|float|null {
            $value = $request->input($field);

            if (! $required && ! Js::truthy($value)) {
                return null;
            }

            $number = Js::toNumber($value);

            return $number ?? throw new InvalidArgumentException("Cast to Number failed for value \"NaN\" (type number) at path \"{$field}\"");
        };

        return [
            'discount_type' => TypeCaster::string($request->input('discountType'), 'discountType'),
            'discount_value' => $number('discountValue', required: true),
            'allow_free_shipping' => Js::truthy($request->input('allowFreeShipping')),
            'expiry_date' => TypeCaster::date($request->input('expiryDate'), 'expiryDate'),
            'min_order_amount' => $number('minOrderAmount') ?? 0,
            'max_order_amount' => $number('maxOrderAmount'),
            'categories' => TypeCaster::stringArray($request->input('categories', []), 'categories'),
            'exclude_categories' => TypeCaster::stringArray($request->input('excludeCategories', []), 'excludeCategories'),
            'usage_limit_per_coupon' => $number('usageLimitPerCoupon'),
            'usage_limit_per_user' => $number('usageLimitPerUser'),
        ];
    }

    /**
     * The included and excluded product lists, each item tagged as a
     * "Product" or "GiftCard" reference.
     *
     * @return array{0: list<array{id: string|null, type: string}>, 1: list<array{id: string|null, type: string}>}
     */
    private function productLists(Request $request): array
    {
        $giftCardIds = GiftCard::query()->pluck('id')->all();

        $tag = fn (array $ids, string $field) => array_map(fn ($id, $index) => [
            'id' => TypeCaster::objectId($id, "{$field}.{$index}"),
            'type' => in_array($id, $giftCardIds, true) ? 'GiftCard' : 'Product',
        ], $ids, array_keys($ids));

        return [
            $tag($this->list($request, 'products'), 'products'),
            $tag($this->list($request, 'excludeProducts'), 'excludeProducts'),
        ];
    }

    /**
     * An array field that defaults to an empty list when missing.
     *
     * @return list<mixed>
     */
    private function list(Request $request, string $field): array
    {
        if (! $request->exists($field)) {
            return [];
        }

        $value = $request->input($field);

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException("{$field}.map is not a function");
        }

        return $value;
    }
}
