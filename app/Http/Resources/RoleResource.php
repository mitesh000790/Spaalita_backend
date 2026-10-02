<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;

/**
 * @mixin Role
 */
class RoleResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'name' => $this->name,
            'description' => $this->when($this->description !== null, $this->description),
            'status' => $this->status,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
