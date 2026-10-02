<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;

/**
 * @mixin Service
 */
class ServiceResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'serviceName' => $this->service_name,
            'serviceImage' => $this->service_image,
            'serviceDescription' => $this->service_description,
            'buttonUrl' => $this->button_url,
            'status' => $this->status,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
