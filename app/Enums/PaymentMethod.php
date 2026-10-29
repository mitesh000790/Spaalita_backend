<?php

namespace App\Enums;

/**
 * How the customer pays at checkout. Both methods are processed by Stripe
 * (Stripe hosts the Klarna redirect), so orders, PaymentIntents and the
 * webhook work the same way for each.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case Klarna = 'klarna';

    /**
     * The method sent by the client, or Card when none was sent.
     */
    public static function fromInput(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Card) : self::Card;
    }

    /**
     * `payment_method_types` for the PaymentIntent. Card keeps Stripe's default
     * (the payment methods enabled in the Dashboard), as before Klarna was added;
     * a Klarna PaymentIntent accepts Klarna only.
     *
     * @return list<string>
     */
    public function stripePaymentMethodTypes(): array
    {
        return match ($this) {
            self::Card => [],
            self::Klarna => ['klarna'],
        };
    }
}
