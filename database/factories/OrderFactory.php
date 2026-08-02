<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $total = fake()->randomFloat(2, 10, 600);

        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'order_number' => fake()->unique()->bothify('ORD-########-#####'),
            'status' => 'received',
            'payment_status' => 'unpaid',
            'billing_source' => 'cash',
            'subtotal' => $total,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => $total,
            'total_amount' => $total,
            'amount_paid' => 0,
            'balance' => $total,
        ];
    }
}
