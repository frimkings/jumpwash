<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Delivery;
use App\Models\LaundryOrder;
use App\Models\Payment;
use App\Support\BranchContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BranchContext $branchContext): View
    {
        $branch = $branchContext->branch() ?? Branch::query()->where('is_primary', true)->first();

        $metrics = [
            'open_orders' => LaundryOrder::query()->whereIn('status', ['received', 'processing', 'ready'])->count(),
            'orders_today' => LaundryOrder::query()->whereDate('received_at', today())->count(),
            'active_customers' => Customer::query()->count(),
            'active_subscriptions' => CustomerSubscription::query()->where('status', 'active')->count(),
            'pending_deliveries' => Delivery::query()->whereIn('status', ['scheduled', 'dispatched'])->count(),
            'revenue_this_month' => Payment::query()->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
        ];

        $recentOrders = LaundryOrder::query()
            ->with(['customer', 'branch'])
            ->latest('received_at')
            ->take(5)
            ->get();

        $activityFeed = [
            ['label' => 'LAN sync', 'value' => 'Local storage only', 'tone' => 'emerald'],
            ['label' => 'Barcode tags', 'value' => 'Ready for thermal print', 'tone' => 'sky'],
            ['label' => 'Queue worker', 'value' => 'On-box jobs for reports and receipts', 'tone' => 'amber'],
            ['label' => 'Branch scope', 'value' => $branch?->name ?? 'Primary branch', 'tone' => 'slate'],
        ];

        return view('dashboard', compact('branch', 'metrics', 'recentOrders', 'activityFeed'));
    }
}