<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class StoreServiceRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'All fields are required';

    public function rules(): array
    {
        return [
            'serviceName' => [new Truthy],
            'serviceDescription' => [new Truthy],
        ];
    }
}
