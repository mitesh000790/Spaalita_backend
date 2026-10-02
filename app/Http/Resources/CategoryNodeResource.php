<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;

/**
 * A sub-category inside a parent's `children` array (recursive).
 *
 * @mixin Category
 */
class CategoryNodeResource extends ApiResource
{
    protected ?string $parentId = null;

    /**
     * Add a `parentId` key (used when a sub-category is fetched on its own).
     */
    public function withParentId(string $parentId): static
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            '_id' => $this->id,
            'oId' => $this->when($this->o_id !== null, $this->o_id),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => self::image($this->resource),
            'count' => $this->count,
            'children' => self::collection($this->childrenOf($this->resource)),
        ];

        if ($this->parentId !== null) {
            $data['parentId'] = $this->parentId;
        }

        return $data;
    }

    /**
     * The category image as {id, src}, or null when it has none.
     *
     * @return array{id?: int, src: string}|null
     */
    public static function image(Category $category): ?array
    {
        if ($category->image_src === null) {
            return null;
        }

        return array_filter(
            ['id' => $category->image_id, 'src' => $category->image_src],
            fn ($value) => $value !== null,
        );
    }

    protected function childrenOf(Category $category): iterable
    {
        return $category->relationLoaded('children') ? $category->children : $category->children()->get();
    }
}
