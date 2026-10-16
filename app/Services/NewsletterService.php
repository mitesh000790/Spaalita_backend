<?php

namespace App\Services;

use App\Models\NewsletterSubscriber;
use App\Models\User;

/**
 * Keeps the newsletter subscriber list in sync with sign-ups from the
 * footer form, the checkout checkbox and the My Account preference.
 * A customer account with the same email shows the current choice
 * (users.newsletter_option).
 */
class NewsletterService
{
    /**
     * Subscribe an email address (or re-subscribe it).
     *
     * @return array{0: NewsletterSubscriber, 1: bool} the subscriber and whether this call subscribed it
     */
    public function subscribe(string $email, string $source, ?User $user = null): array
    {
        $email = mb_strtolower(trim($email));
        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $wasSubscribed = $subscriber->exists && $subscriber->isSubscribed();

        $user ??= User::query()->where('email', $email)->first();

        if ($user !== null) {
            $subscriber->user_id = $user->id;
        }

        if (! $wasSubscribed) {
            $subscriber->fill([
                'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
                'source' => $source,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        }

        $subscriber->save();

        if ($user !== null && $user->newsletter_option !== User::NEWSLETTER_SUBSCRIBE) {
            $user->forceFill(['newsletter_option' => User::NEWSLETTER_SUBSCRIBE])->save();
        }

        return [$subscriber, ! $wasSubscribed];
    }

    /**
     * Unsubscribe an email address. Returns null when it was never subscribed.
     */
    public function unsubscribe(string $email): ?NewsletterSubscriber
    {
        $email = mb_strtolower(trim($email));
        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber !== null && $subscriber->isSubscribed()) {
            $subscriber->forceFill([
                'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => now(),
            ])->save();
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->newsletter_option === User::NEWSLETTER_SUBSCRIBE) {
            $user->forceFill(['newsletter_option' => User::NEWSLETTER_UNSUBSCRIBE])->save();
        }

        return $subscriber;
    }

    /**
     * Apply a customer's My Account choice (already saved on the account):
     * "subscribe" joins the newsletter; "unsubscribe" and "orderUpdates" leave it.
     */
    public function applyUserOption(User $user, string $option): void
    {
        if ($option === User::NEWSLETTER_SUBSCRIBE) {
            $this->subscribe($user->email, 'account', $user);
        } else {
            $this->unsubscribe($user->email);
        }
    }
}
