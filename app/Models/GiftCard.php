<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class GiftCard extends CatalogItem
{
    use HasFactory;

    protected function categoryPivotTable(): string
    {
        return 'category_gift_card';
    }

    /**
     * Same rules as products, except that the description is optional.
     */
    protected function schemaRules(): array
    {
        return array_diff_key(parent::schemaRules(), ['description' => true]);
    }
}
