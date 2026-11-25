<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Services\StripeService;
use PHPUnit\Framework\TestCase;

class StripeServiceTest extends TestCase
{
    public function test_klarna_payment_intents_use_allowed_payment_method_types(): void
    {
        $params = StripeService::paymentIntentParams(9700, 'cad', ['userId' => 'guest'], PaymentMethod::Klarna->stripePaymentMethodTypes());

        $this->assertSame([
            'amount' => 9700,
            'currency' => 'cad',
            'metadata' => ['userId' => 'guest'],
            'allowed_payment_method_types' => ['klarna'],
        ], $params);

        // Removed from the API version of stripe-php 22: Stripe rejects the request when it is sent
        $this->assertArrayNotHasKey('payment_method_types', $params);
    }

    public function test_card_payment_intents_use_the_dashboard_payment_methods(): void
    {
        $this->assertSame(
            ['amount' => 5000, 'currency' => 'cad'],
            StripeService::paymentIntentParams(5000, 'cad', [], PaymentMethod::Card->stripePaymentMethodTypes()),
        );
    }
}
