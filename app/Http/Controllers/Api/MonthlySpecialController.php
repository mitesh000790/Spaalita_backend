<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UploadException;
use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlySpecial\StoreMonthlySpecialRequest;
use App\Http\Resources\MonthlySpecialResource;
use App\Models\MonthlySpecial;
use App\Services\ImageUploadService;
use App\Support\Js;
use App\Support\Like;
use App\Support\Pagination;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Only one monthly special may exist per month.
 *
 * The image is sent as a base64 data URI (admin panel), as an uploaded
 * "image" file (multipart) or as a URL. Data URIs and files are saved under
 * /uploads/monthlySpecial/ and the record keeps that path, so large images
 * never have to fit in a single database packet.
 */
class MonthlySpecialController extends Controller
{
    use MapsRequestFields;

    private const FIELDS = [
        'month' => ['month', ['trim' => true]],
        'image' => ['image'],
    ];

    public function __construct(private readonly ImageUploadService $uploads) {}

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
     * POST /api/monthly-special  {month, image} (JSON, or multipart with an "image" file)
     */
    public function store(StoreMonthlySpecialRequest $request): JsonResponse
    {
        $newImage = null;

        try {
            $attributes = $this->stringAttributes($request, self::FIELDS);

            if (MonthlySpecial::query()->where('month', $attributes['month'])->exists()) {
                return $this->duplicateMonth($request->input('month'));
            }

            $newImage = $this->saveNewImage($request, $attributes['image']);

            $special = MonthlySpecial::query()->create(['image' => $newImage ?? $attributes['image']] + $attributes);

            return response()->json([
                'success' => true,
                'message' => 'Monthly special created successfully',
                'data' => new MonthlySpecialResource($special),
            ], 201);
        } catch (UploadException $exception) {
            return $this->invalidImage($exception);
        } catch (Throwable $exception) {
            $this->uploads->delete($newImage, MonthlySpecial::UPLOAD_FOLDER);

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
     * PUT /api/monthly-special/{id}  (JSON, or multipart with an optional new "image" file)
     *
     * A new image replaces the stored file; the current path sent back unchanged keeps it.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $newImage = null;

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

            $newImage = $this->saveNewImage($request, $attributes['image'] ?? null);

            if ($newImage !== null) {
                $attributes['image'] = $newImage;
            }

            $previousImage = $special->image;
            $special->applyUpdate($attributes);

            if ($special->image !== $previousImage) {
                $this->uploads->delete($previousImage, MonthlySpecial::UPLOAD_FOLDER);
            }

            return response()->json([
                'success' => true,
                'message' => 'Monthly special updated successfully',
                'data' => new MonthlySpecialResource($special),
            ]);
        } catch (UploadException $exception) {
            return $this->invalidImage($exception);
        } catch (Throwable $exception) {
            $this->uploads->delete($newImage, MonthlySpecial::UPLOAD_FOLDER);

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
            $this->uploads->delete($special->image, MonthlySpecial::UPLOAD_FOLDER);

            return response()->json(['success' => true, 'message' => 'Monthly special deleted successfully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting monthly special', $exception);
        }
    }

    /**
     * Save the image sent with this request as a file: the uploaded "image"
     * file, or a base64 data URI. Returns its public path, or null when the
     * request carries neither (a URL or existing path is stored as sent).
     *
     * @throws UploadException when the data URI is not a valid image
     */
    private function saveNewImage(Request $request, ?string $image): ?string
    {
        $filename = $request->attributes->get('uploaded_file');

        if ($filename === null && ImageUploadService::isDataUri($image)) {
            $filename = $this->uploads->storeDataUri($image, MonthlySpecial::UPLOAD_FOLDER);
        }

        return $filename === null ? null : '/uploads/'.MonthlySpecial::UPLOAD_FOLDER.'/'.$filename;
    }

    private function invalidImage(UploadException $exception): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $exception->getMessage()], 400);
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
