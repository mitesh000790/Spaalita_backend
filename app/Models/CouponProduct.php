<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A product or gift card listed in a coupon's `products` or
 * `excludeProducts` list. `productable_type` holds "Product" or "GiftCard",
 * the same values the original `productsModel` arrays stored.
 */
class CouponProduct extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'productable_type',
        'productable_id',
        'is_excluded',
    ];

    protected function casts(): array
    {
        return [
            'is_excluded' => 'boolean',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }
}
