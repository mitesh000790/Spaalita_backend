<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingPolicy\StoreBookingPolicyRequest;
use App\Http\Resources\BookingPolicyResource;
use App\Models\BookingPolicy;
use App\Support\Js;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Only one booking policy can be active: activating one deactivates the others.
 */
class BookingPolicyController extends Controller
{
    use MapsRequestFields;

    private const FIELDS = [
        'title' => ['title', ['trim' => true]],
        'description' => ['description'],
        'buttonUrl' => ['button_url'],
        'status' => ['status'],
    ];

    /**
     * GET /api/booking-policies
     */
    public function index(): JsonResponse
    {
        try {
            $policies = BookingPolicy::query()->orderBy('created_at')->orderBy('id')->get();

            return response()->json(['success' => true, 'data' => BookingPolicyResource::collection($policies)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching booking policies', $exception);
        }
    }

    /**
     * POST /api/booking-policies
     */
    public function store(StoreBookingPolicyRequest $request): JsonResponse
    {
        try {
            $status = $request->input('status');

            if ($status === 'active') {
                $this->deactivateActivePolicies();
            } elseif (! Js::truthy($status)) {
                $status = 'inactive';
            }

            $attributes = $this->stringAttributes($request, self::FIELDS);

            $policy = BookingPolicy::query()->create([
                ...$attributes,
                // Optional: the website falls back to its default booking link
                'button_url' => $attributes['button_url'] ?? '',
                'status' => TypeCaster::string($status, 'status'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking policy created successfully',
                'data' => new BookingPolicyResource($policy),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error creating booking policy', $exception);
        }
    }

    /**
     * GET /api/booking-policies/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $policy = BookingPolicy::query()->find($id);

            if ($policy === null) {
                return response()->json(['success' => false, 'message' => 'Booking policy not found'], 404);
            }

            return response()->json(['success' => true, 'data' => new BookingPolicyResource($policy)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching booking policy', $exception);
        }
    }

    /**
     * PUT /api/booking-policies/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            if ($request->input('status') === 'active') {
                $this->deactivateActivePolicies();
            }

            $attributes = $this->stringAttributes($request, self::FIELDS, onlyPresent: true);
            $policy = BookingPolicy::query()->find($id);

            if ($policy === null) {
                return response()->json(['success' => false, 'message' => 'Booking policy not found'], 404);
            }

            $policy->applyUpdate($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Booking policy updated successfully',
                'data' => new BookingPolicyResource($policy),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating booking policy', $exception);
        }
    }

    /**
     * DELETE /api/booking-policies/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $policy = BookingPolicy::query()->find($id);

            if ($policy === null) {
                return response()->json(['success' => false, 'message' => 'Booking policy not found'], 404);
            }

            $policy->delete();

            return response()->json(['success' => true, 'message' => 'Booking policy deleted successfully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting booking policy', $exception);
        }
    }

    private function deactivateActivePolicies(): void
    {
        BookingPolicy::query()->where('status', 'active')->update(['status' => 'inactive']);
    }
}
