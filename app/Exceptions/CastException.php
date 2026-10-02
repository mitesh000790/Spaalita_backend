<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an incoming value cannot be converted to a field's type
 * (the equivalent of a Mongoose CastError in the original backend).
 */
class CastException extends RuntimeException
{
    public static function make(string $type, mixed $value, string $path): self
    {
        return new self(sprintf(
            'Cast to %s failed for value %s (type %s) at path "%s"',
            $type,
            self::describe($value),
            get_debug_type($value) === 'bool' ? 'boolean' : get_debug_type($value),
            $path,
        ));
    }

    private static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return '"'.$value.'"';
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
