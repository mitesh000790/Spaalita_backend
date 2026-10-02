<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Service extends BaseModel
{
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'service_name',
        'service_image',
        'service_description',
        'button_url',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Records whose status is not "inactive" (null counts as not inactive).
     */
    public function scopeNotInactive(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->where('status', '!=', 'inactive')->orWhereNull('status'));
    }

    protected function schemaRules(): array
    {
        return [
            'service_name' => ['path' => 'serviceName', 'required' => 'Please provide a service name'],
            'service_description' => ['path' => 'serviceDescription', 'required' => 'Please provide a service description'],
            'status' => ['path' => 'status', 'enum' => self::STATUSES],
        ];
    }
}
