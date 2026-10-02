<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails only when the value is missing or null (JavaScript `value == null`),
 * so 0, false and "" are accepted.
 */
class NotNull implements ValidationRule
{
    /**
     * Run even when the attribute is missing or empty.
     */
    public bool $implicit = true;

    public function __construct(private readonly string $message = 'The :attribute field is required.') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            $fail($this->message);
        }
    }
}
