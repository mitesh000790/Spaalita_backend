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
}
