<?php

namespace App\Support;

use App\Exceptions\CastException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Converts raw request values into column values using the same rules the
 * original Mongoose schemas applied (e.g. "true"/"yes"/1 become true for
 * booleans, numeric strings become numbers, arrays reject plain objects).
 *
 * Invalid values raise a CastException, which the controllers report as a
 * 500 error exactly like the Node.js API reported Mongoose CastErrors.
 */
final class TypeCaster
{
    /**
     * @param  array{trim?: bool, lowercase?: bool, uppercase?: bool}  $setters
     */
    public static function string(mixed $value, string $path, array $setters = []): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => Js::numberToString($value),
            is_array($value) && ! array_is_list($value) && is_string($value['_id'] ?? null) => $value['_id'],
            default => throw CastException::make('string', $value, $path),
        };

        if ($setters['trim'] ?? false) {
            $string = trim($string);
        }

        if ($setters['lowercase'] ?? false) {
            $string = mb_strtolower($string);
        }

        if ($setters['uppercase'] ?? false) {
            $string = mb_strtoupper($string);
        }

        return $string;
    }

    public static function number(mixed $value, string $path): int|float|null
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return null;
        }

        $number = is_array($value) ? null : Js::toNumber($value);

        if ($number === null || ! is_finite((float) $number)) {
            throw CastException::make('Number', $value, $path);
        }

        return $number;
    }

    public static function boolean(mixed $value, string $path): ?bool
    {
        return match (true) {
            in_array($value, [true, 'true', 1, '1', 'yes'], true) => true,
            in_array($value, [false, 'false', 0, '0', 'no'], true) => false,
            $value === null => null,
            default => throw CastException::make('boolean', $value, $path),
        };
    }

    /**
     * JavaScript `new Date(value)`: numbers are epoch milliseconds, strings are parsed.
     */
    public static function date(mixed $value, string $path): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_int($value) || is_float($value)) {
                return CarbonImmutable::createFromTimestampMsUTC($value);
            }

            if (is_string($value)) {
                return CarbonImmutable::parse($value, 'UTC')->utc();
            }
        } catch (Throwable) {
            // Fall through to the cast error below.
        }

        throw CastException::make('date', 'Invalid Date', $path);
    }

    public static function objectId(mixed $value, string $path): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && array_key_exists('_id', $value)) {
            $value = $value['_id'];
        }

        if (! ObjectId::isValid($value)) {
            throw CastException::make('ObjectId', $value, $path);
        }

        return strtolower($value);
    }

    /**
     * @return list<string|null>
     */
    public static function stringArray(mixed $value, string $path): array
    {
        return self::castArray($value, $path, 'string', fn ($item, $itemPath) => self::string($item, $itemPath));
    }

    /**
     * @return list<int|float|null>
     */
    public static function numberArray(mixed $value, string $path): array
    {
        return self::castArray($value, $path, 'Number', fn ($item, $itemPath) => self::number($item, $itemPath));
    }

    /**
     * @return list<string|null>
     */
    public static function objectIdArray(mixed $value, string $path): array
    {
        return self::castArray($value, $path, 'ObjectId', fn ($item, $itemPath) => self::objectId($item, $itemPath));
    }

    /**
     * Mongoose array casting: non-array values are wrapped in an array, then every element is cast.
     */
    private static function castArray(mixed $value, string $path, string $kind, callable $caster): array
    {
        if ($value === null) {
            return [];
        }

        $items = is_array($value) && array_is_list($value) ? $value : [$value];

        foreach ($items as $index => $item) {
            try {
                $items[$index] = $caster($item, "{$path}.{$index}");
            } catch (CastException) {
                throw CastException::make("[{$kind}]", $value, "{$path}.{$index}");
            }
        }

        return $items;
    }
}
