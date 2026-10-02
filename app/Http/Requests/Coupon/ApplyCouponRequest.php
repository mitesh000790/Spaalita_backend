<?php

namespace App\Http\Requests\Coupon;

use App\Http\Requests\ApiFormRequest;
use App\Rules\NonEmptyList;
use App\Rules\Truthy;

class ApplyCouponRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'couponCode' => [new Truthy('Coupon code is required')],
            'cartItems' => [new NonEmptyList('Cart items are required')],
        ];
    }
}
