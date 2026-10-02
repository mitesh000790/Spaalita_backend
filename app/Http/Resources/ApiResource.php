<?php

namespace App\Http\Resources;

use App\Support\Js;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base resource: records are exposed with an `_id` key, camelCase field
 * names and ISO-8601 UTC dates with milliseconds ("2025-01-31T10:15:00.000Z"),
 * the JSON format the frontend already consumes.
 */
abstract class ApiResource extends JsonResource
{
    protected static function isoDate(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    protected static function number(int|float|string|null $value): int|float|null
    {
        return Js::number($value);
    }
}
