<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates (or resets) the admin panel login.
 *
 * Defaults: admin / Admin@321 / admin@example.com. Override with
 * ADMIN_USERNAME, ADMIN_PASSWORD and ADMIN_EMAIL in .env.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('ADMIN_USERNAME', 'admin');

        Admin::query()->updateOrCreate(
            ['username' => $username],
            [
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@321')),
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $this->command?->info("Admin credentials set for \"{$username}\".");
    }
}
