<?php

namespace App\Exceptions;

use App\Models\Concerns\HasSchemaValidation;
use RuntimeException;

/**
 * Raised when a model fails its schema rules before being written
 * (the equivalent of a Mongoose ValidationError in the original backend).
 *
 * @see HasSchemaValidation
 */
class SchemaValidationException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors  path => message
     */
    public static function make(string $model, array $errors): self
    {
        $details = collect($errors)
            ->map(fn (string $message, string $path) => "{$path}: {$message}")
            ->implode(', ');

        return new self("{$model} validation failed: {$details}");
    }
}
