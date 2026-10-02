<?php

namespace App\Http\Resources;

use App\Models\MonthlySpecial;
use Illuminate\Http\Request;

/**
 * @mixin MonthlySpecial
 */
class MonthlySpecialResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'month' => $this->month,
            'image' => $this->image,
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
