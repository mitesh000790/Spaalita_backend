<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use Illuminate\Http\Request;

/**
 * Order record with the customer and products referenced by id.
 *
 * @mixin Order
 */
class OrderResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'user' => $this->user_id,
            'guestInfo' => $this->resource->guestInfo(),
            'items' => $this->resource->items->map(fn (OrderItem $item) => $this->item($item))->all(),
            'totalAmount' => self::number($this->total_amount),
            'status' => $this->status,
            'paymentIntentId' => $this->payment_intent_id,
            'paymentMethod' => $this->when($this->payment_method !== null, $this->payment_method),
            'paymentStatus' => $this->payment_status,
            'shippingAddress' => $this->resource->shippingAddress?->toApiArray(),
            'billingAddress' => $this->resource->billingAddress?->toApiArray(),
            'coupon' => $this->resource->couponDetails(),
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }

    /**
     * An order line; fields the client did not send are omitted.
     *
     * @return array<string, mixed>
     */
    protected function item(OrderItem $item): array
    {
        $fields = array_filter([
            'productId' => $item->product_id,
            'name' => $item->name,
            'price' => self::number($item->price),
            'qty' => $item->qty,
            'image' => $item->image,
        ], fn ($value) => $value !== null);

        return $fields + ['_id' => $item->id];
    }

    /**
     * @return array<string, string|null>|object
     */
    protected static function addressOrEmptyObject(?OrderAddress $address): array|object
    {
        return $address?->toApiArray() ?? (object) [];
    }
}
