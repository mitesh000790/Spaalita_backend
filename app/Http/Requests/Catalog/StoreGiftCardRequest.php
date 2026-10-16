<?php

namespace App\Http\Requests\Catalog;

/**
 * Create request for gift cards: the product rules, except that the
 * description is optional.
 */
class StoreGiftCardRequest extends StoreCatalogItemRequest
{
    public function rules(): array
    {
        return array_diff_key(parent::rules(), ['description' => true]);
    }
}
