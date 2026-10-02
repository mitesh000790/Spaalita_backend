<?php

namespace App\Models;

/**
 * Contact-form submission. Only a creation timestamp is kept.
 */
class Contact extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'subject',
        'message',
    ];

    protected function schemaRules(): array
    {
        return [
            'name' => ['path' => 'name', 'required' => true],
            'email' => ['path' => 'email', 'required' => true],
            'phone' => ['path' => 'phone', 'required' => true],
            'subject' => ['path' => 'subject', 'required' => true],
            'message' => ['path' => 'message', 'required' => true],
        ];
    }
}
