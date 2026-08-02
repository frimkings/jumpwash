<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportBuilder
{
    public const TYPES = [
        'sales' => 'Sales Report',
        'customers' => 'Customer Report',
        'services' => 'Service Report',
        'staff' => 'Staff Report',
        'deliveries' => 'Delivery Report',
        'subscriptions' => 'Subscription Report',
        'payments' => 'Payment Report',
        'outstanding' => 'Outstanding Balance Report',
    ];

    public const PERIODS = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'custom' => 'Custom Range',
    ];

    public function build(string $type, string $period, ?string $startDate, ?string $endDate, ?int $branchId): array
    {
        [$start, $end] = $this->range($period, $startDate, $endDate);

        return match ($type) {
            'customers' => $this->customers($start, $end, $branchId),
            'services' => $this->services($start, $end, $branchId),
            'staff' => $this->staff($start, $end, $branchId),
            'deliveries' => $this->deliveries($start, $end, $branchId),
            'subscriptions' => $this->subscriptions($start, $end, $branchId),
            'payments' => $this->payments($start, $end, $branchId),
            'outstanding' => $this->outstanding($start, $end, $branchId),
            default => $this->sales($start, $end, $branchId),
        };
    }

    private function sales(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = $this->orders($branchId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('order_number, status, payment_status, subtotal, total, amount_paid, balance, created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                $row->order_number,
                ucfirst(str_replace('_', ' ', $row->status)),
                ucfirst(str_replace('_', ' ', $row->payment_status)),
                (float) $row->subtotal,
                (float) $row->total,
                (float) ($row->amount_paid ?? 0),
                (float) ($row->balance ?? max(((float) $row->total) - ((float) ($row->amount_paid ?? 0)), 0)),
                Carbon::parse($row->created_at)->format('Y-m-d H:i'),
            ]);

        return $this->payload('Sales Report', ['Order', 'Order Status', 'Payment Status', 'Subtotal', 'Total', 'Paid', 'Balance', 'Date'], $rows, $start, $end);
    }

    private function customers(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $codeColumn = Schema::hasColumn('customers', 'customer_code') ? 'customer_code' : 'code';
        $rows = DB::table('customers')
            ->when($branchId && Schema::hasColumn('customers', 'branch_id'), fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                $row->{$codeColumn} ?? 'N/A',
                $row->name,
                $row->phone ?? '',
                $row->email ?? '',
                isset($row->is_active) && $row->is_active ? 'Active' : 'Inactive',
                Carbon::parse($row->created_at)->format('Y-m-d'),
            ]);

        return $this->payload('Customer Report', ['Customer No', 'Name', 'Phone', 'Email', 'Status', 'Registered'], $rows, $start, $end);
    }

    private function services(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->when($branchId && Schema::hasColumn('orders', 'branch_id'), fn ($query) => $query->where('orders.branch_id', $branchId))
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('order_items.item_name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total) as sales')
            ->groupBy('order_items.item_name')
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($row) => [$row->item_name, (float) $row->quantity, (float) $row->sales]);

        return $this->payload('Service Report', ['Service/Product', 'Quantity', 'Sales'], $rows, $start, $end);
    }

    private function staff(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = DB::table('users')
            ->leftJoin('pickup_delivery_tasks', 'pickup_delivery_tasks.assigned_to', '=', 'users.id')
            ->when($branchId && Schema::hasColumn('users', 'branch_id'), fn ($query) => $query->where('users.branch_id', $branchId))
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('pickup_delivery_tasks.scheduled_at', [$start, $end])
                    ->orWhereNull('pickup_delivery_tasks.id');
            })
            ->selectRaw("users.name, users.email, COUNT(pickup_delivery_tasks.id) as assignments, SUM(CASE WHEN pickup_delivery_tasks.status IN ('completed','delivered') THEN 1 ELSE 0 END) as completed")
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row) => [$row->name, $row->email, (int) $row->assignments, (int) $row->completed]);

        return $this->payload('Staff Report', ['Staff', 'Email', 'Assignments', 'Completed'], $rows, $start, $end);
    }

    private function deliveries(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = DB::table('pickup_delivery_tasks')
            ->leftJoin('customers', 'customers.id', '=', 'pickup_delivery_tasks.customer_id')
            ->leftJoin('users', 'users.id', '=', 'pickup_delivery_tasks.assigned_to')
            ->when($branchId && Schema::hasColumn('pickup_delivery_tasks', 'branch_id'), fn ($query) => $query->where('pickup_delivery_tasks.branch_id', $branchId))
            ->whereIn('pickup_delivery_tasks.type', ['door_delivery', 'self_collect'])
            ->whereBetween('pickup_delivery_tasks.scheduled_at', [$start, $end])
            ->selectRaw('pickup_delivery_tasks.type, pickup_delivery_tasks.status, pickup_delivery_tasks.scheduled_at, customers.name as customer, users.name as staff, pickup_delivery_tasks.address, pickup_delivery_tasks.delivery_signature_path')
            ->orderByDesc('pickup_delivery_tasks.scheduled_at')
            ->get()
            ->map(fn ($row) => [
                ucfirst(str_replace('_', ' ', $row->type)),
                ucfirst(str_replace('_', ' ', $row->status)),
                $row->customer,
                $row->staff ?? 'Unassigned',
                $row->address,
                $row->delivery_signature_path ? 'Signed' : 'Pending',
                Carbon::parse($row->scheduled_at)->format('Y-m-d H:i'),
            ]);

        return $this->payload('Delivery Report', ['Type', 'Status', 'Customer', 'Staff', 'Address', 'Signature', 'Scheduled'], $rows, $start, $end);
    }

    private function subscriptions(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $planColumn = Schema::hasColumn('subscription_plans', 'name') ? 'subscription_plans.name' : 'subscription_plans.id';
        $remainingSelect = Schema::hasColumn('customer_subscriptions', 'washes_remaining')
            ? 'customer_subscriptions.washes_remaining'
            : 'NULL';
        $limitColumns = collect(['usage_limit', 'wash_limit'])
            ->filter(fn (string $column): bool => Schema::hasColumn('subscription_plans', $column))
            ->map(fn (string $column): string => 'subscription_plans.'.$column)
            ->push('0')
            ->implode(', ');
        $amountColumns = collect(['amount', 'price'])
            ->filter(fn (string $column): bool => Schema::hasColumn('subscription_plans', $column))
            ->map(fn (string $column): string => 'subscription_plans.'.$column)
            ->push('0')
            ->implode(', ');
        $limitSelect = "COALESCE({$limitColumns})";
        $amountSelect = "COALESCE({$amountColumns})";
        $rows = DB::table('customer_subscriptions')
            ->leftJoin('customers', 'customers.id', '=', 'customer_subscriptions.customer_id')
            ->leftJoin('subscription_plans', 'subscription_plans.id', '=', 'customer_subscriptions.subscription_plan_id')
            ->when($branchId && Schema::hasColumn('customer_subscriptions', 'branch_id'), fn ($query) => $query->where('customer_subscriptions.branch_id', $branchId))
            ->when($branchId && ! Schema::hasColumn('customer_subscriptions', 'branch_id') && Schema::hasColumn('customers', 'branch_id'), fn ($query) => $query->where('customers.branch_id', $branchId))
            ->whereDate('customer_subscriptions.ends_at', '>=', $start->toDateString())
            ->whereDate('customer_subscriptions.ends_at', '<=', $end->toDateString())
            ->selectRaw("customers.name as customer, {$planColumn} as plan_name, customer_subscriptions.status, customer_subscriptions.starts_at, customer_subscriptions.ends_at, customer_subscriptions.allowance, {$amountSelect} as amount, {$limitSelect} as usage_limit, {$remainingSelect} as remaining_uses, customer_subscriptions.auto_renew")
            ->orderBy('customer_subscriptions.ends_at')
            ->get()
            ->map(function ($row) {
                $allowance = json_decode((string) $row->allowance, true) ?: [];
                $limit = (int) ($row->usage_limit ?: ($allowance['limit'] ?? 0));
                $remaining = (int) ($row->remaining_uses ?? ($allowance['remaining'] ?? $limit));

                return [
                    $row->customer,
                    $row->plan_name,
                    ucfirst((string) $row->status),
                    (float) $row->amount,
                    $limit,
                    max($limit - $remaining, 0),
                    $remaining,
                    $row->auto_renew ? 'Yes' : 'No',
                    $row->starts_at,
                    $row->ends_at,
                ];
            });

        return $this->payload('Subscription Report', ['Customer', 'Package', 'Status', 'Amount', 'Limit', 'Used', 'Remaining', 'Auto Renew', 'Start', 'Expiry'], $rows, $start, $end);
    }

    private function payments(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $numberColumn = Schema::hasColumn('payments', 'payment_number') ? 'payment_number' : (Schema::hasColumn('payments', 'receipt_number') ? 'receipt_number' : 'receipt_no');
        $methodColumn = Schema::hasColumn('payments', 'payment_method') ? 'payment_method' : 'method';
        $referenceSelect = Schema::hasColumn('payments', 'reference') ? 'payments.reference' : "''";
        $rows = DB::table('payments')
            ->leftJoin('orders', 'orders.id', '=', 'payments.order_id')
            ->leftJoin('customers', 'customers.id', '=', 'payments.customer_id')
            ->when($branchId && Schema::hasColumn('payments', 'branch_id'), fn ($query) => $query->where('payments.branch_id', $branchId))
            ->whereBetween('payments.created_at', [$start, $end])
            ->selectRaw("payments.{$numberColumn} as number, orders.order_number, customers.name as customer, payments.{$methodColumn} as method, payments.amount, {$referenceSelect} as reference, payments.created_at")
            ->orderByDesc('payments.created_at')
            ->get()
            ->map(fn ($row) => [$row->number, $row->order_number, $row->customer, ucfirst(str_replace('_', ' ', (string) $row->method)), (float) $row->amount, $row->reference, Carbon::parse($row->created_at)->format('Y-m-d H:i')]);

        return $this->payload('Payment Report', ['Payment No', 'Order', 'Customer', 'Method', 'Amount', 'Reference', 'Date'], $rows, $start, $end);
    }

    private function outstanding(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = $this->orders($branchId)
            ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where(function ($query) {
                $query->where('orders.balance', '>', 0)->orWhereIn('orders.payment_status', ['unpaid', 'part_paid', 'partial']);
            })
            ->selectRaw('orders.order_number, customers.name as customer, orders.total_amount, orders.amount_paid, orders.balance, orders.payment_status, orders.created_at')
            ->orderByDesc('orders.balance')
            ->get()
            ->map(fn ($row) => [$row->order_number, $row->customer, (float) $row->total_amount, (float) $row->amount_paid, (float) $row->balance, ucfirst(str_replace('_', ' ', $row->payment_status)), Carbon::parse($row->created_at)->format('Y-m-d')]);

        return $this->payload('Outstanding Balance Report', ['Order', 'Customer', 'Total', 'Paid', 'Balance', 'Status', 'Date'], $rows, $start, $end);
    }

    private function orders(?int $branchId)
    {
        return DB::table('orders')
            ->when($branchId && Schema::hasColumn('orders', 'branch_id'), fn ($query) => $query->where('orders.branch_id', $branchId));
    }

    private function range(string $period, ?string $startDate, ?string $endDate): array
    {
        $now = now();

        return match ($period) {
            'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'yearly' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => [Carbon::parse($startDate ?: today())->startOfDay(), Carbon::parse($endDate ?: today())->endOfDay()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function payload(string $title, array $headings, Collection $rows, Carbon $start, Carbon $end): array
    {
        $numericTotals = [];
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                if (is_numeric($value)) {
                    $numericTotals[$index] = ($numericTotals[$index] ?? 0) + (float) $value;
                }
            }
        }

        return [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows->values()->all(),
            'start' => $start,
            'end' => $end,
            'summary' => [
                'records' => $rows->count(),
                'totals' => $numericTotals,
            ],
        ];
    }
}
