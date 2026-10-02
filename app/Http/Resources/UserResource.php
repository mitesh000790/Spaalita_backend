<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin User
 */
class UserResource extends ApiResource
{
    /**
     * The password hash and reset token are never exposed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'profilePicture' => $this->profile_picture,
            'userName' => $this->user_name,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,
            'postalCode' => $this->postal_code,
            'role' => $this->role,
            'status' => $this->status,
            'billing' => self::address($this->resource->billingAddress),
            'shipping' => self::address($this->resource->shippingAddress),
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }

    /**
     * Saved address in its API shape; omitted entirely when none was saved.
     *
     * @return array<string, string>|MissingValue
     */
    public static function address(?UserAddress $address): array|MissingValue
    {
        $fields = $address?->toApiArray() ?? [];

        return $fields === [] ? new MissingValue : $fields;
    }
}
