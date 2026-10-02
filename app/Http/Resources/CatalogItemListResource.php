<?php

namespace App\Http\Resources;

use App\Models\CatalogItem;
use Illuminate\Http\Request;

/**
 * Product / gift card as shown in catalogue listings: a subset of fields,
 * with `categories` expanded to the item's root categories ({_id, name, slug}).
 *
 * @mixin CatalogItem
 */
class CatalogItemListResource extends ApiResource
{
    /**
     * @param  array<string, array{_id: string, name: string, slug: string}>  $categoryLookup
     */
    public function __construct(CatalogItem $resource, private readonly array $categoryLookup)
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
            'productName' => $this->product_name,
            'sku' => $this->sku,
            'price' => self::number($this->price),
            'regular_price' => $this->regular_price,
            'sale_price' => $this->sale_price,
            'qty' => $this->qty,
            'productImages' => $this->product_images ?? [],
            'description' => $this->description,
            'short_description' => $this->short_description,
            'status' => $this->status,
            'categories' => $this->rootCategories(),
            'slug' => $this->slug,
            'stock_status' => $this->stock_status,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }

    /**
     * @return list<array{_id: string, name: string, slug: string}>
     */
    private function rootCategories(): array
    {
        return $this->resource->categories
            ->filter(fn ($category) => $category->parent_id === null)
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->map(fn ($category) => $this->categoryLookup[$category->id] ?? null)
            ->filter()
            ->unique('_id')
            ->values()
            ->all();
    }
}
