<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Admin extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $attributes = [
        'role' => 'admin',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function schemaRules(): array
    {
        return [
            'username' => ['path' => 'username', 'required' => 'Please provide a username'],
            'email' => [
                'path' => 'email',
                'required' => 'Please provide an email',
                'match' => ['/^\S+@\S+\.\S+$/', 'Please provide a valid email'],
            ],
            'password' => ['path' => 'password', 'required' => 'Please provide a password'],
        ];
    }
}
