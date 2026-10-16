<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Catalog\ApplyProductCouponRequest;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Models\Product;
use App\Support\Js;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Throwable;

/**
 * /api/products
 */
class ProductController extends CatalogItemController
{
    /**
     * Fixed promotional codes accepted by POST /api/products/apply-coupon.
     * (Store coupons are handled by /api/coupon/apply.)
     */
    private const PROMO_CODES = [
        'SAVE10' => ['discount' => 10, 'type' => 'percentage'],
        'SAVE20' => ['discount' => 20, 'type' => 'percentage'],
        'FLAT50' => ['discount' => 50, 'type' => 'fixed'],
    ];

    /**
     * POST /api/products
     */
    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        return $this->createItem($request);
    }

    protected function model(): string
    {
        return Product::class;
    }

    protected function messages(): array
    {
        return [
            'created' => 'Product created successfully',
            'createFailed' => 'Error creating product',
            'notFound' => 'Product not found',
            'updated' => 'Product updated successfully',
            'updateFailed' => 'Error updating product',
            'listFailed' => 'Error fetching products',
            'showFailed' => 'Error fetching product details',
            'deleteNotFound' => 'Product not found',
            'deleted' => 'Product soft deleted (status set to inactive)',
            'deleteFailed' => 'Error deleting product',
            'relatedNotFound' => 'Product not found',
            'relatedFailed' => 'Error fetching related products',
        ];
    }

    /**
     * POST /api/products/apply-coupon
     */
    public function applyCoupon(ApplyProductCouponRequest $request): JsonResponse
    {
        try {
            $product = Product::query()->find(TypeCaster::string($request->input('productId'), 'productId'));

            if ($product === null || $product->status === 'inactive') {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $code = $request->input('couponCode');

            if (! is_string($code)) {
                throw new InvalidArgumentException('couponCode.toUpperCase is not a function');
            }

            $coupon = self::PROMO_CODES[mb_strtoupper($code)] ?? null;

            if ($coupon === null) {
                return response()->json(['success' => false, 'message' => 'Invalid coupon code'], 400);
            }

            $price = $product->price;
            $discountedPrice = $coupon['type'] === 'percentage'
                ? $price - ($price * $coupon['discount'] / 100)
                : max(0, $price - $coupon['discount']);

            return response()->json([
                'success' => true,
                'message' => 'Coupon applied successfully',
                'data' => [
                    'product' => $product->product_name,
                    'originalPrice' => Js::number($price),
                    'discount' => $coupon['discount'],
                    'discountType' => $coupon['type'],
                    'discountedPrice' => Js::number((float) Js::toFixed($discountedPrice, 2)),
                    'savings' => Js::number((float) Js::toFixed($price - $discountedPrice, 2)),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error applying coupon', $exception);
        }
    }
}
