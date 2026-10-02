<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class UserLoginRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Email and password are required';

    public function rules(): array
    {
        return [
            'email' => [new Truthy],
            'password' => [new Truthy],
        ];
    }
}
