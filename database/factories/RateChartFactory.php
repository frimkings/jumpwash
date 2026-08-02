<?php

namespace Database\Factories;

use App\Models\LaundryService;
use App\Models\Product;
use App\Models\RateChart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateChart>
 */
class RateChartFactory extends Factory
{
    protected $model = RateChart::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'product_id' => Product::factory(),
            'laundry_service_id' => LaundryService::factory(),
            'price' => fake()->randomFloat(2, 3, 80),
        ];
    }
}
