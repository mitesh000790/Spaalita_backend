<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;
use App\Support\Js;
use App\Support\ListInput;
use Illuminate\Validation\Validator;

/**
 * Create request for products and gift cards (same fields and rules).
 */
class StoreCatalogItemRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'All required fields must be provided';

    /**
     * Accept categories as an array, a "categories[]" field, a JSON string or a single id.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['categories' => self::categoriesFrom($this->getInputSource()->all())]);
    }

    public function rules(): array
    {
        return [
            'productName' => [new Truthy],
            'sku' => [new Truthy],
            'price' => [new Truthy],
            'qty' => [new Truthy],
            'description' => [new Truthy],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->input('categories') === []) {
                    $validator->errors()->add('categories', $this->failureMessage);
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<mixed>
     */
    public static function categoriesFrom(array $body): array
    {
        $categories = $body['categories'] ?? null;

        if (! Js::truthy($categories)) {
            $categories = $body['categories[]'] ?? null;
        }

        if (! Js::truthy($categories)) {
            return [];
        }

        return ListInput::parse($categories);
    }
}
