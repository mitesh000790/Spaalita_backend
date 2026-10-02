<?php

namespace App\Http\Requests\BookingPolicy;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class StoreBookingPolicyRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'All fields are required';

    public function rules(): array
    {
        return [
            'title' => [new Truthy],
            'description' => [new Truthy],
        ];
    }
}
