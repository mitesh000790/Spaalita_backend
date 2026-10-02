<?php

namespace App\Http\Resources;

use App\Models\CatalogItem;
use Illuminate\Http\Request;

/**
 * Full product / gift card record, with `categories` as a list of ids.
 *
 * @mixin CatalogItem
 */
class CatalogItemResource extends ApiResource
{
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
            'categories' => $this->categoriesForResponse(),
            'status' => $this->status,
            'slug' => $this->slug,
            'tax_status' => $this->tax_status,
            'shipping_required' => $this->shipping_required,
            'shipping_taxable' => $this->shipping_taxable,
            'stock_status' => $this->stock_status,
            'related_ids' => $this->related_ids ?? [],
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function categoriesForResponse(): array
    {
        return $this->resource->categories->pluck('id')->all();
    }
}
