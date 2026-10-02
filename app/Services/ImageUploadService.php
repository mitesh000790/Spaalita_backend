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
        $filename = sprintf(
            '%d-%d%s',
            (int) floor(microtime(true) * 1000),
            random_int(0, 1_000_000_000),
            $this->extensionFor($file),
        );

        Storage::disk('uploads')->putFileAs($folder, $file, $filename);

        return $filename;
    }

    private function extensionFor(UploadedFile $file): string
    {
        $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

        if (in_array(strtolower($extension), self::SAFE_EXTENSIONS, true)) {
            return '.'.$extension;
        }

        return self::MIME_EXTENSIONS[$file->getClientMimeType()] ?? '';
    }
}
