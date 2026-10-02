<?php

namespace App\Models\Concerns;

use App\Exceptions\SchemaValidationException;

/**
 * Document-level validation equivalent to the original Mongoose schemas
 * (`required`, `enum` and `match` validators).
 *
 * As with Mongoose, the rules run automatically when a record is created
 * and only on demand (validateSchema()) for updates, because the Node.js
 * API skipped validators for findByIdAndUpdate() calls.
 */
trait HasSchemaValidation
{
    public static function bootHasSchemaValidation(): void
    {
        static::creating(fn (self $model) => $model->validateSchema());
    }

    /**
     * Validation rules keyed by column name.
     *
     * @return array<string, array{path: string, required?: bool|string, enum?: list<string>, match?: array{0: string, 1: string}}>
     */
    abstract protected function schemaRules(): array;

    /**
     * @throws SchemaValidationException
     */
    public function validateSchema(): void
    {
        $errors = [];

        foreach ($this->schemaRules() as $attribute => $rule) {
            $path = $rule['path'];
            $value = $this->getAttribute($attribute);

            if (($rule['required'] ?? false) && ($value === null || $value === '')) {
                $errors[$path] = is_string($rule['required']) ? $rule['required'] : "Path `{$path}` is required.";

                continue;
            }

            if ($value === null) {
                continue;
            }

            if (isset($rule['enum']) && ! in_array($value, $rule['enum'], true)) {
                $errors[$path] = "`{$value}` is not a valid enum value for path `{$path}`.";
            } elseif (isset($rule['match']) && preg_match($rule['match'][0], (string) $value) !== 1) {
                $errors[$path] = $rule['match'][1];
            }
        }

        if ($errors !== []) {
            throw SchemaValidationException::make(class_basename($this), $errors);
        }
    }
}
