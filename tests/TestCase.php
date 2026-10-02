<?php

namespace Tests;

use App\Models\Admin;
use App\Models\User;
use App\Services\JwtService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Stripe\PaymentIntent;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authorization header for a newly created admin.
     *
     * @return array<string, string>
     */
    protected function asAdmin(?Admin $admin = null): array
    {
        $admin ??= Admin::factory()->create();

        return ['Authorization' => 'Bearer '.app(JwtService::class)->issueAdminToken($admin)];
    }

    /**
     * @return array<string, string>
     */
    protected function asUser(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->issueUserToken($user)];
    }

    /**
     * Replace Stripe with a fake that records created payment intents.
     *
     * @param  array<int, array<string, mixed>>  $calls  receives [amount, currency, metadata] per call
     */
    protected function fakeStripe(array &$calls = []): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) use (&$calls) {
            $mock->shouldReceive('createPaymentIntent')->andReturnUsing(
                function (int $amount, string $currency, array $metadata = []) use (&$calls) {
                    $calls[] = compact('amount', 'currency', 'metadata');
                    $id = 'pi_test_'.count($calls);

                    return PaymentIntent::constructFrom(['id' => $id, 'client_secret' => $id.'_secret']);
                }
            );
        });
    }

    /**
     * Send a raw multipart/form-data request, exactly as a browser would,
     * so the application's own body parser is exercised.
     *
     * @param  list<array{0: string, 1: string}>  $fields  [name, value] pairs (names may repeat)
     * @param  list<array{0: string, 1: string, 2: string}>  $files  [name, filename, mime type] (names may repeat)
     * @param  array<string, string>  $headers
     */
    protected function multipart(string $method, string $uri, array $fields, array $files = [], array $headers = []): TestResponse
    {
        $boundary = '----TestBoundary'.bin2hex(random_bytes(6));
        $body = '';

        foreach ($fields as [$name, $value]) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }

        foreach ($files as [$name, $filename, $type]) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n"
                ."Content-Type: {$type}\r\n\r\n".self::pngBytes()."\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $server = $this->transformHeadersToServerVars($headers + [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
            'Accept' => 'application/json',
        ]);

        return $this->call($method, $uri, [], [], [], $server, $body);
    }

    protected static function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }
}
