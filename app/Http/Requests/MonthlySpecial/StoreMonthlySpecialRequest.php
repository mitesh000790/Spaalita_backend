<?php

namespace App\Http\Requests\MonthlySpecial;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class StoreMonthlySpecialRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'All fields are required';

    public function rules(): array
    {
        return [
            'month' => [new Truthy],
            'image' => [new Truthy],
        ];
    }
}
