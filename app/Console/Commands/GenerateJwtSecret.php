<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateJwtSecret extends Command
{
    protected $signature = 'jwt:secret
                            {--show : Display the secret instead of writing it to .env}
                            {--force : Overwrite an existing JWT_SECRET}';

    protected $description = 'Generate the JWT_SECRET used to sign API access tokens';

    public function handle(): int
    {
        $secret = bin2hex(random_bytes(32));

        if ($this->option('show')) {
            $this->line($secret);

            return self::SUCCESS;
        }

        $path = $this->laravel->environmentFilePath();

        if (! is_file($path)) {
            $this->error('.env file not found. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $hasSecret = preg_match('/^JWT_SECRET=(.+)$/m', $contents, $current) === 1 && trim($current[1]) !== '';

        if ($hasSecret && ! $this->option('force')) {
            $this->warn('JWT_SECRET is already set. Use --force to replace it (all issued tokens will become invalid).');

            return self::SUCCESS;
        }

        $contents = preg_match('/^JWT_SECRET=.*$/m', $contents)
            ? preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET='.$secret, $contents)
            : rtrim($contents).PHP_EOL.'JWT_SECRET='.$secret.PHP_EOL;

        file_put_contents($path, $contents);

        $this->info('JWT_SECRET written to .env');

        return self::SUCCESS;
    }
}
