<?php

namespace App\Http\Resources;

use App\Models\CatalogItem;

/**
 * Product / gift card details: `categories` expanded to {_id, name, slug}
 * for every assigned root or first-level category.
 *
 * @mixin CatalogItem
 */
class CatalogItemDetailResource extends CatalogItemResource
{
    /**
     * @param  array<string, array{_id: string, name: string, slug: string}>  $categoryLookup
     */
    public function __construct(CatalogItem $resource, private readonly array $categoryLookup)
    {
        parent::__construct($resource);
    }

    protected function categoriesForResponse(): array
    {
        return $this->resource->categories
            ->pluck('id')
            ->map(fn (string $id) => $this->categoryLookup[$id] ?? null)
            ->filter()
            ->values()
            ->all();
    }
}
