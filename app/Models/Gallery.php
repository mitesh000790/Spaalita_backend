<?php

namespace App\Models;

class Gallery extends BaseModel
{
    protected $fillable = [
        'url',
        'created_at',
        'updated_at',
    ];

    protected function schemaRules(): array
    {
        return [
            'url' => ['path' => 'url', 'required' => true],
        ];
    }
}
