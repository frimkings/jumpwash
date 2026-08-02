<?php

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function createForOrder(Order $order, array $data): Payment
    {
        return Payment::create(array_merge([
            'branch_id' => $order->branch_id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'received_by' => auth()->id(),
        ], $data));
    }

    public function paidAmount(Order $order): float
    {
        return round((float) Payment::query()->where('order_id', $order->id)->sum('amount'), 2);
    }

    public function nextPaymentNumber(): string
    {
        $prefix = 'JW-PAY-'.now()->format('Ymd').'-';
        $count = Payment::withoutGlobalScopes()
            ->where(function (Builder $query) use ($prefix): void {
                foreach (['payment_number', 'receipt_number', 'receipt_no'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $query->orWhere($column, 'like', $prefix.'%');
                    }
                }
            })
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
