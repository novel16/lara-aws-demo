<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('PRD-######'),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 1, 10000),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
