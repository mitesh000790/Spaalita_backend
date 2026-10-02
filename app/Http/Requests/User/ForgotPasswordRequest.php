<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiFormRequest;
use App\Support\Js;
use Illuminate\Validation\Validator;

class ForgotPasswordRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Email or userName is required';

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
                if (! Js::truthy($this->input('email')) && ! Js::truthy($this->input('userName'))) {
                    $validator->errors()->add('email', $this->failureMessage);
                }
            },
        ];
    }
}
