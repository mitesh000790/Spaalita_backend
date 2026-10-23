<?php

namespace Tests\Feature;

use App\Models\BookingPolicy;
use App\Models\Gallery;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_are_paginated_and_soft_deleted(): void
    {
        $headers = $this->asAdmin();
        $payload = ['serviceImage' => '/img.jpg', 'serviceDescription' => 'd', 'buttonUrl' => '/book'];

        $this->postJson('/api/services', ['serviceName' => 'X'], $headers)
            ->assertStatus(400)->assertJsonPath('message', 'All fields are required');
        $id = $this->postJson('/api/services', ['serviceName' => ' Facial '] + $payload, $headers)
            ->assertCreated()->assertJsonPath('data.serviceName', 'Facial')->assertJsonPath('data.status', 'active')->json('data._id');
        $this->postJson('/api/services', ['serviceName' => 'Massage'] + $payload, $headers)->assertCreated();

        $this->getJson('/api/services?limit=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('pagination', ['total' => 2, 'page' => 1, 'limit' => 1, 'totalPages' => 2]);

        $this->deleteJson("/api/services/{$id}", [], $headers)->assertJsonPath('message', 'Service soft deleted (status set to inactive)');
        $this->getJson("/api/services/{$id}")->assertNotFound()->assertJsonPath('message', 'Service not found');
        $this->getJson('/api/services')->assertJsonCount(1, 'data');
    }

    public function test_service_image_upload_and_optional_fields(): void
    {
        Storage::fake('uploads');
        $headers = $this->asAdmin();

        // Multipart, as the admin panel sends it (empty button URL)
        $response = $this->multipart('POST', '/api/services', [
            ['serviceName', 'facials'], ['serviceDescription', '<p>d</p>'], ['buttonUrl', ''],
        ], [['serviceImage', 'banner.png', 'image/png']], $headers);

        $response->assertCreated()->assertJsonPath('data.buttonUrl', '');
        $image = $response->json('data.serviceImage');
        $this->assertMatchesRegularExpression('#^/uploads/services/\d{13}-\d+\.png$#', $image);
        Storage::disk('uploads')->assertExists(substr($image, strlen('/uploads/')));
        $id = $response->json('data._id');

        // Without a new file the image is kept
        $this->multipart('PUT', "/api/services/{$id}", [['serviceName', 'massages'], ['serviceDescription', 'd2'], ['buttonUrl', '/book']], [], $headers)
            ->assertOk()
            ->assertJsonPath('data.serviceName', 'massages')
            ->assertJsonPath('data.serviceImage', $image)
            ->assertJsonPath('data.buttonUrl', '/book');

        // A new file replaces it
        $replaced = $this->multipart('PUT', "/api/services/{$id}", [['serviceName', 'massages']], [['serviceImage', 'new.gif', 'image/gif']], $headers)
            ->assertOk()->json('data.serviceImage');
        $this->assertMatchesRegularExpression('#^/uploads/services/\d{13}-\d+\.gif$#', $replaced);

        // Image and button URL are optional
        $this->postJson('/api/services', ['serviceName' => 'waxing', 'serviceDescription' => 'd'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.serviceImage', '')
            ->assertJsonPath('data.buttonUrl', '');
    }

    public function test_booking_policy_button_url_is_optional(): void
    {
        $this->postJson('/api/booking-policies', ['title' => 'T', 'description' => 'd', 'buttonUrl' => ''], $this->asAdmin())
            ->assertCreated()->assertJsonPath('data.buttonUrl', '');
        $this->postJson('/api/booking-policies', ['title' => 'U', 'description' => 'd'], $this->asAdmin())
            ->assertCreated()->assertJsonPath('data.buttonUrl', '');
    }

    public function test_only_one_booking_policy_is_active(): void
    {
        $headers = $this->asAdmin();
        $payload = ['description' => 'd', 'buttonUrl' => '/u'];

        $first = $this->postJson('/api/booking-policies', ['title' => 'One', 'status' => 'active'] + $payload, $headers)->json('data._id');
        $this->postJson('/api/booking-policies', ['title' => 'Two', 'status' => 'active'] + $payload, $headers);
        $this->postJson('/api/booking-policies', ['title' => 'Three'] + $payload, $headers)->assertJsonPath('data.status', 'inactive');

        $this->assertSame(['Two'], BookingPolicy::query()->where('status', 'active')->pluck('title')->all());

        $this->putJson("/api/booking-policies/{$first}", ['status' => 'active'], $headers)->assertOk();
        $this->assertSame(['One'], BookingPolicy::query()->where('status', 'active')->pluck('title')->all());
    }

    public function test_one_monthly_special_per_month(): void
    {
        $headers = $this->asAdmin();

        $this->postJson('/api/monthly-special', ['month' => ' June ', 'image' => '/june.jpg'], $headers)->assertCreated()->assertJsonPath('data.month', 'June');
        $july = $this->postJson('/api/monthly-special', ['month' => 'July', 'image' => '/july.jpg'], $headers)->json('data._id');

        $this->postJson('/api/monthly-special', ['month' => 'June ', 'image' => '/x.jpg'], $headers)
            ->assertStatus(409)->assertJsonPath('message', "A monthly special for 'June ' already exists. Only one per month is allowed.");
        $this->putJson("/api/monthly-special/{$july}", ['month' => 'June'], $headers)->assertStatus(409);
        $this->getJson('/api/monthly-special?search=jul')->assertJsonCount(1, 'data')->assertJsonMissingPath('pagination');
    }

    public function test_role_names_are_unique(): void
    {
        $headers = $this->asAdmin();

        $this->postJson('/api/roles', ['name' => ' Manager '], $headers)->assertCreated()->assertJsonPath('data.name', 'Manager');
        $this->postJson('/api/roles', ['name' => 'Manager'], $headers)->assertStatus(409)->assertJsonPath('message', 'Role name already exists');
        $this->postJson('/api/roles', [], $headers)->assertStatus(400)->assertJsonPath('message', 'Role name is required');
        $this->getJson('/api/roles')->assertStatus(401);
    }

    public function test_contact_form(): void
    {
        $this->postJson('/api/contact', ['name' => 'Ann'])
            ->assertStatus(400)->assertJsonPath('message', 'Please complete all required fields and the reCAPTCHA.');

        config(['services.recaptcha.secret' => 'test-recaptcha-secret']);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'contact_submit',
            ]),
        ]);

        $this->postJson('/api/contact', [
            'name' => 'Ann',
            'email' => 'a@x.com',
            'phone' => 123,
            'subject' => 'Hi',
            'message' => 'Hello',
            'g-recaptcha-response' => 'valid-test-token',
        ])
            ->assertCreated()
            ->assertJsonPath('data.phone', '123')
            ->assertJsonMissingPath('data.updatedAt');

        Http::assertSent(fn ($request) => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'test-recaptcha-secret'
            && $request['response'] === 'valid-test-token');
    }

    public function test_contact_form_rejects_invalid_recaptcha_tokens(): void
    {
        config(['services.recaptcha.secret' => 'test-recaptcha-secret']);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $this->postJson('/api/contact', [
            'name' => 'Ann',
            'email' => 'a@x.com',
            'phone' => 123,
            'subject' => 'Hi',
            'message' => 'Hello',
            'g-recaptcha-response' => 'invalid-test-token',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'reCAPTCHA verification failed. Please try again.');

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_contact_form_rejects_recaptcha_wrong_action_or_low_score(): void
    {
        config(['services.recaptcha.secret' => 'test-recaptcha-secret', 'services.recaptcha.minimum_score' => 0.5]);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::sequence()
                ->push(['success' => true, 'score' => 0.9, 'action' => 'other_action'])
                ->push(['success' => true, 'score' => 0.3, 'action' => 'contact_submit']),
        ]);
        $payload = [
            'name' => 'Ann',
            'email' => 'a@x.com',
            'phone' => 123,
            'subject' => 'Hi',
            'message' => 'Hello',
            'g-recaptcha-response' => 'test-token',
        ];

        $this->postJson('/api/contact', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'reCAPTCHA verification failed. Please try again.');
        $this->postJson('/api/contact', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'reCAPTCHA verification failed. Please try again.');

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_contact_form_fails_closed_when_recaptcha_is_not_configured(): void
    {
        config(['services.recaptcha.secret' => null]);

        $this->postJson('/api/contact', [
            'name' => 'Ann',
            'email' => 'a@x.com',
            'phone' => 123,
            'subject' => 'Hi',
            'message' => 'Hello',
            'g-recaptcha-response' => 'test-token',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_gallery_upload_list_update_and_delete(): void
    {
        Storage::fake('uploads');
        $headers = $this->asAdmin();

        $images = $this->multipart('POST', '/api/gallery/create', [], [['images', 'a.png', 'image/png'], ['images', 'b.png', 'image/png']], $headers)
            ->assertCreated()->assertJsonCount(2, 'data')->json('data');
        $this->assertMatchesRegularExpression('#^/uploads/gallery/\d{13}-\d+\.png$#', $images[0]['url']);

        $this->postJson('/api/gallery/create', [], $headers)->assertStatus(500)->assertExactJson(['message' => 'Failed to upload images']);
        $this->getJson('/api/gallery?date='.now()->utc()->format('d-m-Y'))->assertJsonCount(2, 'data');
        $this->getJson('/api/gallery?date=01-01-2020')->assertJsonCount(0, 'data');

        $this->multipart('PUT', "/api/gallery/{$images[0]['_id']}", [['note', 'x']], [], $headers)
            ->assertStatus(400)->assertExactJson(['message' => 'No image provided for update.']);
        $this->multipart('PUT', "/api/gallery/{$images[0]['_id']}", [], [['image', 'c.png', 'image/png']], $headers)
            ->assertOk()->assertJsonPath('message', 'Image updated successfully');

        $this->deleteJson("/api/gallery/{$images[1]['_id']}")->assertStatus(401);
        $this->deleteJson("/api/gallery/{$images[1]['_id']}", [], $headers)->assertOk()->assertJsonPath('message', 'Image deleted successfully');
        $this->deleteJson("/api/gallery/{$images[1]['_id']}", [], $headers)->assertNotFound()->assertExactJson(['message' => 'Image not found']);
        $this->assertSame(1, Gallery::query()->count());
    }

    public function test_dashboard_counts(): void
    {
        User::factory()->count(2)->create();
        User::factory()->inactive()->create();
        Product::factory()->count(3)->create();

        $this->getJson('/api/dashboard', $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('data', [
                'totalUsers' => 3,
                'activeUsers' => 2,
                'totalOrders' => 0,
                'completedOrders' => 0,
                'pendingOrders' => 0,
                'cancelledOrders' => 0,
                'totalProducts' => 3,
                'totalGiftCards' => 0,
            ]);
    }

    public function test_unknown_routes_return_json(): void
    {
        $this->getJson('/api/nothing')->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Cannot GET /api/nothing']);
        $this->getJson('/')->assertExactJson(['message' => 'Welcome to SpaaLita Backend API']);
    }
}
