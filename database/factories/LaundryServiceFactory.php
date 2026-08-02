<?php

namespace Database\Factories;

use App\Models\LaundryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaundryService>
 */
class LaundryServiceFactory extends Factory
{
    protected $model = LaundryService::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Laundry', 'Dry Cleaning', 'Ironing', 'Starching', 'Express Service']);

        return [
            'branch_id' => null,
            'name' => $name,
            'code' => str($name)->slug('-')->upper()->toString(),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 3, 40),
            'tax_percentage' => 0,
            'unit' => 'piece',
            'turnaround_hours' => 24,
            'is_active' => true,
        ];
    }
}
