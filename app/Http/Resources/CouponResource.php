<?php

namespace App\Http\Resources;

use App\Models\Coupon;
use App\Models\CouponProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Coupon record. With $withProductNames the `products` / `excludeProducts`
 * lists contain {_id, productName} for each existing product or gift card;
 * otherwise they contain the raw ids.
 *
 * @mixin Coupon
 */
class CouponResource extends ApiResource
{
    public function __construct(Coupon $resource, private readonly bool $withProductNames = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $included = $this->resource->includedProducts;
        $excluded = $this->resource->excludedProducts;

        return [
            '_id' => $this->id,
            'code' => $this->code,
            'discountType' => $this->discount_type,
            'discountValue' => self::number($this->discount_value),
            'allowFreeShipping' => $this->allow_free_shipping,
            'expiryDate' => self::isoDate($this->expiry_date),
            'minOrderAmount' => self::number($this->min_order_amount),
            'maxOrderAmount' => self::number($this->max_order_amount),
            'products' => $this->productList($included),
            'productsModel' => $included->pluck('productable_type')->all(),
            'excludeProducts' => $this->productList($excluded),
            'excludeProductsModel' => $excluded->pluck('productable_type')->all(),
            'categories' => $this->categories ?? [],
            'excludeCategories' => $this->exclude_categories ?? [],
            'usageLimitPerCoupon' => $this->usage_limit_per_coupon,
            'usageLimitPerUser' => $this->usage_limit_per_user,
            'usageCount' => $this->usage_count,
            'status' => $this->status,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }

    /**
     * @param  Collection<int, CouponProduct>  $links
     * @return list<mixed>
     */
    private function productList(Collection $links): array
    {
        if (! $this->withProductNames) {
            return $links->pluck('productable_id')->all();
        }

        return $links
            ->filter(fn (CouponProduct $link) => $link->productable !== null)
            ->map(fn (CouponProduct $link) => [
                '_id' => $link->productable->id,
                'productName' => $link->productable->product_name,
            ])
            ->values()
            ->all();
    }
}
