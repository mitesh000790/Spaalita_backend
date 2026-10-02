<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class ApplyProductCouponRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Product ID and coupon code are required';

    public function rules(): array
    {
        return [
            'productId' => [new Truthy],
            'couponCode' => [new Truthy],
        ];
    }
}
