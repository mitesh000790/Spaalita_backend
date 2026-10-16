<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_subscribe_and_unsubscribe(): void
    {
        $this->postJson('/api/newsletter/subscribe', ['email' => 'not-an-email'])
            ->assertStatus(400)->assertExactJson(['success' => false, 'message' => 'Please provide a valid email address']);

        $this->postJson('/api/newsletter/subscribe', ['email' => ' Ann@Example.com '])
            ->assertCreated()
            ->assertJsonPath('message', 'Thank you for subscribing to our newsletter')
            ->assertJsonPath('data.email', 'ann@example.com')
            ->assertJsonPath('data.status', 'subscribed')
            ->assertJsonPath('data.source', 'footer');

        $this->postJson('/api/newsletter/subscribe', ['email' => 'ann@example.com'])
            ->assertOk()->assertJsonPath('message', 'This email is already subscribed');
        $this->assertSame(1, NewsletterSubscriber::query()->count());

        $this->postJson('/api/newsletter/unsubscribe', ['email' => 'ANN@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'You have been unsubscribed from our newsletter')
            ->assertJsonPath('data.status', 'unsubscribed');
        $this->postJson('/api/newsletter/unsubscribe', ['email' => 'nobody@example.com'])
            ->assertNotFound()->assertJsonPath('message', 'This email is not subscribed');

        $this->postJson('/api/newsletter/subscribe', ['email' => 'ann@example.com'])
            ->assertCreated()->assertJsonPath('data.status', 'subscribed')->assertJsonPath('data.unsubscribedAt', null);
    }

    public function test_subscriptions_follow_the_customer_account(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/newsletter/subscribe', ['email' => 'jane@example.com'])
            ->assertCreated()->assertJsonPath('data.user', $user->id);
        $this->assertSame('subscribe', $user->fresh()->newsletter_option);

        $this->postJson('/api/newsletter/unsubscribe', ['email' => 'jane@example.com'])->assertOk();
        $this->assertSame('unsubscribe', $user->fresh()->newsletter_option);
    }

    public function test_admins_list_search_and_delete_subscribers(): void
    {
        $this->postJson('/api/newsletter/subscribe', ['email' => 'a@example.com'])->assertCreated();
        $this->travel(1)->seconds();
        $this->postJson('/api/newsletter/subscribe', ['email' => 'b@example.com'])->assertCreated();
        $this->postJson('/api/newsletter/unsubscribe', ['email' => 'b@example.com'])->assertOk();

        $this->getJson('/api/newsletter')->assertStatus(401);
        $this->getJson('/api/newsletter', $this->asUser(User::factory()->create()))
            ->assertStatus(403)->assertExactJson(['success' => false, 'message' => 'Admin access required']);

        $admin = $this->asAdmin();

        $this->getJson('/api/newsletter', $admin)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.email', 'b@example.com')
            ->assertJsonPath('pagination', ['total' => 2, 'page' => 1, 'limit' => 20, 'totalPages' => 1]);
        $this->getJson('/api/newsletter?status=subscribed', $admin)->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'a@example.com');
        $this->getJson('/api/newsletter?search=B@', $admin)->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'b@example.com');

        $id = NewsletterSubscriber::query()->where('email', 'a@example.com')->value('id');
        $this->deleteJson("/api/newsletter/{$id}", [], $admin)->assertOk()->assertJsonPath('message', 'Subscriber deleted successfully');
        $this->deleteJson("/api/newsletter/{$id}", [], $admin)->assertNotFound()->assertJsonPath('message', 'Subscriber not found');
    }

    public function test_the_checkout_checkbox_subscribes_the_customer(): void
    {
        $this->fakeStripe();
        $order = ['items' => [['name' => 'Facial', 'price' => 50, 'qty' => 1]], 'totalAmount' => 50];

        $this->postJson('/api/orders', $order + [
            'guestInfo' => ['name' => 'Guest', 'email' => 'Guest@Example.com'],
            'newsletter' => true,
        ])->assertCreated();
        $this->assertSame('checkout', NewsletterSubscriber::query()->where('email', 'guest@example.com')->value('source'));

        $jane = User::factory()->create(['email' => 'jane@example.com']);
        $this->postJson('/api/orders', $order + ['newsletter' => true], $this->asUser($jane))->assertCreated();
        $this->assertTrue(NewsletterSubscriber::query()->where('email', 'jane@example.com')->where('user_id', $jane->id)->exists());
        $this->assertSame('subscribe', $jane->fresh()->newsletter_option);

        // Unticked, or no usable email: the order is still placed, nobody is subscribed.
        $this->postJson('/api/orders', $order + ['guestInfo' => ['name' => 'Bob', 'email' => 'bob@example.com']])->assertCreated();
        $this->postJson('/api/orders', $order + ['guestInfo' => ['name' => 'NoEmail'], 'newsletter' => true])->assertCreated();
        $this->assertSame(2, NewsletterSubscriber::query()->count());
    }
}
