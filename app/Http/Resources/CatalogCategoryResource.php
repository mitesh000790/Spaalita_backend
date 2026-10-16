<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A category in the catalogue sidebar: `count` is the number of listed items
 * in it or any of its sub-categories (what the list shows when filtered by
 * it), and `children` holds its sub-categories that contain items.
 *
 * @mixin Category
 */
class CatalogCategoryResource extends ApiResource
{
    /**
     * @param  array<string, int>  $counts  category id => listed item count
     */
    public function __construct(Category $resource, private readonly array $counts)
    {
        parent::__construct($resource);
    }

    /**
     * The categories that contain items, in their stored order.
     *
     * @param  Collection<int, Category>  $categories
     * @param  array<string, int>  $counts
     * @return list<self>
     */
    public static function nonEmpty(Collection $categories, array $counts): array
    {
        return $categories
            ->filter(fn (Category $category) => ($counts[$category->id] ?? 0) > 0)
            ->map(fn (Category $category) => new self($category, $counts))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'count' => $this->counts[$this->id] ?? 0,
            'children' => self::nonEmpty($this->resource->children, $this->counts),
        ];
    }
}
