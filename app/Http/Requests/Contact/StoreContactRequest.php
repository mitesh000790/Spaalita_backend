<?php

namespace App\Http\Requests\Contact;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;

class StoreContactRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Please complete all required fields and the reCAPTCHA.';

    public function rules(): array
    {
        return [
            'name' => [new Truthy],
            'email' => [new Truthy],
            'phone' => [new Truthy],
            'subject' => [new Truthy],
            'message' => [new Truthy],
            'g-recaptcha-response' => ['required', 'string'],
        ];
    }
}
