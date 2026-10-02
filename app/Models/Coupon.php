<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends BaseModel
{
    use HasFactory;

    public const DISCOUNT_TYPES = ['percentage', 'fixed'];

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'allow_free_shipping',
        'expiry_date',
        'min_order_amount',
        'max_order_amount',
        'categories',
        'exclude_categories',
        'usage_limit_per_coupon',
        'usage_limit_per_user',
        'usage_count',
        'status',
    ];

    protected $attributes = [
        'allow_free_shipping' => false,
        'min_order_amount' => 0,
        'categories' => '[]',
        'exclude_categories' => '[]',
        'usage_count' => 0,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'allow_free_shipping' => 'boolean',
            'expiry_date' => 'datetime',
            'min_order_amount' => 'float',
            'max_order_amount' => 'float',
            'categories' => 'array',
            'exclude_categories' => 'array',
            'usage_limit_per_coupon' => 'integer',
            'usage_limit_per_user' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    public function productLinks(): HasMany
    {
        return $this->hasMany(CouponProduct::class)->orderBy('id');
    }

    /**
     * Products / gift cards the coupon is restricted to.
     */
    public function includedProducts(): HasMany
    {
        return $this->productLinks()->where('is_excluded', false);
    }

    /**
     * Products / gift cards the coupon never applies to.
     */
    public function excludedProducts(): HasMany
    {
        return $this->productLinks()->where('is_excluded', true);
    }

    /**
     * Replace the included or excluded product list, keeping the given order.
     *
     * @param  list<array{id: string|null, type: string}>  $items
     */
    public function replaceProducts(array $items, bool $excluded): void
    {
        $this->productLinks()->where('is_excluded', $excluded)->delete();

        $this->productLinks()->createMany(array_map(fn (array $item) => [
            'productable_type' => $item['type'],
            'productable_id' => $item['id'],
            'is_excluded' => $excluded,
        ], $items));

        $this->unsetRelation($excluded ? 'excludedProducts' : 'includedProducts');
    }

    protected function schemaRules(): array
    {
        return [
            'code' => ['path' => 'code', 'required' => true],
            'discount_type' => ['path' => 'discountType', 'required' => true, 'enum' => self::DISCOUNT_TYPES],
            'discount_value' => ['path' => 'discountValue', 'required' => true],
            'expiry_date' => ['path' => 'expiryDate', 'required' => true],
            'status' => ['path' => 'status', 'enum' => self::STATUSES],
        ];
    }
}
