<?php

namespace App\Support;

/**
 * JavaScript value semantics used by the original Node.js controllers.
 *
 * The API contract was defined by checks such as `if (!price)` or
 * `parseInt(page)`, which behave differently from PHP's `empty()` or
 * `(int)` casts (e.g. "0" is truthy in JavaScript but falsy in PHP).
 * Routing those checks through this class keeps responses identical.
 */
final class Js
{
    /**
     * JavaScript truthiness: false, 0, NaN, "" and null/undefined are falsy;
     * everything else (including "0", "false", [] and {}) is truthy.
     */
    public static function truthy(mixed $value): bool
    {
        return match (true) {
            $value === null, $value === false, $value === '' => false,
            is_int($value) => $value !== 0,
            is_float($value) => $value != 0.0 && ! is_nan($value),
            default => true,
        };
    }

    /**
     * JavaScript `parseInt(value)` for base 10. Returns null where JavaScript returns NaN.
     */
    public static function parseInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? (int) $value : null;
        }

        if (! is_string($value) || preg_match('/^\s*([+-]?\d+)/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * JavaScript `Number(value)` for strings, booleans and numbers. Returns null for NaN.
     */
    public static function toNumber(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return is_nan((float) $value) ? null : $value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if ($value === null) {
            return 0;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if (preg_match('/^0[xX][0-9a-fA-F]+$/', $value)) {
            return hexdec($value);
        }

        if (preg_match('/^0[bB][01]+$/', $value)) {
            return bindec($value);
        }

        if (preg_match('/^0[oO][0-7]+$/', $value)) {
            return octdec(substr($value, 2));
        }

        if (preg_match('/^[+-]?Infinity$/', $value)) {
            return str_starts_with($value, '-') ? -INF : INF;
        }

        if (! preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/', $value)) {
            return null;
        }

        $number = (float) $value;

        return floor($number) === $number && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }

    /**
     * Normalise a numeric value so that it is JSON-encoded the way JavaScript
     * would print it (42 instead of 42.0; NaN and Infinity become null).
     */
    public static function number(int|float|string|null $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $value = is_string($value) ? (float) $value : $value;

        if (is_float($value) && ! is_finite($value)) {
            return null;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 1e15) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * JavaScript `String(number)` / template literal formatting of numbers.
     */
    public static function numberToString(int|float $value): string
    {
        $value = self::number($value);

        return is_int($value) ? (string) $value : json_encode($value);
    }

    /**
     * JavaScript `Number.prototype.toFixed(digits)` (round half up on the exact binary value).
     */
    public static function toFixed(float $value, int $digits): string
    {
        $exact = sprintf('%.40F', abs($value));
        [$integer, $fraction] = explode('.', $exact);

        $kept = $integer.substr($fraction, 0, $digits);
        $roundUp = ((int) ($fraction[$digits] ?? '0')) >= 5;

        if ($roundUp) {
            $kept = self::incrementDigits($kept);
        }

        $kept = str_pad($kept, $digits + 1, '0', STR_PAD_LEFT);
        $result = $digits > 0
            ? substr($kept, 0, -$digits).'.'.substr($kept, -$digits)
            : $kept;

        return ($value < 0 && (float) $result !== 0.0 ? '-' : '').$result;
    }

    /**
     * Add one to a non-negative integer held as a digit string.
     */
    private static function incrementDigits(string $digits): string
    {
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            if ($digits[$i] !== '9') {
                $digits[$i] = (string) ((int) $digits[$i] + 1);

                return $digits;
            }

            $digits[$i] = '0';
        }

        return '1'.$digits;
    }
}
