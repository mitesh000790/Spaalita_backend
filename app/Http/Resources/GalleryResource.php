<?php

namespace App\Http\Resources;

use App\Models\Gallery;
use Illuminate\Http\Request;

/**
 * @mixin Gallery
 */
class GalleryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'url' => $this->url,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
