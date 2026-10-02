<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Requires a non-empty JSON array (a list, not an object).
 */
class NonEmptyList implements ValidationRule
{
    /**
     * Run even when the attribute is missing or empty.
     */
    public bool $implicit = true;

    public function __construct(private readonly string $message = 'The :attribute field must be a non-empty list.') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            $fail($this->message);
        }
    }
}
