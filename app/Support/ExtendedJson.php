<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use JsonException;
use RuntimeException;

/**
 * Reads MongoDB Extended JSON exports (mongoexport, MongoDB Compass) into
 * plain PHP values: {"$oid": ...} becomes the id string, {"$date": ...} a
 * UTC CarbonImmutable, and numeric wrappers become ints / floats.
 */
final class ExtendedJson
{
    /**
     * Documents from a JSON array file or a newline-delimited JSON file.
     *
     * @return list<array<string, mixed>>
     */
    public static function readFile(string $path): array
    {
        $contents = trim((string) file_get_contents($path));

        if ($contents === '') {
            return [];
        }

        try {
            $documents = str_starts_with($contents, '[')
                ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
                : array_map(
                    fn (string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
                    array_values(array_filter(array_map('trim', explode("\n", $contents)))),
                );
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid JSON in {$path}: {$exception->getMessage()}");
        }

        return array_map(fn ($document) => self::decode($document), $documents);
    }

    public static function decode(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (count($value) === 1) {
            $type = array_key_first($value);
            $inner = $value[$type];

            switch ($type) {
                case '$oid':
                    return (string) $inner;
                case '$date':
                    $inner = self::decode($inner);

                    return is_numeric($inner)
                        ? CarbonImmutable::createFromTimestampMsUTC((int) $inner)
                        : CarbonImmutable::parse((string) $inner)->utc();
                case '$numberInt':
                case '$numberLong':
                    return (int) $inner;
                case '$numberDouble':
                case '$numberDecimal':
                    return (float) $inner;
            }
        }

        return array_map(self::decode(...), $value);
    }
}
