<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\Js;
use App\Support\Like;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ServiceController extends Controller
{
    use MapsRequestFields;

    private const FIELDS = [
        'serviceName' => ['service_name', ['trim' => true]],
        'serviceImage' => ['service_image'],
        'serviceDescription' => ['service_description'],
        'buttonUrl' => ['button_url'],
    ];

    private const UPLOAD_FOLDER = 'services';

    /**
     * GET /api/services (active services only)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagination = Pagination::lenient($request);
            $search = $request->query('search', '');

            $query = Service::query()->notInactive();

            if (Js::truthy($search)) {
                $query->where('service_name', 'like', Like::contains((string) $search));
            }

            $total = $query->count();
            $services = $pagination->apply($query->orderBy('created_at')->orderBy('id'))->get();

            return response()->json([
                'success' => true,
                'data' => ServiceResource::collection($services),
                'pagination' => [
                    'total' => $total,
                    'page' => $pagination->page,
                    'limit' => $pagination->limit,
                    'totalPages' => $pagination->pages($total),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching services', $exception);
        }
    }

    /**
     * POST /api/services (JSON, or multipart with an optional "serviceImage" image)
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $attributes = $this->stringAttributes($request, self::FIELDS);

            $service = Service::query()->create([
                ...$attributes,
                // Optional: the website falls back to its default banner and booking link
                'service_image' => $this->uploadedImagePath($request) ?? $attributes['service_image'] ?? '',
                'button_url' => $attributes['button_url'] ?? '',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Service created successfully',
                'data' => new ServiceResource($service),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error creating service', $exception);
        }
    }

    /**
     * GET /api/services/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $service = Service::query()->find($id);

            if ($service === null || $service->status === 'inactive') {
                return response()->json(['success' => false, 'message' => 'Service not found'], 404);
            }

            return response()->json(['success' => true, 'data' => new ServiceResource($service)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching service', $exception);
        }
    }

    /**
     * PUT /api/services/{id} (JSON, or multipart with an optional new "serviceImage" image)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $attributes = $this->stringAttributes($request, self::FIELDS + ['status' => ['status']], onlyPresent: true);
            $image = $this->uploadedImagePath($request);

            // An uploaded file replaces the image; without one the current image is kept
            if ($image !== null) {
                $attributes['service_image'] = $image;
            }

            $service = Service::query()->find($id);

            if ($service === null) {
                return response()->json(['success' => false, 'message' => 'Service not found'], 404);
            }

            $service->applyUpdate($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Service updated successfully',
                'data' => new ServiceResource($service),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating service', $exception);
        }
    }

    /**
     * DELETE /api/services/{id} (soft delete: status becomes "inactive")
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $service = Service::query()->find($id);

            if ($service === null) {
                return response()->json(['success' => false, 'message' => 'Service not found'], 404);
            }

            $service->applyUpdate(['status' => 'inactive']);

            return response()->json(['success' => true, 'message' => 'Service soft deleted (status set to inactive)']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting service', $exception);
        }
    }

    /**
     * Public path of the "serviceImage" file uploaded with the request, if any.
     */
    private function uploadedImagePath(Request $request): ?string
    {
        $filename = $request->attributes->get('uploaded_file');

        return $filename === null ? null : '/uploads/'.self::UPLOAD_FOLDER.'/'.$filename;
    }
}
