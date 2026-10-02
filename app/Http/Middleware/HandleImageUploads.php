<?php

namespace App\Http\Middleware;

use App\Exceptions\UploadException;
use App\Services\ImageUploadService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts image uploads for one form field before the controller runs.
 *
 * Usage: `upload:{folder},{field}` for a single file, or
 * `upload:{folder},{field},{maxCount}` for several files.
 *
 * Stored file names are exposed as request attributes: "uploaded_file"
 * (single) or "uploaded_files" (multiple). Neither attribute is set for
 * requests that are not multipart/form-data.
 */
class HandleImageUploads
{
    public function __construct(private readonly ImageUploadService $uploads) {}

    public function handle(Request $request, Closure $next, string $folder, string $field, ?string $maxCount = null): Response
    {
        if (! str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/')) {
            return $next($request);
        }

        try {
            $files = $this->filesFor($request, $field, $maxCount === null ? 1 : (int) $maxCount);
            array_walk($files, fn (UploadedFile $file) => $this->uploads->assertValid($file));
        } catch (UploadException $exception) {
            return response()->json(['message' => 'Something went wrong!', 'error' => $exception->getMessage()], 500);
        }

        $stored = array_map(fn (UploadedFile $file) => $this->uploads->store($file, $folder), $files);

        if ($maxCount === null) {
            $request->attributes->set('uploaded_file', $stored[0] ?? null);
        } else {
            $request->attributes->set('uploaded_files', $stored);
        }

        return $next($request);
    }

    /**
     * @return list<UploadedFile>
     *
     * @throws UploadException when files arrive under another field or exceed the limit
     */
    private function filesFor(Request $request, string $field, int $maxCount): array
    {
        $all = $request->allFiles();

        if (array_diff(array_keys($all), [$field]) !== []) {
            throw UploadException::unexpectedField();
        }

        $files = array_values(array_filter(
            Arr::flatten(Arr::wrap($all[$field] ?? [])),
            fn ($file) => $file instanceof UploadedFile,
        ));

        if (count($files) > $maxCount) {
            throw UploadException::unexpectedField();
        }

        return $files;
    }
}
