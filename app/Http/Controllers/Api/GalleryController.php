<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Gallery endpoints report errors as {"message": "..."} (no "success" key),
 * as in the original API.
 */
class GalleryController extends Controller
{
    private const UPLOAD_FOLDER = 'gallery';

    /**
     * GET /api/gallery?date=dd-mm-yyyy
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Gallery::query()->newestFirst();
            $date = $request->query('date');

            if ($date !== null && $date !== '') {
                $day = $this->parseDay((string) $date);
                $query->whereBetween('created_at', [$day, $day->endOfDay()]);
            }

            return response()->json(['success' => true, 'data' => GalleryResource::collection($query->get())]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Server error fetching gallery'], 500);
        }
    }

    /**
     * POST /api/gallery/create (multipart: up to 10 "images")
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $filenames = $request->attributes->get('uploaded_files');

            if ($filenames === null) {
                throw new InvalidArgumentException('Expected a multipart/form-data upload');
            }

            // One batch shares a single timestamp and keeps upload order.
            $uploadedAt = now();

            $images = DB::transaction(fn () => collect($filenames)->map(
                fn (string $filename) => Gallery::query()->create([
                    'url' => $this->publicUrl($filename),
                    'created_at' => $uploadedAt,
                    'updated_at' => $uploadedAt,
                ])
            ));

            return response()->json([
                'success' => true,
                'message' => 'Images uploaded successfully',
                'data' => GalleryResource::collection($images),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Failed to upload images'], 500);
        }
    }

    /**
     * PUT /api/gallery/{id} (multipart: one "image")
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $filename = $request->attributes->get('uploaded_file');

            if ($filename === null) {
                return response()->json(['message' => 'No image provided for update.'], 400);
            }

            $image = Gallery::query()->find($id);

            if ($image === null) {
                return response()->json(['message' => 'Image not found'], 404);
            }

            $image->applyUpdate(['url' => $this->publicUrl($filename)]);

            return response()->json([
                'success' => true,
                'message' => 'Image updated successfully',
                'data' => new GalleryResource($image),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Server error updating image'], 500);
        }
    }

    /**
     * DELETE /api/gallery/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $image = Gallery::query()->find($id);

            if ($image === null) {
                return response()->json(['message' => 'Image not found'], 404);
            }

            $image->delete();

            return response()->json(['success' => true, 'message' => 'Image deleted successfully']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Server error deleting image'], 500);
        }
    }

    private function publicUrl(string $filename): string
    {
        return '/uploads/'.self::UPLOAD_FOLDER.'/'.$filename;
    }

    /**
     * Start of the given "dd-mm-yyyy" day in UTC.
     */
    private function parseDay(string $date): CarbonImmutable
    {
        [$day, $month, $year] = array_pad(explode('-', $date), 3, '');

        if (! ctype_digit($day) || ! ctype_digit($month) || ! ctype_digit($year)
            || ! checkdate((int) $month, (int) $day, (int) $year)) {
            throw new InvalidArgumentException("Invalid date: {$date}");
        }

        return CarbonImmutable::create((int) $year, (int) $month, (int) $day, 0, 0, 0, 'UTC');
    }
}
