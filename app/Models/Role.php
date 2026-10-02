<?php

namespace App\Models;

class Role extends BaseModel
{
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function schemaRules(): array
    {
        return [
            'name' => ['path' => 'name', 'required' => 'Please provide a role name'],
            'status' => ['path' => 'status', 'enum' => self::STATUSES],
        ];
    }
}
