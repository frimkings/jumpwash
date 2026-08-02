@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-8">
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="stat-card">
            <span>Open Orders</span>
            <strong>{{ number_format($metrics['open_orders']) }}</strong>
            <p>Pending items across the floor and production queue.</p>
        </div>
        <div class="stat-card">
            <span>Orders Today</span>
            <strong>{{ number_format($metrics['orders_today']) }}</strong>
            <p>Orders received on the active branch today.</p>
        </div>
        <div class="stat-card">
            <span>Revenue This Month</span>
            <strong>{{ number_format($metrics['revenue_this_month'], 2) }}</strong>
            <p>Completed payments captured on this LAN instance.</p>
        </div>
        <div class="stat-card">
            <span>Customers</span>
            <strong>{{ number_format($metrics['active_customers']) }}</strong>
            <p>Managed customer records ready for pickup and loyalty.</p>
        </div>
        <div class="stat-card">
            <span>Subscriptions</span>
            <strong>{{ number_format($metrics['active_subscriptions']) }}</strong>
            <p>Active customer plans and allowances.</p>
        </div>
        <div class="stat-card">
            <span>Deliveries</span>
            <strong>{{ number_format($metrics['pending_deliveries']) }}</strong>
            <p>Scheduled or dispatched pickups and deliveries.</p>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
        <div class="card-surface p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-cyan-300">Operations snapshot</p>
                    <h3 class="mt-2 text-xl font-semibold text-white">Recent orders</h3>
                </div>
                <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs text-emerald-200">LAN online</span>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($recentOrders as $order)
                    <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-slate-400">{{ $order->order_no }}</p>
                                <p class="mt-1 font-medium text-white">{{ $order->customer?->name ?? 'Walk-in customer' }}</p>
                            </div>
                            <div class="text-right text-sm text-slate-300">
                                <p>{{ ucfirst($order->status) }}</p>
                                <p>{{ optional($order->received_at)->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 bg-slate-900/40 p-6 text-sm text-slate-400">
                        No orders yet. Seed data or capture the first walk-in order to populate the production view.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="card-surface p-6">
                <p class="text-sm uppercase tracking-[0.3em] text-cyan-300">Branch</p>
                <h3 class="mt-2 text-xl font-semibold text-white">{{ $branch?->name ?? 'Primary branch' }}</h3>
                <p class="mt-3 text-sm leading-6 text-slate-300">{{ $branch?->address ?? 'No branch address configured yet.' }}</p>
            </div>

            <div class="card-surface p-6">
                <p class="text-sm uppercase tracking-[0.3em] text-cyan-300">System readiness</p>
                <div class="mt-4 space-y-3">
                    @foreach ($activityFeed as $item)
                        <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-4">
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">{{ $item['label'] }}</p>
                            <p class="mt-2 text-sm text-white">{{ $item['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
@endsection