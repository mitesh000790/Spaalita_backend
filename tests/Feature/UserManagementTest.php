<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_reports_every_missing_field(): void
    {
        $this->postJson('/api/users', ['userName' => 'jane', 'email' => 'jane@example.com'], $this->asAdmin())
            ->assertStatus(400)
            ->assertExactJson([
                'success' => false,
                'message' => 'All fields are required',
                'missingFields' => ['firstName', 'lastName', 'phone', 'country', 'city', 'address', 'postalCode', 'password'],
            ]);
    }

    public function test_create_normalises_fields_and_never_returns_the_password(): void
    {
        Storage::fake('uploads');

        $response = $this->multipart('POST', '/api/users', [
            ['userName', ' jane '], ['firstName', 'Jane'], ['lastName', 'Roe'], ['email', 'Jane@Example.COM'],
            ['phone', '555'], ['country', 'CA'], ['city', 'Toronto'], ['address', '1 St'], ['postalCode', 'M1'], ['password', 'Secret@1'],
        ], [['profilePicture', 'me.png', 'image/png']], $this->asAdmin());

        $response->assertCreated()
            ->assertJsonPath('data.userName', 'jane')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.billing');
        $this->assertMatchesRegularExpression('#^/uploads/userProfile/\d{13}-\d+\.png$#', $response->json('data.profilePicture'));

        $this->postJson('/api/users', [
            'userName' => 'other', 'firstName' => 'a', 'lastName' => 'b', 'email' => 'JANE@example.com', 'phone' => '1',
            'country' => 'c', 'city' => 'c', 'address' => 'a', 'postalCode' => 'p', 'password' => 'x',
        ], $this->asAdmin())->assertStatus(409)->assertJsonPath('message', 'User name or email already exists');
    }

    public function test_update_stores_the_profile_picture_under_uploads(): void
    {
        Storage::fake('uploads');
        $user = User::factory()->create();

        $response = $this->multipart('PUT', "/api/users/{$user->id}", [['city', 'Ottawa']], [['profilePicture', 'me.png', 'image/png']], $this->asAdmin());

        $response->assertOk()->assertJsonPath('data.city', 'Ottawa');
        $this->assertMatchesRegularExpression('#^/uploads/userProfile/\d{13}-\d+\.png$#', $response->json('data.profilePicture'));
    }

    public function test_update_changes_only_the_fields_sent(): void
    {
        $user = User::factory()->create(['first_name' => 'Jane', 'city' => 'Toronto']);

        $this->putJson("/api/users/{$user->id}", ['firstName' => ' Janet ', 'email' => 'JANET@example.com'], $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('message', 'User updated successfully')
            ->assertJsonPath('data.firstName', 'Janet')
            ->assertJsonPath('data.email', 'janet@example.com')
            ->assertJsonPath('data.city', 'Toronto');
    }

    public function test_addresses_are_replaced_as_a_whole(): void
    {
        $user = User::factory()->create();
        $headers = $this->asAdmin();

        $this->putJson("/api/users/{$user->id}/addresses", [], $headers)
            ->assertStatus(400)->assertJsonPath('message', 'No address data provided');

        $this->putJson("/api/users/{$user->id}/addresses", ['billing' => ['firstName' => 'Jane', 'city' => 'Toronto', 'phone' => 555, 'extra' => 'x']], $headers)
            ->assertOk()->assertJsonPath('data.billing', ['firstName' => 'Jane', 'city' => 'Toronto', 'phone' => '555']);

        $this->putJson("/api/users/{$user->id}/addresses", ['billing' => ['city' => 'Ottawa'], 'shipping' => ['giftCard' => 'GC1']], $headers)
            ->assertOk()
            ->assertJsonPath('data.billing', ['city' => 'Ottawa'])
            ->assertJsonPath('data.shipping', ['giftCard' => 'GC1']);
    }

    public function test_listing_search_and_soft_delete(): void
    {
        $jane = User::factory()->create(['first_name' => 'Jane']);
        User::factory()->count(2)->create();
        $headers = $this->asAdmin();

        $this->getJson('/api/users?limit=2', $headers)
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination', ['total' => 3, 'page' => 1, 'limit' => 2, 'totalPages' => 2]);
        $this->getJson('/api/users?search=JANE', $headers)->assertJsonCount(1, 'data');

        $this->deleteJson("/api/users/{$jane->id}", [], $headers)->assertOk()->assertJsonPath('message', 'User deleted SuccessFully');
        $this->getJson('/api/users', $headers)->assertJsonCount(2, 'data');
        $this->getJson("/api/users/{$jane->id}", $headers)->assertOk()->assertJsonPath('data.status', 'inactive');
    }
}
