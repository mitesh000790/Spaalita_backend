<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CODE###')),
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'allow_free_shipping' => false,
            'expiry_date' => now()->addMonth(),
            'min_order_amount' => 0,
            'status' => 'active',
        ];
    }

    public function fixed(float $amount): static
    {
        return $this->state(['discount_type' => 'fixed', 'discount_value' => $amount]);
    }

    public function expired(): static
    {
        return $this->state(['expiry_date' => now()->subDay()]);
    }
}
