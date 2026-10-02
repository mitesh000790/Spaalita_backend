<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;

/**
 * @mixin Contact
 */
class ContactResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->when($this->address !== null, $this->address),
            'subject' => $this->subject,
            'message' => $this->message,
            'createdAt' => self::isoDate($this->created_at),
        ];
    }
}
