<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Support\LoyaltyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PaymentsManager extends Component
{
    public const METHODS = [
        'cash' => 'Cash',
        'mobile_money' => 'Mobile Money',
        'bank_transfer' => 'Bank Transfer',
        'pos_card' => 'POS/Card',
        'cheque' => 'Cheque',
    ];

    public const STATUSES = [
        'unpaid' => 'Unpaid',
        'part_paid' => 'Part Paid',
        'paid' => 'Paid',
    ];

    public string $search = '';
    public string $paymentStatusFilter = 'all';
    public ?int $selectedOrderId = null;
    public string $amount = '';
    public string $payment_method = 'cash';
    public string $reference = '';
    public string $notes = '';
    public string $redeemPoints = '0';

    public function mount(): void
    {
        if (request()->filled('order')) {
            $this->selectOrder((int) request('order'));
        }
    }

    public function selectOrder(int $orderId): void
    {
        $order = $this->orderQuery()->findOrFail($orderId);
        $this->selectedOrderId = $order->id;
        $this->amount = number_format(max((float) $order->balance, 0), 2, '.', '');
        $this->redeemPoints = '0';
        $this->resetValidation();
    }

    public function recordPayment(LoyaltyService $loyalty): void
    {
        $order = $this->selectedOrderId ? $this->orderQuery()->with('customer')->findOrFail($this->selectedOrderId) : null;
        $balance = $order ? $this->currentBalance($order) : 0;

        if (! $order || $balance <= 0) {
            session()->flash('error', 'This order is already fully paid.');
            return;
        }

        $validated = $this->validate([
            'selectedOrderId' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:'.$balance],
            'payment_method' => ['required', Rule::in(array_keys(self::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'redeemPoints' => ['nullable', 'integer', 'min:0'],
        ]);

        $redeemPoints = (int) ($validated['redeemPoints'] ?? 0);
        $redeemValue = 0.0;

        if ($redeemPoints > 0) {
            $maxRedeemable = $loyalty->maxRedeemablePoints($order->customer, $balance, $order->branch_id);

            if ($redeemPoints < $loyalty->minimumRedemptionPoints($order->branch_id)) {
                $this->addError('redeemPoints', 'Minimum redemption is '.$loyalty->minimumRedemptionPoints($order->branch_id).' points.');
                return;
            }

            if ($redeemPoints > $maxRedeemable) {
                $this->addError('redeemPoints', 'Customer can redeem up to '.$maxRedeemable.' points for this order.');
                return;
            }

            $redeemValue = min($loyalty->moneyValueForPoints($redeemPoints, $order->branch_id), $balance);
        }

        if (round((float) $validated['amount'] + $redeemValue, 2) <= 0) {
            $this->addError('amount', 'Enter a cash amount or redeem loyalty points.');
            return;
        }

        if (round((float) $validated['amount'] + $redeemValue, 2) > $balance) {
            $this->addError('amount', 'Cash plus loyalty credit cannot be greater than the current balance.');
            return;
        }

        $payment = DB::transaction(function () use ($order, $validated, $loyalty, $redeemPoints, $redeemValue): ?Payment {
            $loyaltyPayment = null;

            if ($redeemPoints > 0 && $redeemValue > 0) {
                $loyaltyPayment = $this->createPayment($order, [
                    'amount' => $redeemValue,
                    'payment_method' => 'loyalty_credit',
                    'reference' => 'LOYALTY-'.$redeemPoints.'PTS',
                    'notes' => 'Redeemed '.$redeemPoints.' loyalty points.',
                ]);

                $loyalty->redeemForOrder($order->customer, $order, $redeemPoints, $loyaltyPayment);
            }

            if ((float) $validated['amount'] <= 0) {
                $this->syncOrderPaymentSummary($order->fresh());
                return $loyaltyPayment;
            }

            $payment = $this->createPayment($order, $validated);
            $this->syncOrderPaymentSummary($order->fresh());
            $loyalty->earnForPayment($payment->fresh(['customer', 'order']));

            return $payment;
        });

        if (! $payment) {
            $this->resetPaymentForm(keepOrder: true);
            session()->flash('status', 'Loyalty credit redeemed.');
            return;
        }

        $freshOrder = $order->fresh(['customer']);

        ActivityLog::record('payment.received', $payment, [
            'order_number' => $freshOrder->order_number,
            'customer' => $freshOrder->customer?->name,
        ], [], [
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method ?? $payment->method,
            'reference' => $payment->reference ?? null,
            'amount_paid' => $freshOrder->amount_paid,
            'balance' => $freshOrder->balance,
            'payment_status' => $freshOrder->payment_status,
        ]);

        $this->resetPaymentForm(keepOrder: true);
        session()->flash('status', $redeemPoints > 0 ? 'Payment recorded and loyalty credit redeemed.' : 'Payment recorded.');
    }

    private function createPayment(Order $order, array $validated): Payment
    {
            $paymentNumber = $this->nextPaymentNumber();
            $data = [
                'branch_id' => auth()->user()?->branch_id,
                'customer_id' => $order->customer_id,
                'amount' => round((float) $validated['amount'], 2),
                'received_by' => auth()->id(),
            ];

            if (Schema::hasColumn('payments', 'reference')) {
                $data['reference'] = $validated['reference'] ?: null;
            }

            if (Schema::hasColumn('payments', 'notes')) {
                $data['notes'] = $validated['notes'] ?: null;
            }

            foreach (['payment_number', 'receipt_number', 'receipt_no'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $data[$column] = $paymentNumber;
                }
            }

            if (Schema::hasColumn('payments', 'order_id')) {
                $data['order_id'] = $order->id;
            }

            if (Schema::hasColumn('payments', 'payment_method')) {
                $data['payment_method'] = $validated['payment_method'];
            }

            if (Schema::hasColumn('payments', 'method')) {
                $data['method'] = $validated['payment_method'];
            }

            if (Schema::hasColumn('payments', 'status')) {
                $data['status'] = 'settled';
            }

            if (Schema::hasColumn('payments', 'paid_at')) {
                $data['paid_at'] = now();
            }

            return Payment::create($data);
    }

    public function resetPaymentForm(bool $keepOrder = false): void
    {
        $orderId = $this->selectedOrderId;
        $this->amount = '';
        $this->payment_method = 'cash';
        $this->reference = '';
        $this->notes = '';
        $this->redeemPoints = '0';
        $this->resetValidation();

        if ($keepOrder && $orderId) {
            $this->selectOrder($orderId);
        }
    }

    public function render()
    {
        $orders = $this->orderQuery()
            ->with(['customer.loyaltyTransactions', 'payments.receiver'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('order_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->paymentStatusFilter !== 'all', fn (Builder $query) => $query->where('payment_status', $this->paymentStatusFilter))
            ->latest()
            ->limit(30)
            ->get()
            ->each(fn (Order $order) => $this->syncComputedSummary($order));

        $selectedOrder = $this->selectedOrderId
            ? $orders->firstWhere('id', $this->selectedOrderId) ?? $this->orderQuery()->with(['customer.loyaltyTransactions', 'payments.receiver'])->find($this->selectedOrderId)
            : $orders->first();

        if (! $this->selectedOrderId && $selectedOrder) {
            $this->selectedOrderId = $selectedOrder->id;
        }

        if ($selectedOrder) {
            $this->syncComputedSummary($selectedOrder);
        }

        return view('livewire.payments-manager', [
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'methods' => self::METHODS,
            'statuses' => self::STATUSES,
            'nextPaymentNumber' => $this->nextPaymentNumber(),
            'loyaltyMaxRedeemable' => $selectedOrder?->customer ? app(LoyaltyService::class)->maxRedeemablePoints($selectedOrder->customer, (float) $selectedOrder->balance, $selectedOrder->branch_id) : 0,
            'loyaltyRedeemValue' => $selectedOrder ? app(LoyaltyService::class)->moneyValueForPoints((int) $this->redeemPoints, $selectedOrder->branch_id) : 0,
            'loyaltyMinimumRedeem' => $selectedOrder ? app(LoyaltyService::class)->minimumRedemptionPoints($selectedOrder->branch_id) : 0,
        ])->layout('layouts.app', ['title' => 'Payments']);
    }

    private function orderQuery(): Builder
    {
        return Order::query()->where('branch_id', auth()->user()?->branch_id);
    }

    private function syncComputedSummary(Order $order): void
    {
        $order->amount_paid = $this->paidAmount($order);
        $order->total_amount = (float) ($order->total_amount ?: $order->total);
        $order->balance = max((float) $order->total_amount - (float) $order->amount_paid, 0);
        $order->payment_status = $this->statusFor((float) $order->total_amount, (float) $order->amount_paid);
    }

    private function syncOrderPaymentSummary(Order $order): void
    {
        $total = (float) ($order->total_amount ?: $order->total);
        $paid = $this->paidAmount($order);
        $balance = max($total - $paid, 0);

        $order->update([
            'total_amount' => $total,
            'amount_paid' => $paid,
            'balance' => $balance,
            'payment_status' => $this->statusFor($total, $paid),
        ]);
    }

    private function paidAmount(Order $order): float
    {
        return round((float) Payment::query()->where('order_id', $order->id)->sum('amount'), 2);
    }

    private function currentBalance(Order $order): float
    {
        $total = (float) ($order->total_amount ?: $order->total);
        return max(round($total - $this->paidAmount($order), 2), 0);
    }

    private function statusFor(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'part_paid';
    }

    private function nextPaymentNumber(): string
    {
        $prefix = 'JW-PAY-'.now()->format('Ymd').'-';
        $count = Payment::withoutGlobalScopes()
            ->where(function (Builder $query) use ($prefix) {
                if (Schema::hasColumn('payments', 'payment_number')) {
                    $query->orWhere('payment_number', 'like', $prefix.'%');
                }

                if (Schema::hasColumn('payments', 'receipt_number')) {
                    $query->orWhere('receipt_number', 'like', $prefix.'%');
                }

                if (Schema::hasColumn('payments', 'receipt_no')) {
                    $query->orWhere('receipt_no', 'like', $prefix.'%');
                }
            })
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
