<?php

namespace App\Services;

use App\Exceptions\UploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded images under public/uploads/{folder} using the original
 * naming scheme ("{milliseconds}-{random}{ext}").
 */
class ImageUploadService
{
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * Extensions a stored file may keep. Anything else (e.g. ".php") is
     * replaced by the extension of its declared image type, so an upload can
     * never become an executable script on the web server.
     */
    private const SAFE_EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'jfif', 'pjpeg', 'pjp', 'png', 'gif'];

    private const MIME_EXTENSIONS = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif'];

    /**
     * Leading bytes of the image types accepted as base64 data URIs => extension
     * (WebP is detected separately: "RIFF" + size + "WEBP").
     */
    private const IMAGE_SIGNATURES = [
        "\xFF\xD8\xFF" => '.jpg',
        "\x89PNG\r\n\x1A\n" => '.png',
        'GIF87a' => '.gif',
        'GIF89a' => '.gif',
    ];

    /**
     * @throws UploadException when the file is not a jpg, png or gif image
     */
    public function assertValid(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new UploadException($file->getErrorMessage());
        }

        if (! in_array($file->getClientMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw UploadException::invalidType();
        }
    }

    /**
     * Store the file and return its generated file name.
     */
    public function store(UploadedFile $file, string $folder): string
    {
        $filename = $this->newFilename($this->extensionFor($file));

        Storage::disk('uploads')->putFileAs($folder, $file, $filename);

        return $filename;
    }

    /**
     * Whether the value is a base64 image data URI ("data:image/png;base64,...").
     */
    public static function isDataUri(mixed $value): bool
    {
        return is_string($value) && preg_match('#^data:image/[\w.+-]+;base64,#i', $value) === 1;
    }

    /**
     * Decode a base64 image data URI, store it and return its generated file
     * name. The file type is read from the decoded bytes, not the declared type.
     *
     * @throws UploadException when the data is not a jpg, png, gif or webp image
     */
    public function storeDataUri(string $dataUri, string $folder): string
    {
        $base64 = preg_replace('/\s+/', '', substr($dataUri, (int) strpos($dataUri, ',') + 1));
        $bytes = base64_decode($base64, true);
        $extension = is_string($bytes) ? self::extensionForBytes($bytes) : null;

        if ($extension === null) {
            throw UploadException::invalidImageData();
        }

        $filename = $this->newFilename($extension);

        Storage::disk('uploads')->put("{$folder}/{$filename}", $bytes);

        return $filename;
    }

    /**
     * Remove a file this service stored in the folder (a "/uploads/{folder}/..."
     * path). Other values, such as external URLs, are left alone.
     */
    public function delete(?string $path, string $folder): void
    {
        if (is_string($path) && str_starts_with($path, "/uploads/{$folder}/")) {
            Storage::disk('uploads')->delete($folder.'/'.basename($path));
        }
    }

    private function newFilename(string $extension): string
    {
        return sprintf('%d-%d%s', (int) floor(microtime(true) * 1000), random_int(0, 1_000_000_000), $extension);
    }

    private function extensionFor(UploadedFile $file): string
    {
        $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

        if (in_array(strtolower($extension), self::SAFE_EXTENSIONS, true)) {
            return '.'.$extension;
        }

        return self::MIME_EXTENSIONS[$file->getClientMimeType()] ?? '';
    }

    private static function extensionForBytes(string $bytes): ?string
    {
        foreach (self::IMAGE_SIGNATURES as $signature => $extension) {
            if (str_starts_with($bytes, $signature)) {
                return $extension;
            }
        }

        $isWebp = strlen($bytes) > 12 && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP';

        return $isWebp ? '.webp' : null;
    }
}
