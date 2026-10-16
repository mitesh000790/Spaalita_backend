<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_manage_only_their_own_account(): void
    {
        $jane = User::factory()->create();
        $bob = User::factory()->create();
        $asJane = $this->asUser($jane);
        $forbidden = ['success' => false, 'message' => 'You can only access your own account'];

        $this->getJson("/api/users/{$jane->id}", $asJane)->assertOk()->assertJsonPath('data._id', $jane->id);
        $this->putJson("/api/users/{$jane->id}", ['city' => 'Ottawa'], $asJane)->assertOk()->assertJsonPath('data.city', 'Ottawa');
        $this->putJson("/api/users/{$jane->id}/addresses", ['shipping' => ['firstName' => 'Jane', 'city' => 'Ottawa']], $asJane)
            ->assertOk()->assertJsonPath('data.shipping.city', 'Ottawa');

        $this->getJson("/api/users/{$bob->id}", $asJane)->assertStatus(403)->assertExactJson($forbidden);
        $this->putJson("/api/users/{$bob->id}", ['city' => 'Hacked'], $asJane)->assertStatus(403);
        $this->putJson("/api/users/{$bob->id}/addresses", ['billing' => ['firstName' => 'X']], $asJane)->assertStatus(403);
        $this->assertNotSame('Hacked', $bob->fresh()->city);

        $this->getJson("/api/users/{$bob->id}", $this->asAdmin())->assertOk();
    }

    public function test_customers_cannot_change_their_role_or_status(): void
    {
        $jane = User::factory()->create();

        $this->putJson("/api/users/{$jane->id}", ['role' => 'admin', 'status' => 'inactive', 'firstName' => 'Janet'], $this->asUser($jane))
            ->assertOk()
            ->assertJsonPath('data.firstName', 'Janet')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.status', 'active');

        $this->putJson("/api/users/{$jane->id}", ['status' => 'inactive'], $this->asAdmin())->assertJsonPath('data.status', 'inactive');
    }

    public function test_account_details_save_the_newsletter_option(): void
    {
        $jane = User::factory()->create(['email' => 'jane@example.com']);
        $asJane = $this->asUser($jane);

        $this->putJson("/api/users/{$jane->id}", ['newsletterOption' => 'weekly'], $asJane)
            ->assertStatus(400)->assertJsonPath('message', 'Invalid newsletter option');

        $this->putJson("/api/users/{$jane->id}", ['newsletterOption' => 'subscribe'], $asJane)
            ->assertOk()->assertJsonPath('data.newsletterOption', 'subscribe');
        $this->assertSame('subscribed', NewsletterSubscriber::query()->where('email', 'jane@example.com')->value('status'));

        $this->putJson("/api/users/{$jane->id}", ['newsletterOption' => 'orderUpdates'], $asJane)
            ->assertOk()->assertJsonPath('data.newsletterOption', 'orderUpdates');
        $this->assertSame('unsubscribed', NewsletterSubscriber::query()->where('email', 'jane@example.com')->value('status'));
        $this->assertSame('orderUpdates', $jane->fresh()->newsletter_option);

        // "" (nothing chosen) leaves the saved choice alone.
        $this->putJson("/api/users/{$jane->id}", ['newsletterOption' => '', 'lastName' => 'Roe'], $asJane)
            ->assertOk()->assertJsonPath('data.newsletterOption', 'orderUpdates');

        $this->postJson('/api/users/login', ['email' => 'jane@example.com', 'password' => 'password'])
            ->assertJsonPath('data.user.newsletterOption', 'orderUpdates');
    }

    public function test_customers_change_their_password_with_the_current_one(): void
    {
        $jane = User::factory()->create(['email' => 'jane@example.com']);
        $asJane = $this->asUser($jane);

        $this->putJson("/api/users/{$jane->id}", ['passwordCurrent' => 'password', 'passwordNew' => 'New@1234', 'passwordConfirm' => 'Other'], $asJane)
            ->assertStatus(400)->assertJsonPath('message', 'New password and confirm password do not match');
        $this->putJson("/api/users/{$jane->id}", ['passwordCurrent' => 'wrong', 'passwordNew' => 'New@1234', 'passwordConfirm' => 'New@1234'], $asJane)
            ->assertStatus(400)->assertJsonPath('message', 'Current password is incorrect');
        $this->putJson("/api/users/{$jane->id}", ['passwordCurrent' => 'password', 'passwordNew' => 'New@1234', 'passwordConfirm' => 'New@1234'], $asJane)
            ->assertOk()->assertJsonMissingPath('data.password');

        $this->postJson('/api/users/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertStatus(401);
        $this->postJson('/api/users/login', ['email' => 'jane@example.com', 'password' => 'New@1234'])->assertOk();

        // Empty password fields (the form left blank) change nothing.
        $this->putJson("/api/users/{$jane->id}", ['passwordCurrent' => '', 'passwordNew' => '', 'passwordConfirm' => ''], $asJane)->assertOk();
        $this->postJson('/api/users/login', ['email' => 'jane@example.com', 'password' => 'New@1234'])->assertOk();

        // Admins can set a new password without the current one.
        $this->putJson("/api/users/{$jane->id}", ['passwordNew' => 'Admin@Set1'], $this->asAdmin())->assertOk();
        $this->postJson('/api/users/login', ['email' => 'jane@example.com', 'password' => 'Admin@Set1'])->assertOk();
    }

    public function test_admin_endpoints_reject_customer_tokens(): void
    {
        $asJane = $this->asUser(User::factory()->create());
        $denied = ['success' => false, 'message' => 'Admin access required'];

        $requests = [
            ['GET', '/api/users'],
            ['POST', '/api/users'],
            ['DELETE', '/api/users/6abfea1e3abbccd7871b8fd4'],
            ['POST', '/api/products'],
            ['PUT', '/api/products/6abfea1e3abbccd7871b8fd4'],
            ['DELETE', '/api/giftcards/6abfea1e3abbccd7871b8fd4'],
            ['POST', '/api/services'],
            ['POST', '/api/booking-policies'],
            ['POST', '/api/monthly-special'],
            ['GET', '/api/roles'],
            ['PUT', '/api/orders/6abfea1e3abbccd7871b8fd4/status'],
            ['DELETE', '/api/orders/6abfea1e3abbccd7871b8fd4'],
            ['POST', '/api/gallery/create'],
            ['DELETE', '/api/gallery/6abfea1e3abbccd7871b8fd4'],
            ['GET', '/api/coupon'],
            ['POST', '/api/category'],
            ['GET', '/api/dashboard'],
        ];

        foreach ($requests as [$method, $uri]) {
            $this->json($method, $uri, [], $asJane)->assertStatus(403)->assertExactJson($denied);
        }

        // Customers keep their own order history.
        $this->getJson('/api/orders', $asJane)->assertOk();
    }
}
