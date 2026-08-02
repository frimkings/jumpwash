<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_number' => fake()->unique()->bothify('PAY-########-#####'),
            'receipt_number' => fake()->unique()->bothify('RCT-########-#####'),
            'payment_method' => fake()->randomElement(['cash', 'mobile_money', 'bank_transfer', 'pos_card']),
            'method' => 'cash',
            'status' => 'settled',
            'amount' => fake()->randomFloat(2, 5, 500),
            'paid_at' => now(),
        ];
    }
}
