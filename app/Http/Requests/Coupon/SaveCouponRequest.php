<?php

namespace App\Http\Requests\Coupon;

use App\Http\Requests\ApiFormRequest;
use App\Rules\NotNull;
use App\Rules\Truthy;

/**
 * Used for both creating and updating a coupon.
 */
class SaveCouponRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Coupon code, discount type, amount, and expiry date are required';

    public function rules(): array
    {
        return [
            'code' => [new Truthy],
            'discountType' => [new Truthy],
            'discountValue' => [new NotNull],
            'expiryDate' => [new Truthy],
        ];
    }
}
