<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved billing or shipping address (the `billing` / `shipping`
 * sub-documents of the original User model).
 */
class UserAddress extends Model
{
    public const TYPE_BILLING = 'billing';

    public const TYPE_SHIPPING = 'shipping';

    /**
     * API field name => column, per address type.
     */
    public const FIELDS = [
        self::TYPE_BILLING => [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'address1' => 'address1',
            'address2' => 'address2',
            'country' => 'country',
            'state' => 'state',
            'city' => 'city',
            'postcode' => 'postcode',
            'phone' => 'phone',
            'email' => 'email',
        ],
        self::TYPE_SHIPPING => [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'address1' => 'address1',
            'address2' => 'address2',
            'country' => 'country',
            'state' => 'state',
            'city' => 'city',
            'postcode' => 'postcode',
            'giftCard' => 'gift_card',
        ],
    ];

    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'address1',
        'address2',
        'country',
        'state',
        'city',
        'postcode',
        'phone',
        'email',
        'gift_card',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The address in its API shape; fields that were never set are omitted.
     *
     * @return array<string, string>
     */
    public function toApiArray(): array
    {
        $fields = [];

        foreach (self::FIELDS[$this->type] as $key => $column) {
            if ($this->{$column} !== null) {
                $fields[$key] = $this->{$column};
            }
        }

        return $fields;
    }
}
