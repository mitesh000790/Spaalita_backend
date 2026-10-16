<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends BaseModel
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    public const NEWSLETTER_SUBSCRIBE = 'subscribe';

    public const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';

    /**
     * Choices offered on My Account > Account details
     * ("orderUpdates": order emails only, no newsletter).
     */
    public const NEWSLETTER_OPTIONS = [self::NEWSLETTER_SUBSCRIBE, self::NEWSLETTER_UNSUBSCRIBE, 'orderUpdates'];

    protected $fillable = [
        'profile_picture',
        'user_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'country',
        'city',
        'address',
        'postal_code',
        'password',
        'role',
        'status',
        'newsletter_option',
        'reset_password_token',
        'reset_password_expire',
    ];

    protected $hidden = [
        'password',
        'reset_password_token',
    ];

    protected $attributes = [
        'profile_picture' => '',
        'role' => 'user',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'reset_password_expire' => 'datetime',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('type', UserAddress::TYPE_BILLING);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('type', UserAddress::TYPE_SHIPPING);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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
            'user_name' => ['path' => 'userName', 'required' => true],
            'first_name' => ['path' => 'firstName', 'required' => true],
            'last_name' => ['path' => 'lastName', 'required' => true],
            'email' => ['path' => 'email', 'required' => true],
            'phone' => ['path' => 'phone', 'required' => true],
            'country' => ['path' => 'country', 'required' => true],
            'city' => ['path' => 'city', 'required' => true],
            'address' => ['path' => 'address', 'required' => true],
            'postal_code' => ['path' => 'postalCode', 'required' => true],
            'password' => ['path' => 'password', 'required' => true],
            'status' => ['path' => 'status', 'enum' => self::STATUSES],
        ];
    }
}
