<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class StoreRoleRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Role name is required';

    public function rules(): array
    {
        return [
            'name' => [new Truthy],
        ];
    }
}
