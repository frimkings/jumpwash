<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'branch_id' => Branch::factory(),
            'code' => fake()->unique()->bothify('CUS-####'),
            'customer_code' => fake()->unique()->bothify('CUS-########-####'),
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($first.' '.$last),
            'phone' => fake()->unique()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'gps_location' => fake()->latitude().','.fake()->longitude(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
