<?php

namespace App\Support;

use JsonException;

/**
 * Normalises list fields that clients send either as arrays, JSON-encoded
 * strings ('["a","b"]') or single values (multipart forms), following the
 * parsing rules of the original API.
 */
final class ListInput
{
    /**
     * Strings are JSON-decoded when possible; any non-list result is wrapped
     * in a list (so null becomes [null]). Used for `categories`.
     *
     * @return list<mixed>
     */
    public static function parse(mixed $value): array
    {
        $value = self::decode($value);

        return self::isList($value) ? $value : [$value];
    }

    /**
     * Like parse(), but falsy non-list values become an empty list.
     * Used for `existingImages`.
     *
     * @return list<mixed>
     */
    public static function parseOptional(mixed $value): array
    {
        $value = self::decode($value);

        if (self::isList($value)) {
            return $value;
        }

        return Js::truthy($value) ? [$value] : [];
    }

    private static function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }
    }

    private static function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }
}
