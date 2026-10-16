<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an uploaded image (multipart file or base64 data URI) is rejected.
 */
class UploadException extends RuntimeException
{
    public static function unexpectedField(): self
    {
        return new self('Unexpected field');
    }

    public static function invalidType(): self
    {
        return new self('Invalid file type. Only jpg, png, gif allowed.');
    }

    public static function invalidImageData(): self
    {
        return new self('Invalid image. Only jpg, png, gif or webp images are allowed.');
    }
}
