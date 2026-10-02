<?php

namespace App\Http\Resources;

use App\Models\BookingPolicy;
use Illuminate\Http\Request;

/**
 * @mixin BookingPolicy
 */
class BookingPolicyResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'buttonUrl' => $this->button_url,
            'status' => $this->status,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
