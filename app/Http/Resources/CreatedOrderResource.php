<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

/**
 * Order returned right after checkout: the customer is expanded to
 * {_id, firstName, lastName, email} and each item's product to
 * {_id, productName}, plus a `productName` on every item.
 *
 * @mixin Order
 */
class CreatedOrderResource extends OrderResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return array_merge(parent::toArray($request), [
            'user' => $user === null ? null : [
                '_id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
            ],
            'shippingAddress' => self::addressOrEmptyObject($this->resource->shippingAddress),
            'billingAddress' => self::addressOrEmptyObject($this->resource->billingAddress),
        ]);
    }

    protected function item(OrderItem $item): array
    {
        $product = $item->product;
        $fields = parent::item($item);

        if ($item->product_id !== null) {
            $fields['productId'] = $product === null ? null : ['_id' => $product->id, 'productName' => $product->product_name];
        }

        return $fields + ['productName' => $product?->product_name ?: $item->name];
    }
}
