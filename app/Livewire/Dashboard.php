<?php

namespace App\Livewire;

use App\Models\CustomerSubscription;
use App\Models\GarmentTag;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PickupDeliveryTask;
use App\Support\PerformanceCache;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $branchId = auth()->user()?->branch_id;
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        $orders = Order::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));

        $payments = Payment::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));

        $monthlyLabels = collect(CarbonPeriod::create($startOfYear, '1 month', $now->copy()->endOfMonth()))
            ->map(fn ($date) => $date->format('M'));

        $dashboard = Cache::remember(
            PerformanceCache::key('dashboard:'.$now->format('YmdHi')),
            PerformanceCache::DASHBOARD_TTL,
            function () use ($branchId, $orders, $payments, $startOfMonth, $startOfYear, $monthlyLabels) {
                $ordersByMonth = $this->monthlySeries($monthlyLabels, fn ($monthStart, $monthEnd) => (clone $orders)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count());

                $revenueByMonth = $this->monthlySeries($monthlyLabels, fn ($monthStart, $monthEnd) => (float) (clone $payments)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount'));

                $serviceBreakdown = OrderItem::query()
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->when($branchId, fn ($query) => $query->where('orders.branch_id', $branchId))
                    ->where('orders.created_at', '>=', $startOfMonth)
                    ->selectRaw('order_items.item_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.line_total) as total_sales')
                    ->groupBy('order_items.item_name')
                    ->orderByDesc('total_sales')
                    ->limit(6)
                    ->get()
                    ->map(fn ($item) => [
                        'label' => $item->item_name,
                        'value' => (float) $item->total_sales,
                        'meta' => number_format((float) $item->total_quantity, 2).' qty',
                    ]);

                $topCustomers = Customer::query()
                    ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                    ->withSum(['orders as total_spend' => fn ($query) => $query->where('created_at', '>=', $startOfYear)], 'total')
                    ->withCount(['orders as orders_count' => fn ($query) => $query->where('created_at', '>=', $startOfYear)])
                    ->orderByDesc('total_spend')
                    ->limit(5)
                    ->get()
                    ->map(fn (Customer $customer) => [
                        'label' => $customer->name,
                        'value' => (float) ($customer->total_spend ?? 0),
                        'meta' => ($customer->orders_count ?? 0).' orders',
                    ]);

                $topServices = $this->topOrderItemSales($branchId, $startOfYear, 'laundry_service_id');
                $topProducts = $this->topOrderItemSales($branchId, $startOfYear, 'product_id');

                return [
                    'stats' => [
                        'new_orders_today' => (clone $orders)->where('created_at', '>=', today())->count(),
                        'pending_orders' => (clone $orders)->whereIn('status', ['received', 'pending'])->count(),
                        'in_process_orders' => (clone $orders)->whereIn('status', ['processing', 'in_process', 'washing', 'drying'])->count(),
                        'ready_for_delivery' => (clone $orders)->whereIn('status', ['ready', 'ready_for_delivery'])->count(),
                        'door_deliveries_today' => PickupDeliveryTask::query()
                            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                            ->where('type', 'door_delivery')
                            ->where('scheduled_at', '>=', today())
                            ->where('scheduled_at', '<', today()->addDay())
                            ->count(),
                        'pickup_requests' => PickupDeliveryTask::query()
                            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                            ->where('type', 'door_pickup')
                            ->whereIn('status', ['scheduled', 'assigned', 'requested'])
                            ->count(),
                        'expiring_subscriptions' => CustomerSubscription::query()
                            ->where('status', 'active')
                            ->whereBetween('ends_at', [today(), today()->addDays(7)])
                            ->whereHas('customer', fn (Builder $query) => $query->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId)))
                            ->count(),
                        'outstanding_balances' => (clone $orders)
                            ->whereIn('payment_status', ['unpaid', 'partial', 'part_paid'])
                            ->sum(DB::raw('COALESCE(balance, total_amount, total)')),
                    ],
                    'analytics' => [
                        'monthly_orders' => (clone $orders)->where('created_at', '>=', $startOfMonth)->count(),
                        'yearly_orders' => (clone $orders)->where('created_at', '>=', $startOfYear)->count(),
                        'monthly_revenue' => (clone $payments)->where('created_at', '>=', $startOfMonth)->sum('amount'),
                        'monthly_subscription_revenue' => Payment::query()
                            ->join('orders', 'orders.id', '=', 'payments.order_id')
                            ->when($branchId, fn ($query) => $query->where('payments.branch_id', $branchId))
                            ->where('payments.created_at', '>=', $startOfMonth)
                            ->whereIn('orders.billing_source', ['subscription', 'plan'])
                            ->sum('payments.amount'),
                    ],
                    'charts' => [
                        'monthly_order_labels' => $monthlyLabels,
                        'monthly_orders' => $ordersByMonth,
                        'monthly_revenue' => $revenueByMonth,
                        'service_breakdown' => $serviceBreakdown,
                        'top_customers' => $topCustomers,
                        'top_services' => $topServices,
                        'top_products' => $topProducts,
                    ],
                ];
            },
        );

        return view('livewire.dashboard', [
            'stats' => $dashboard['stats'],
            'analytics' => $dashboard['analytics'],
            'charts' => $dashboard['charts'],
            'roleQueues' => $this->roleQueues($branchId),
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }

    private function roleQueues(?int $branchId): array
    {
        $user = auth()->user();

        return [
            'admin' => $user?->can('reports.view') || $user?->can('settings.manage') ? [
                'label' => 'Admin Focus',
                'items' => [
                    'Outstanding balances' => Order::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->where('balance', '>', 0)->count(),
                    'Reports ready' => 'Sales, services, customers',
                    'Backups' => 'Use local backup before end of day',
                ],
            ] : null,
            'reception' => $user?->can('orders.manage') || $user?->can('payments.manage') ? [
                'label' => 'Reception Focus',
                'items' => [
                    'New orders today' => Order::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->whereDate('created_at', today())->count(),
                    'Unpaid orders' => Order::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->whereIn('payment_status', ['unpaid', 'part_paid', 'partial'])->count(),
                    'Ready for receipt print' => Order::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->whereIn('status', ['received', 'ready'])->count(),
                ],
            ] : null,
            'laundry' => $user?->can('garments.scan') ? [
                'label' => 'Laundry Focus',
                'items' => [
                    'Garments in process' => GarmentTag::query()->whereHas('order', fn (Builder $query) => $query->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId)))->whereIn('status', ['received', 'washing', 'drying', 'ironing', 'packaging'])->count(),
                    'Exceptions' => GarmentTag::query()->whereHas('order', fn (Builder $query) => $query->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId)))->whereIn('status', ['missing', 'damaged', 'rewash'])->count(),
                    'Ready tags' => GarmentTag::query()->whereHas('order', fn (Builder $query) => $query->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId)))->where('status', 'ready')->count(),
                ],
            ] : null,
            'rider' => $user?->can('deliveries.manage') || $user?->can('deliveries.assigned.view') ? [
                'label' => 'Rider Focus',
                'items' => [
                    'Pickups due today' => PickupDeliveryTask::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->whereIn('type', ['door_pickup'])->whereDate('scheduled_at', today())->whereNotIn('status', ['completed', 'cancelled'])->count(),
                    'Deliveries due today' => PickupDeliveryTask::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->whereIn('type', ['door_delivery'])->whereDate('scheduled_at', today())->whereNotIn('status', ['delivered', 'failed'])->count(),
                    'Out for delivery' => PickupDeliveryTask::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->where('status', 'out_for_delivery')->count(),
                ],
            ] : null,
        ];
    }

    private function monthlySeries(Collection $labels, callable $resolver): Collection
    {
        return $labels->values()->map(function (string $label, int $index) use ($resolver) {
            $monthStart = now()->copy()->startOfYear()->addMonths($index)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            return [
                'label' => $label,
                'value' => (float) $resolver($monthStart, $monthEnd),
            ];
        });
    }

    private function topOrderItemSales(?int $branchId, $startOfYear, string $column): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull("order_items.{$column}")
            ->when($branchId, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->where('orders.created_at', '>=', $startOfYear)
            ->selectRaw('order_items.item_name, SUM(order_items.line_total) as total_sales')
            ->groupBy('order_items.item_name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get()
            ->map(fn ($item) => ['label' => $item->item_name, 'value' => (float) $item->total_sales]);
    }
}
