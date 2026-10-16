<?php

namespace App\Http\Resources;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

/**
 * @mixin NewsletterSubscriber
 */
class NewsletterSubscriberResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->id,
            'email' => $this->email,
            'status' => $this->status,
            'source' => $this->source,
            'user' => $this->user_id,
            'subscribedAt' => self::isoDate($this->subscribed_at),
            'unsubscribedAt' => self::isoDate($this->unsubscribed_at),
            'createdAt' => self::isoDate($this->created_at),
            'updatedAt' => self::isoDate($this->updated_at),
        ];
    }
}
