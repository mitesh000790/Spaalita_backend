<?php

namespace App\Rules;

use App\Support\Js;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the value is truthy by JavaScript rules, mirroring the
 * `if (!field)` required checks of the original API ("0" passes, 0 fails).
 */
class Truthy implements ValidationRule
{
    /**
     * Run even when the attribute is missing or empty.
     */
    public bool $implicit = true;

    public function __construct(private readonly string $message = 'The :attribute field is required.') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Js::truthy($value)) {
            $fail($this->message);
        }
    }
}
