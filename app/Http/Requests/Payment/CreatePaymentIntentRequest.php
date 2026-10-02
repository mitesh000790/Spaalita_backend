<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class CreatePaymentIntentRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Amount is required';

    public function rules(): array
    {
        return [
            'amount' => [new Truthy],
        ];
    }
}
