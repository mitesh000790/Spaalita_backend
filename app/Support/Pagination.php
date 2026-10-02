<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Page / limit query parameters, parsed with JavaScript parseInt() rules.
 *
 * Two flavours mirror the two query styles of the original API:
 * - lenient(): limit=0 means "no limit" (MongoDB find().limit(0)).
 * - strict():  the limit must be positive (MongoDB aggregation $limit).
 * Invalid values throw, which the controllers report as a 500 error.
 */
final class Pagination
{
    private function __construct(
        public readonly int $page,
        public readonly int $limit,
    ) {}

    public static function lenient(Request $request, int $defaultLimit = 10): self
    {
        $pagination = self::parse($request, $defaultLimit);

        if ($pagination->offset() < 0) {
            throw new InvalidArgumentException('skip value must be non-negative');
        }

        return $pagination;
    }

    public static function strict(Request $request, int $defaultLimit = 10): self
    {
        $pagination = self::parse($request, $defaultLimit);

        if ($pagination->limit <= 0) {
            throw new InvalidArgumentException('the limit must be positive');
        }

        if ($pagination->offset() < 0) {
            throw new InvalidArgumentException('skip value must be non-negative');
        }

        return $pagination;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * Apply offset/limit to a query (a zero limit returns every row).
     */
    public function apply(Builder $query): Builder
    {
        // With no limit the offset is always 0 ((page - 1) * 0).
        if ($this->limit !== 0) {
            $query->skip($this->offset())->take(abs($this->limit));
        }

        return $query;
    }

    /**
     * Total number of pages (null when there is no limit).
     */
    public function pages(int $total): ?int
    {
        return $this->limit === 0 ? null : (int) ceil($total / $this->limit);
    }

    private static function parse(Request $request, int $defaultLimit): self
    {
        $page = Js::parseInt($request->query('page', 1));
        $limit = Js::parseInt($request->query('limit', $defaultLimit));

        if ($page === null || $limit === null) {
            throw new InvalidArgumentException('page and limit must be numbers');
        }

        return new self($page, $limit);
    }
}
