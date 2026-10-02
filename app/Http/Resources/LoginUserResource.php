<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The user profile returned by POST /api/users/login.
 *
 * @mixin User
 */
class LoginUserResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userName' => $this->user_name,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,
            'postalCode' => $this->postal_code,
            'profilePicture' => $this->profile_picture,
            'role' => $this->role,
            'status' => $this->status,
            // An empty object when no address was saved.
            'billing' => $this->resource->billingAddress?->toApiArray() ?: (object) [],
            'shipping' => $this->resource->shippingAddress?->toApiArray() ?: (object) [],
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
