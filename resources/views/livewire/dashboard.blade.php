<div class="dashboard-page">
    <section class="dashboard-hero">
        <div>
            <h2><span class="dashboard-title-icon">JW</span> Admin Dashboard</h2>
            <p class="dashboard-eyebrow">Overview — {{ now()->format('l, F d Y') }}</p>
        </div>
        <a href="{{ route('reports.index') }}" class="dashboard-report-link">
            <x-ui.nav-icon name="reports" />
            <span>Full Reports</span>
        </a>
    </section>

    @php
        $overviewCards = [
            ['New Orders Today', $stats['new_orders_today'], 'More Info', 'orders', 'blue'],
            ['Pending Orders', $stats['pending_orders'], 'More Info', 'orders', 'green'],
            ['In Process Orders', $stats['in_process_orders'], 'More Info', 'services', 'orange'],
            ['Ready For Delivery', $stats['ready_for_delivery'], 'More Info', 'delivery', 'teal'],
            ['Door Deliveries Today', $stats['door_deliveries_today'], 'More Info', 'pickup', 'green'],
            ['Pickup Requests', $stats['pickup_requests'], 'More Info', 'pickup', 'red'],
            ['Expiring Subscriptions', $stats['expiring_subscriptions'], 'More Info', 'subscriptions', 'amber'],
            ['Outstanding Balances', 'GHS '.number_format($stats['outstanding_balances'], 2), 'Payments', 'payments', 'purple'],
        ];
    @endphp

    <section class="kpi-grid">
        @foreach ($overviewCards as [$label, $value, $footer, $icon, $tone])
            <article class="kpi-card kpi-card--{{ $tone }}">
                <span class="metric-icon">
                    <x-ui.nav-icon :name="$icon" />
                </span>
                <p>{{ $label }}</p>
                <strong>{{ $value }}</strong>
                <a href="{{ route('reports.index') }}">{{ $footer }} <span>›</span></a>
            </article>
        @endforeach
    </section>

    <section class="analytics-grid">
        <article class="analytics-card">
            <p>Monthly Orders</p>
            <strong>{{ number_format($analytics['monthly_orders']) }}</strong>
        </article>
        <article class="analytics-card">
            <p>Yearly Orders</p>
            <strong>{{ number_format($analytics['yearly_orders']) }}</strong>
        </article>
        <article class="analytics-card">
            <p>Monthly Revenue</p>
            <strong>GHS {{ number_format($analytics['monthly_revenue'], 2) }}</strong>
        </article>
        <article class="analytics-card">
            <p>Monthly Subscription Revenue</p>
            <strong>GHS {{ number_format($analytics['monthly_subscription_revenue'], 2) }}</strong>
        </article>
    </section>

    <section class="chart-grid">
        @foreach (array_filter($roleQueues) as $queue)
            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <h3>{{ $queue['label'] }}</h3>
                        <p>Role-specific work queue</p>
                    </div>
                </div>
                <div class="rank-list">
                    @foreach ($queue['items'] as $label => $value)
                        <div>
                            <p>{{ $label }}</p>
                            <strong>{{ is_numeric($value) ? number_format($value) : $value }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>

    @php
        $maxOrders = max(1, $charts['monthly_orders']->max('value'));
        $maxRevenue = max(1, $charts['monthly_revenue']->max('value'));
        $serviceTotal = max(1, $charts['service_breakdown']->sum('value'));
        $pieStops = [];
        $pieStart = 0;
        $pieColors = ['#0891b2', '#16a34a', '#f59e0b', '#ef4444', '#7c3aed', '#475569'];

        foreach ($charts['service_breakdown']->values() as $index => $item) {
            $slice = ($item['value'] / $serviceTotal) * 100;
            $pieStops[] = $pieColors[$index % count($pieColors)].' '.$pieStart.'% '.($pieStart + $slice).'%';
            $pieStart += $slice;
        }

        $pieBackground = count($pieStops) ? implode(', ', $pieStops) : '#e4e4e7 0% 100%';
    @endphp

    <section class="chart-grid">
        <article class="chart-card chart-card--wide">
            <div class="chart-header">
                <div>
                    <h3>Monthly Orders</h3>
                    <p>Orders received by month</p>
                </div>
                <a href="{{ route('reports.index') }}" class="panel-action">Full Report</a>
            </div>
            <div class="line-chart">
                @foreach ($charts['monthly_orders'] as $point)
                    <div class="line-chart__point" style="--height: {{ 12 + (($point['value'] / $maxOrders) * 78) }}%;">
                        <span>{{ $point['value'] }}</span>
                        <small>{{ $point['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="chart-card chart-card--wide">
            <div class="chart-header">
                <div>
                    <h3>Monthly Revenue</h3>
                    <p>Collected revenue (GHS)</p>
                </div>
                <a href="{{ route('reports.index') }}" class="panel-action">Full Report</a>
            </div>
            <div class="bar-chart">
                @foreach ($charts['monthly_revenue'] as $bar)
                    <div class="bar-chart__bar" style="--height: {{ 10 + (($bar['value'] / $maxRevenue) * 82) }}%;">
                        <span>GHS {{ number_format($bar['value'], 0) }}</span>
                        <small>{{ $bar['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <h3>Service Breakdown</h3>
                    <p>Sales by service mix</p>
                </div>
            </div>
            <div class="pie-chart" style="--pie: {{ $pieBackground }}"></div>
            <div class="legend-list">
                @forelse ($charts['service_breakdown'] as $index => $item)
                    <div>
                        <span style="background: {{ $pieColors[$index % count($pieColors)] }}"></span>
                        <p>{{ $item['label'] }}</p>
                        <strong>GHS {{ number_format($item['value'], 2) }}</strong>
                    </div>
                @empty
                    <p class="empty-state">No service sales yet.</p>
                @endforelse
            </div>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <h3>Top Customers</h3>
                    <p>Highest spend this year</p>
                </div>
            </div>
            <div class="rank-list">
                @forelse ($charts['top_customers'] as $customer)
                    <div>
                        <p>{{ $customer['label'] }}</p>
                        <span>{{ $customer['meta'] }}</span>
                        <strong>GHS {{ number_format($customer['value'], 2) }}</strong>
                    </div>
                @empty
                    <p class="empty-state">No customer orders yet.</p>
                @endforelse
            </div>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <h3>Top Services</h3>
                    <p>Best performing services</p>
                </div>
            </div>
            <div class="rank-list">
                @forelse ($charts['top_services'] as $service)
                    <div>
                        <p>{{ $service['label'] }}</p>
                        <strong>GHS {{ number_format($service['value'], 2) }}</strong>
                    </div>
                @empty
                    <p class="empty-state">No service items yet.</p>
                @endforelse
            </div>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <h3>Top Products</h3>
                    <p>Garment categories by sales</p>
                </div>
            </div>
            <div class="rank-list">
                @forelse ($charts['top_products'] as $product)
                    <div>
                        <p>{{ $product['label'] }}</p>
                        <strong>GHS {{ number_format($product['value'], 2) }}</strong>
                    </div>
                @empty
                    <p class="empty-state">No package products yet.</p>
                @endforelse
            </div>
        </article>
    </section>
</div>
