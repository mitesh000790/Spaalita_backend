<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscriber extends BaseModel
{
    public const STATUS_SUBSCRIBED = 'subscribed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const SOURCES = ['footer', 'checkout', 'account', 'admin'];

    protected $fillable = [
        'email',
        'status',
        'source',
        'user_id',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_SUBSCRIBED,
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSubscribed(): bool
    {
        return $this->status === self::STATUS_SUBSCRIBED;
    }

    protected function schemaRules(): array
    {
        return [
            'email' => ['path' => 'email', 'required' => true],
            'status' => ['path' => 'status', 'enum' => [self::STATUS_SUBSCRIBED, self::STATUS_UNSUBSCRIBED]],
        ];
    }
}
