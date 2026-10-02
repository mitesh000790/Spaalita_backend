<?php

namespace App\Models;

class MonthlySpecial extends BaseModel
{
    protected $fillable = [
        'month',
        'image',
    ];

    protected function schemaRules(): array
    {
        return [
            'month' => ['path' => 'month', 'required' => 'Please provide a title'],
            'image' => ['path' => 'image', 'required' => 'Please provide an image'],
        ];
    }
}
