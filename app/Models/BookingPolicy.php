<?php

namespace App\Models;

class BookingPolicy extends BaseModel
{
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'title',
        'description',
        'button_url',
        'status',
    ];

    protected $attributes = [
        'status' => 'inactive',
    ];

    protected function schemaRules(): array
    {
        return [
            'title' => ['path' => 'title', 'required' => 'Please provide a title'],
            'description' => ['path' => 'description', 'required' => 'Please provide a description'],
            'status' => ['path' => 'status', 'enum' => self::STATUSES],
        ];
    }
}
