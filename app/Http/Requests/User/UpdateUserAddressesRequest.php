<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiFormRequest;
use App\Support\Js;
use Illuminate\Validation\Validator;

class UpdateUserAddressesRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'No address data provided';

    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! Js::truthy($this->input('billing')) && ! Js::truthy($this->input('shipping'))) {
                    $validator->errors()->add('billing', $this->failureMessage);
                }
            },
        ];
    }
}
