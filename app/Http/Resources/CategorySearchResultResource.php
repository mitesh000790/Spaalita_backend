<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;

/**
 * A root category as returned by a category search: only its sub-categories
 * whose names match the search are included.
 *
 * @mixin Category
 */
class CategorySearchResultResource extends ApiResource
{
    /**
     * @param  iterable<Category>  $matchingChildren
     */
    public function __construct(Category $resource, private readonly iterable $matchingChildren)
    {
        parent::__construct($resource);
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
            'description' => $this->description,
            'image' => $this->when($this->image_src !== null, fn () => CategoryNodeResource::image($this->resource)),
            'count' => $this->count,
            'children' => CategoryNodeResource::collection($this->matchingChildren),
        ];
    }
}
