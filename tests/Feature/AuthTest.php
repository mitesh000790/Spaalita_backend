<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** A hash created by the original Node.js backend (bcryptjs) for "Admin@321". */
    private const BCRYPTJS_HASH = '$2a$10$cLPjAywPPuHfHusnoT6QEOZf2hHLbEjcPfebbXkyT4V1Y0O/9v9Im';

    public function test_admin_logs_in_with_a_password_hashed_by_the_node_backend(): void
    {
        $admin = Admin::factory()->create(['username' => 'admin', 'password' => self::BCRYPTJS_HASH]);

        $response = $this->postJson('/api/admin/login', ['username' => 'admin', 'password' => 'Admin@321']);

        $response->assertOk()->assertJson([
            'success' => true,
            'message' => 'Login successful',
            'data' => ['id' => $admin->id, 'username' => 'admin', 'email' => $admin->email, 'role' => 'admin'],
        ]);

        $claims = $this->claims($response->json('token'));
        $this->assertSame($admin->id, $claims['id']);
        $this->assertSame('admin', $claims['username']);
        $this->assertSame(3600, $claims['exp'] - $claims['iat']);
    }

    public function test_admin_login_failures(): void
    {
        Admin::factory()->create(['username' => 'admin', 'password' => self::BCRYPTJS_HASH]);
        Admin::factory()->inactive()->create(['username' => 'old', 'password' => self::BCRYPTJS_HASH]);

        $this->postJson('/api/admin/login', ['username' => 'admin'])
            ->assertStatus(400)->assertExactJson(['success' => false, 'message' => 'Please provide username and password']);
        $this->postJson('/api/admin/login', ['username' => 'admin', 'password' => 'wrong'])
            ->assertStatus(400)->assertJsonPath('message', 'Invalid credentials');
        $this->postJson('/api/admin/login', ['username' => 'nobody', 'password' => 'x'])
            ->assertStatus(400)->assertJsonPath('message', 'Invalid credentials');
        $this->postJson('/api/admin/login', ['username' => 'old', 'password' => 'Admin@321'])
            ->assertStatus(403)->assertJsonPath('message', 'Admin account is deactivated');
    }

    public function test_protected_routes_require_a_valid_token(): void
    {
        $this->getJson('/api/dashboard')
            ->assertStatus(401)->assertExactJson(['success' => false, 'message' => 'No token provided, authorization denied']);

        $this->getJson('/api/dashboard', ['Authorization' => 'Bearer not-a-token'])
            ->assertStatus(401)->assertExactJson(['success' => false, 'message' => 'Invalid or expired token']);

        $expired = JWT::encode(['id' => 'x', 'role' => 'admin', 'iat' => time() - 7200, 'exp' => time() - 3600], config('jwt.secret'), 'HS256');
        $this->getJson('/api/dashboard', ['Authorization' => "Bearer {$expired}"])->assertStatus(401);

        $this->getJson('/api/dashboard', $this->asAdmin())->assertOk();
    }

    public function test_user_logs_in_with_a_case_insensitive_email(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => bcrypt('Secret@1')]);

        $response = $this->postJson('/api/users/login', ['email' => 'JANE@Example.com', 'password' => 'Secret@1']);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.user.billing', [])
            ->assertJsonMissingPath('data.user.password');

        $claims = $this->claims($response->json('data.token'));
        $this->assertSame(['id', 'email', 'role', 'iat', 'exp'], array_keys($claims));
        $this->assertSame(86400, $claims['exp'] - $claims['iat']);
    }

    public function test_inactive_users_and_wrong_passwords_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'old@example.com']);
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/users/login', ['email' => 'old@example.com', 'password' => 'password'])->assertStatus(401);
        $this->postJson('/api/users/login', ['email' => 'jane@example.com', 'password' => 'nope'])
            ->assertStatus(401)->assertJsonPath('message', 'Invalid email or password');
        $this->postJson('/api/users/login', ['email' => 'jane@example.com'])
            ->assertStatus(400)->assertJsonPath('message', 'Email and password are required');
    }

    public function test_password_reset_flow(): void
    {
        User::factory()->create(['user_name' => 'jane', 'email' => 'jane@example.com']);

        $link = $this->postJson('/api/users/forgot-password', ['userName' => 'jane'])
            ->assertOk()
            ->assertJsonPath('message', 'Reset password link generated')
            ->json('resetLink');

        $this->assertStringStartsWith('http://localhost:3000/reset-password/', $link);
        $token = basename($link);

        $this->postJson('/api/users/reset-password/invalid', ['password' => 'x'])
            ->assertStatus(400)->assertJsonPath('message', 'Token invalid or expired');
        $this->postJson("/api/users/reset-password/{$token}", ['password' => 'NewPass1'])
            ->assertOk()->assertJsonPath('message', 'Password reset successful');
        $this->postJson("/api/users/reset-password/{$token}", ['password' => 'Again'])->assertStatus(400);

        $this->postJson('/api/users/login', ['email' => 'jane@example.com', 'password' => 'NewPass1'])->assertOk();
    }

    public function test_forgot_password_validation(): void
    {
        $this->postJson('/api/users/forgot-password', [])
            ->assertStatus(400)->assertJsonPath('message', 'Email or userName is required');
        $this->postJson('/api/users/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(404)->assertJsonPath('message', 'User not found');
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(string $token): array
    {
        return json_decode(JWT::urlsafeB64Decode(explode('.', $token)[1]), true);
    }
}
