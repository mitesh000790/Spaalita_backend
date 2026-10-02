<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends CatalogItem
{
    use HasFactory;

    protected function categoryPivotTable(): string
    {
        return 'category_product';
    }
}
