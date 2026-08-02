<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function earnForPayment(Payment $payment): ?LoyaltyTransaction
    {
        $payment->loadMissing(['customer', 'order']);

        if (! $payment->customer || ($payment->payment_method ?? $payment->method) === 'loyalty_credit') {
            return null;
        }

        if (LoyaltyTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('type', LoyaltyTransaction::TYPE_EARNED)
            ->exists()) {
            return null;
        }

        $points = $this->pointsForAmount((float) $payment->amount, $payment->branch_id);

        if ($points <= 0) {
            return null;
        }

        return $this->addTransaction(
            customer: $payment->customer,
            type: LoyaltyTransaction::TYPE_EARNED,
            points: $points,
            moneyValue: 0,
            order: $payment->order,
            payment: $payment,
            notes: 'Earned from payment '.$this->paymentNumber($payment),
        );
    }

    public function redeemForOrder(Customer $customer, Order $order, int $points, ?Payment $payment = null): LoyaltyTransaction
    {
        $points = max($points, 0);

        if ($points <= 0) {
            throw new \InvalidArgumentException('Redeemed points must be greater than zero.');
        }

        $customer->refresh();

        if ((int) $customer->loyalty_points < $points) {
            throw new \InvalidArgumentException('Customer does not have enough loyalty points.');
        }

        return $this->addTransaction(
            customer: $customer,
            type: LoyaltyTransaction::TYPE_REDEEMED,
            points: -$points,
            moneyValue: $this->moneyValueForPoints($points, $order->branch_id),
            order: $order,
            payment: $payment,
            notes: 'Redeemed on order '.$order->order_number,
        );
    }

    public function adjustPoints(Customer $customer, int $points, string $reason): LoyaltyTransaction
    {
        if ($points === 0) {
            throw new \InvalidArgumentException('Loyalty adjustment points cannot be zero.');
        }

        $customer->refresh();

        if ((int) $customer->loyalty_points + $points < 0) {
            throw new \InvalidArgumentException('Adjustment cannot reduce loyalty points below zero.');
        }

        return $this->addTransaction(
            customer: $customer,
            type: LoyaltyTransaction::TYPE_ADJUSTED,
            points: $points,
            moneyValue: 0,
            order: null,
            payment: null,
            notes: $reason,
        );
    }

    public function pointsForAmount(float $amount, ?int $branchId = null): int
    {
        $spend = max((float) $this->setting('loyalty_spend_per_point', $branchId, '10'), 0.01);
        $points = (int) floor(max($amount, 0) / $spend);

        return $this->loyaltyEnabled($branchId) ? $points : 0;
    }

    public function moneyValueForPoints(int $points, ?int $branchId = null): float
    {
        $value = max((float) $this->setting('loyalty_value_per_point', $branchId, '0.10'), 0);

        return round(max($points, 0) * $value, 2);
    }

    public function maxRedeemablePoints(Customer $customer, float $balance, ?int $branchId = null): int
    {
        if (! $this->loyaltyEnabled($branchId) || $balance <= 0) {
            return 0;
        }

        $minimum = $this->minimumRedemptionPoints($branchId);
        $value = max((float) $this->setting('loyalty_value_per_point', $branchId, '0.10'), 0);

        if ($value <= 0 || (int) $customer->loyalty_points < $minimum) {
            return 0;
        }

        $balanceLimited = (int) floor($balance / $value);

        return max(min((int) $customer->loyalty_points, $balanceLimited), 0);
    }

    public function minimumRedemptionPoints(?int $branchId = null): int
    {
        return max((int) $this->setting('loyalty_min_redeem_points', $branchId, '10'), 1);
    }

    public function loyaltyEnabled(?int $branchId = null): bool
    {
        return (bool) (int) $this->setting('loyalty_enabled', $branchId, '1');
    }

    private function addTransaction(
        Customer $customer,
        string $type,
        int $points,
        float $moneyValue,
        ?Order $order,
        ?Payment $payment,
        ?string $notes,
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($customer, $type, $points, $moneyValue, $order, $payment, $notes): LoyaltyTransaction {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $balance = max((int) $customer->loyalty_points + $points, 0);

            $customer->update(['loyalty_points' => $balance]);

            $transaction = LoyaltyTransaction::create([
                'branch_id' => $customer->branch_id ?? $order?->branch_id ?? $payment?->branch_id,
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
                'payment_id' => $payment?->id,
                'created_by' => auth()->id(),
                'type' => $type,
                'points' => $points,
                'balance_after' => $balance,
                'money_value' => $moneyValue,
                'notes' => $notes,
            ]);

            ActivityLog::record('loyalty.'.$type, $transaction, [
                'customer' => $customer->name,
                'points' => $points,
                'balance_after' => $balance,
                'money_value' => $moneyValue,
            ]);

            return $transaction;
        });
    }

    private function setting(string $key, ?int $branchId, string $default): string
    {
        return (string) (Setting::query()
            ->where('key', $key)
            ->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->orderByRaw('case when branch_id is null then 1 else 0 end')
            ->value('value') ?? $default);
    }

    private function paymentNumber(Payment $payment): string
    {
        return (string) ($payment->payment_number ?? $payment->receipt_number ?? $payment->receipt_no ?? '#'.$payment->id);
    }
}
