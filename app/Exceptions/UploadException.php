<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by the upload middleware when a multipart upload is rejected.
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
}
