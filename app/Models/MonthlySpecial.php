<?php

namespace App\Models;

class MonthlySpecial extends BaseModel
{
    /**
     * Folder under public/uploads for images saved from uploads and data URIs.
     */
    public const UPLOAD_FOLDER = 'monthlySpecial';

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
