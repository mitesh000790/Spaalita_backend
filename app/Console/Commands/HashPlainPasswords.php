<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashPlainPasswords extends Command
{
    protected $signature = 'users:hash-plain-passwords';

    protected $description = 'Hash any user passwords that are still stored as plain text';

    public function handle(): int
    {
        $updated = 0;

        User::query()->select(['id', 'email', 'password'])->chunkById(200, function ($users) use (&$updated) {
            foreach ($users as $user) {
                if ($user->password !== null && ! preg_match('/^\$2[aby]\$/', $user->password)) {
                    $user->forceFill(['password' => Hash::make($user->password)])->saveQuietly();
                    $this->line("Updated password for user: {$user->email}");
                    $updated++;
                }
            }
        });

        $this->info("Done. {$updated} password(s) hashed.");

        return self::SUCCESS;
    }
}
