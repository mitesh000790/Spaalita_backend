<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;
use Illuminate\Contracts\Validation\Validator;

class StoreUserRequest extends ApiFormRequest
{
    /**
     * Report every missing field, not just the first.
     *
     * @var bool
     */
    protected $stopOnFirstFailure = false;

    protected ?string $failureMessage = 'All fields are required';

    public function rules(): array
    {
        return array_fill_keys([
            'userName',
            'firstName',
            'lastName',
            'email',
            'phone',
            'country',
            'city',
            'address',
            'postalCode',
            'password',
        ], [new Truthy]);
    }

    protected function failurePayload(Validator $validator): array
    {
        return parent::failurePayload($validator) + [
            'missingFields' => array_keys($validator->failed()),
        ];
    }
}
