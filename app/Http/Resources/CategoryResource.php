<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;

/**
 * A root category with its nested sub-categories.
 *
 * @mixin Category
 */
class CategoryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'oId' => $this->when($this->o_id !== null, $this->o_id),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->when($this->image_src !== null, fn () => CategoryNodeResource::image($this->resource)),
            'count' => $this->count,
            'children' => CategoryNodeResource::collection(
                $this->relationLoaded('children') ? $this->children : $this->resource->children()->get()
            ),
            'parentId' => $this->parent_id,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
