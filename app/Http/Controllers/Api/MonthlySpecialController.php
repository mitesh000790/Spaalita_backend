<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlySpecial\StoreMonthlySpecialRequest;
use App\Http\Resources\MonthlySpecialResource;
use App\Models\MonthlySpecial;
use App\Support\Js;
use App\Support\Like;
use App\Support\Pagination;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Only one monthly special may exist per month.
 */
class MonthlySpecialController extends Controller
{
    use MapsRequestFields;

    private const FIELDS = [
        'month' => ['month', ['trim' => true]],
        'image' => ['image'],
    ];

    /**
     * GET /api/monthly-special
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagination = Pagination::lenient($request);
            $search = $request->query('search', '');

            $query = MonthlySpecial::query();

            if (Js::truthy($search)) {
                $query->where('month', 'like', Like::contains((string) $search));
            }

            $specials = $pagination->apply($query->orderBy('created_at')->orderBy('id'))->get();

            return response()->json(['success' => true, 'data' => MonthlySpecialResource::collection($specials)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching monthly special', $exception);
        }
    }

    /**
     * POST /api/monthly-special
     */
    public function store(StoreMonthlySpecialRequest $request): JsonResponse
    {
        try {
            $attributes = $this->stringAttributes($request, self::FIELDS);

            if (MonthlySpecial::query()->where('month', $attributes['month'])->exists()) {
                return $this->duplicateMonth($request->input('month'));
            }

            $special = MonthlySpecial::query()->create($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Monthly special created successfully',
                'data' => new MonthlySpecialResource($special),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error creating monthly special', $exception);
        }
    }

    /**
     * GET /api/monthly-special/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $special = MonthlySpecial::query()->find($id);

            if ($special === null) {
                return response()->json(['success' => false, 'message' => 'Monthly Special not found'], 404);
            }

            return response()->json(['success' => true, 'data' => new MonthlySpecialResource($special)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching monthly special', $exception);
        }
    }

    /**
     * PUT /api/monthly-special/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $month = $request->input('month');

            if (Js::truthy($month)) {
                $duplicate = MonthlySpecial::query()
                    ->where('month', TypeCaster::string($month, 'month', ['trim' => true]))
                    ->whereKeyNot($id)
                    ->exists();

                if ($duplicate) {
                    return $this->duplicateMonth($month);
                }
            }

            $attributes = $this->stringAttributes($request, self::FIELDS, onlyPresent: true);
            $special = MonthlySpecial::query()->find($id);

            if ($special === null) {
                return response()->json(['success' => false, 'message' => 'Monthly special not found'], 404);
            }

            $special->applyUpdate($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Monthly special updated successfully',
                'data' => new MonthlySpecialResource($special),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating monthly special', $exception);
        }
    }

    /**
     * DELETE /api/monthly-special/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $special = MonthlySpecial::query()->find($id);

            if ($special === null) {
                return response()->json(['success' => false, 'message' => 'Monthly special not found'], 404);
            }

            $special->delete();

            return response()->json(['success' => true, 'message' => 'Monthly special deleted successfully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting monthly special', $exception);
        }
    }

    private function duplicateMonth(mixed $month): JsonResponse
    {
        $month = TypeCaster::string($month, 'month');

        return response()->json([
            'success' => false,
            'message' => "A monthly special for '{$month}' already exists. Only one per month is allowed.",
        ], 409);
    }
}
