<?php

namespace App\Contracts\Repositories;

use App\Models\Order;
use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function createForOrder(Order $order, array $data): Payment;

    public function paidAmount(Order $order): float;

    public function nextPaymentNumber(): string;
}
