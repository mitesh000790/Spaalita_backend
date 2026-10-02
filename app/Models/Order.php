<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends BaseModel
{
    public const STATUSES = ['pending', 'paid', 'failed', 'cancelled', 'completed'];

    public const PAYMENT_STATUSES = ['pending', 'succeeded', 'failed'];

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'total_amount',
        'status',
        'payment_intent_id',
        'payment_method',
        'payment_status',
        'coupon_code',
        'coupon_discount_amount',
        'coupon_free_shipping_amount',
    ];

    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'float',
            'coupon_discount_amount' => 'float',
            'coupon_free_shipping_amount' => 'float',
        ];
    }

    /**
     * The customer. Not a foreign key: like the original backend, the id is
     * taken from the JWT and stored as-is, even if it is not a users row.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', OrderAddress::TYPE_SHIPPING);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', OrderAddress::TYPE_BILLING);
    }

    /**
     * Products referenced by the order items, in catalogue order.
     */
    public function products(): Collection
    {
        return Product::query()
            ->whereIn('id', $this->items->pluck('product_id')->filter()->unique()->values())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The `guestInfo` object, or null when no guest details were stored.
     *
     * @return array<string, string>|null
     */
    public function guestInfo(): ?array
    {
        $info = array_filter([
            'name' => $this->guest_name,
            'email' => $this->guest_email,
            'phone' => $this->guest_phone,
        ], fn ($value) => $value !== null);

        return $info === [] ? null : $info;
    }

    /**
     * The `coupon` object, or null when the order was placed without one.
     *
     * @return array<string, mixed>|null
     */
    public function couponDetails(): ?array
    {
        if ($this->coupon_discount_amount === null) {
            return null;
        }

        return array_filter([
            'code' => $this->coupon_code,
            'discountAmount' => $this->coupon_discount_amount,
            'freeShippingAmount' => $this->coupon_free_shipping_amount ?? 0,
        ], fn ($value) => $value !== null);
    }

    protected function schemaRules(): array
    {
        return [
            'total_amount' => ['path' => 'totalAmount', 'required' => true],
            'status' => ['path' => 'status', 'enum' => self::STATUSES],
            'payment_status' => ['path' => 'paymentStatus', 'enum' => self::PAYMENT_STATUSES],
        ];
    }
}
