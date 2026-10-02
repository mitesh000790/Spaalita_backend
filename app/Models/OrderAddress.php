<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shipping or billing address captured with an order.
 */
class OrderAddress extends Model
{
    public const TYPE_SHIPPING = 'shipping';

    public const TYPE_BILLING = 'billing';

    /**
     * API field name => column.
     */
    public const FIELDS = [
        'name' => 'name',
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'address' => 'address',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip',
        'country' => 'country',
        'email' => 'email',
        'phone' => 'phone',
    ];

    public $timestamps = false;

    protected $fillable = [
        'type',
        'name',
        'first_name',
        'last_name',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'email',
        'phone',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string|null>
     */
    public function toApiArray(): array
    {
        $fields = [];

        foreach (self::FIELDS as $key => $column) {
            $fields[$key] = $this->{$column};
        }

        return $fields;
    }
}
