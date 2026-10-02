<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class AdminLoginRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Please provide username and password';

    public function rules(): array
    {
        return [
            'username' => [new Truthy],
            'password' => [new Truthy],
        ];
    }
}
