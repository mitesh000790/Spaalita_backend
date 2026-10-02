<?php

namespace Database\Factories;

use App\Models\GiftCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftCard>
 */
class GiftCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_name' => 'Gift Card $'.fake()->randomElement([25, 50, 100]),
            'sku' => fake()->unique()->bothify('GC-####'),
            'price' => fake()->randomElement([25, 50, 100]),
            'qty' => 100,
            'product_images' => ['gift.jpg'],
            'description' => 'Gift card',
            'status' => 'active',
        ];
    }
}
