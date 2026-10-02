<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'price' => fake()->randomFloat(2, 5, 200),
            'qty' => fake()->numberBetween(1, 50),
            'product_images' => ['image.jpg'],
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }

    public function withoutImages(): static
    {
        return $this->state(['product_images' => []]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
