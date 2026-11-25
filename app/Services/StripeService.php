<?php

namespace App\Services;

use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Thin wrapper around the Stripe SDK so payment calls can be faked in tests.
 */
class StripeService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $webhookSecret,
        private readonly ?string $apiBase = null,
    ) {}

    /**
     * @param  array<string, string>  $metadata
     * @param  list<string>  $paymentMethodTypes  e.g. ['klarna']; empty uses the payment methods enabled in the Dashboard
     */
    public function createPaymentIntent(int $amountInCents, string $currency, array $metadata = [], array $paymentMethodTypes = []): PaymentIntent
    {
        return $this->client()->paymentIntents->create(
            self::paymentIntentParams($amountInCents, $currency, $metadata, $paymentMethodTypes),
        );
    }

    /**
     * Parameters of PaymentIntent create.
     *
     * The restriction to given payment methods is sent as `allowed_payment_method_types`:
     * the API version of stripe-php 22 (2026-09-30.endive) no longer accepts
     * `payment_method_types` ("The `payment_method_types` parameter is no longer supported").
     * The methods must also be enabled in the Dashboard (Settings > Payment methods).
     *
     * @param  array<string, string>  $metadata
     * @param  list<string>  $paymentMethodTypes
     * @return array<string, mixed>
     */
    public static function paymentIntentParams(int $amountInCents, string $currency, array $metadata = [], array $paymentMethodTypes = []): array
    {
        $params = ['amount' => $amountInCents, 'currency' => $currency];

        if ($metadata !== []) {
            $params['metadata'] = $metadata;
        }

        if ($paymentMethodTypes !== []) {
            $params['allowed_payment_method_types'] = $paymentMethodTypes;
        }

        return $params;
    }

    /**
     * Verify a webhook payload against its Stripe-Signature header.
     *
     * @throws \UnexpectedValueException|SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, ?string $signature): Event
    {
        return Webhook::constructEvent($payload, (string) $signature, (string) $this->webhookSecret);
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(array_filter([
            'api_key' => $this->secretKey,
            'api_base' => $this->apiBase,
        ]));
    }
}
