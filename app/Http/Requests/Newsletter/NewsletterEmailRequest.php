<?php

namespace App\Http\Requests\Newsletter;

use App\Http\Requests\ApiFormRequest;

/**
 * Subscribe / unsubscribe body: {"email": "..."}.
 */
class NewsletterEmailRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'Please provide a valid email address';

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Ignore spaces typed or pasted around the address.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => trim($this->input('email'))]);
        }
    }
}
