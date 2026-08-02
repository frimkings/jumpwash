<?php

namespace App\Services;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private readonly PaymentRepositoryInterface $payments)
    {
    }

    public function record(Order $order, array $payload): Payment
    {
        return DB::transaction(function () use ($order, $payload): Payment {
            $payment = $this->payments->createForOrder($order, $payload);
            $this->syncOrderSummary($order->fresh());

            $freshOrder = $order->fresh(['customer']);

            ActivityLog::record('payment.received', $payment, [
                'order_number' => $freshOrder->order_number,
                'customer' => $freshOrder->customer?->name,
            ], [], [
                'amount' => $payment->amount,
                'payment_method' => $payload['payment_method'] ?? $payload['method'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'amount_paid' => $freshOrder->amount_paid,
                'balance' => $freshOrder->balance,
                'payment_status' => $freshOrder->payment_status,
            ]);

            return $payment;
        });
    }

    public function syncOrderSummary(Order $order): void
    {
        $total = (float) ($order->total_amount ?: $order->total);
        $paid = $this->payments->paidAmount($order);
        $balance = max($total - $paid, 0);

        $order->update([
            'total_amount' => $total,
            'amount_paid' => $paid,
            'balance' => $balance,
            'payment_status' => $this->statusFor($total, $paid),
        ]);
    }

    public function statusFor(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'part_paid';
    }
}
