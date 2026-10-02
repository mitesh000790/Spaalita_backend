<?php

namespace App\Support;

/**
 * Generates and validates MongoDB-compatible ObjectId strings.
 *
 * Primary keys are 24-character hex ObjectIds (4-byte timestamp, 5-byte
 * process-unique random value, 3-byte counter) so that every `_id` the API
 * returns keeps the exact format the frontend received from the original
 * MongoDB backend, and existing MongoDB documents can be imported unchanged.
 */
final class ObjectId
{
    private static ?string $processUnique = null;

    private static ?int $counter = null;

    public static function generate(): string
    {
        self::$processUnique ??= random_bytes(5);
        self::$counter ??= random_int(0, 0xFFFFFF);

        self::$counter = (self::$counter + 1) & 0xFFFFFF;

        $timestamp = pack('N', time());
        $counter = substr(pack('N', self::$counter), 1, 3);

        return bin2hex($timestamp.self::$processUnique.$counter);
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-fA-F]{24}$/', $value) === 1;
    }
}
