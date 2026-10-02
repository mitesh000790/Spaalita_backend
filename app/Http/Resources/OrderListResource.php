<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Order as shown in the order list: includes the full customer record,
 * the referenced products, and a `productName` on every item.
 *
 * @mixin Order
 */
class OrderListResource extends OrderResource
{
    /**
     * @var Collection<int, Product>|null
     */
    private ?Collection $products = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = parent::toArray($request);

        return array_merge($order, [
            'user' => new UserResource($this->resource->user),
            'shippingAddress' => $order['shippingAddress'] ?? '',
            'products' => CatalogItemResource::collection($this->products()),
        ]);
    }

    protected function item(OrderItem $item): array
    {
        $product = $this->products()->firstWhere('id', $item->product_id);

        return parent::item($item) + [
            'productName' => $product !== null ? $product->product_name : $item->name,
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return $this->products ??= $this->resource->products()->load('categories');
    }
}
