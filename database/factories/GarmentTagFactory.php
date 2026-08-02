<?php

namespace Database\Factories;

use App\Models\GarmentTag;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarmentTag>
 */
class GarmentTagFactory extends Factory
{
    protected $model = GarmentTag::class;

    public function definition(): array
    {
        $code = fake()->unique()->bothify('TAG-########-######');

        return [
            'order_id' => Order::factory(),
            'tag_code' => $code,
            'garment_type' => fake()->randomElement(['Shirt', 'Trousers', 'Suit', 'Dress']),
            'color' => fake()->safeColorName(),
            'brand' => fake()->optional()->company(),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'gender' => fake()->randomElement(['Male', 'Female', 'Unisex']),
            'condition' => fake()->randomElement(['Good', 'Stained', 'Torn', 'Delicate']),
            'barcode_payload' => $code,
            'status' => 'received',
            'is_scanned' => false,
        ];
    }
}
