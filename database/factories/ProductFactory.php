<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'name' => fake()->unique()->randomElement(['Shirt', 'Trousers', 'Suit', 'Dress', 'Blanket']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
