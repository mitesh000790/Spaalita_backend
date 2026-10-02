<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Shared behaviour of products and gift cards, which have identical schemas
 * and endpoints (they were two MongoDB collections with the same shape).
 */
abstract class CatalogItem extends BaseModel
{
    public const STATUSES = ['active', 'inactive'];

    public const TAX_STATUSES = ['taxable', 'none'];

    public const STOCK_STATUSES = ['instock', 'outofstock'];

    protected $fillable = [
        'product_name',
        'sku',
        'price',
        'regular_price',
        'sale_price',
        'qty',
        'product_images',
        'description',
        'short_description',
        'status',
        'slug',
        'tax_status',
        'shipping_required',
        'shipping_taxable',
        'stock_status',
        'related_ids',
    ];

    protected $attributes = [
        'regular_price' => '',
        'sale_price' => '',
        'product_images' => '[]',
        'short_description' => '',
        'status' => 'active',
        'slug' => '',
        'tax_status' => 'none',
        'shipping_required' => true,
        'shipping_taxable' => false,
        'stock_status' => 'instock',
        'related_ids' => '[]',
    ];

    /**
     * Pivot table linking this item type to categories.
     */
    abstract protected function categoryPivotTable(): string;

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'qty' => 'integer',
            'product_images' => 'array',
            'shipping_required' => 'boolean',
            'shipping_taxable' => 'boolean',
            'related_ids' => 'array',
        ];
    }

    public function categories(): BelongsToMany
    {
        $pivot = $this->categoryPivotTable();

        return $this->belongsToMany(Category::class, $pivot)->orderBy("{$pivot}.id");
    }

    /**
     * Replace the item's categories, keeping the given order and ignoring
     * ids that do not belong to an existing category.
     *
     * @param  list<string|null>  $categoryIds
     */
    public function syncCategoriesInOrder(array $categoryIds): void
    {
        $existing = Category::query()->whereIn('id', array_filter($categoryIds))->pluck('id')->all();
        $ordered = array_values(array_unique(array_filter(
            $categoryIds,
            fn ($id) => $id !== null && in_array($id, $existing, true),
        )));

        $this->categories()->detach();
        $this->categories()->attach($ordered);
        $this->unsetRelation('categories');
    }

    /**
     * Records whose status is not "inactive" (null counts as not inactive).
     */
    public function scopeNotInactive(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->where('status', '!=', 'inactive')->orWhereNull('status'));
    }

    protected function schemaRules(): array
    {
        return [
            'product_name' => ['path' => 'productName', 'required' => true],
            'sku' => ['path' => 'sku', 'required' => true],
            'price' => ['path' => 'price', 'required' => true],
            'qty' => ['path' => 'qty', 'required' => true],
            'description' => ['path' => 'description', 'required' => true],
            'status' => ['path' => 'status', 'enum' => self::STATUSES],
            'tax_status' => ['path' => 'tax_status', 'enum' => self::TAX_STATUSES],
            'stock_status' => ['path' => 'stock_status', 'enum' => self::STOCK_STATUSES],
        ];
    }
}
