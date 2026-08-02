<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'JumpWash') }} - {{ $title ?? View::yieldContent('title', 'Dashboard') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('offline.css') }}">
    @endif
    @if (file_exists(public_path('dashboard-admin.css')))
        <link rel="stylesheet" href="{{ asset('dashboard-admin.css') }}?v={{ filemtime(public_path('dashboard-admin.css')) }}">
    @endif
    @stack('styles')
    @livewireStyles
</head>
<body class="app-body min-h-screen bg-zinc-50 text-zinc-950 antialiased">
    @php
        $navItems = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'can' => 'dashboard.view'],
            ['label' => 'Orders', 'route' => 'orders.index', 'icon' => 'orders', 'canany' => ['orders.manage', 'orders.assigned.view']],
            ['label' => 'Garment Tags', 'route' => 'garment-tags.index', 'icon' => 'tag', 'can' => 'garments.scan'],
            ['label' => 'Customers', 'route' => 'customers.index', 'icon' => 'customers', 'can' => 'customers.manage'],
            ['label' => 'Payments', 'route' => 'payments.index', 'icon' => 'payments', 'can' => 'payments.manage'],
            ['label' => 'Services', 'route' => 'services.index', 'icon' => 'services', 'can' => 'services.manage'],
            ['label' => 'Products', 'route' => 'products.index', 'icon' => 'products', 'can' => 'products.manage'],
            ['label' => 'Rate Chart', 'route' => 'rate-chart.index', 'icon' => 'rates', 'can' => 'rate-chart.manage'],
            ['label' => 'Subscriptions', 'route' => 'subscriptions.index', 'icon' => 'subscriptions', 'can' => 'subscriptions.manage'],
            ['label' => 'Pickups', 'route' => 'pickups.index', 'icon' => 'pickup', 'canany' => ['deliveries.manage', 'deliveries.assigned.view']],
            ['label' => 'Deliveries', 'route' => 'deliveries.index', 'icon' => 'delivery', 'canany' => ['deliveries.manage', 'deliveries.assigned.view']],
            ['label' => 'Calendar', 'route' => 'calendar.index', 'icon' => 'calendar', 'canany' => ['deliveries.manage', 'orders.manage', 'subscriptions.manage', 'staff.manage']],
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'notifications', 'can' => 'dashboard.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'reports', 'can' => 'reports.view'],
            ['label' => 'Audit Logs', 'route' => 'audit-logs.index', 'icon' => 'audit', 'canany' => ['settings.manage', 'reports.view']],
            ['label' => 'Access Control', 'route' => 'access-control.index', 'icon' => 'access', 'can' => 'settings.manage'],
            ['label' => 'Backups', 'route' => 'backups.index', 'icon' => 'backup', 'can' => 'settings.manage'],
            ['label' => 'Settings', 'route' => 'settings.index', 'icon' => 'settings', 'can' => 'settings.manage'],
        ];
    @endphp

    <div class="app-shell flex min-h-screen">
        <aside class="app-sidebar w-64">
            <div class="sidebar-brand">
                <div class="brand-mark">JW</div>
                <div>
                    <p>JumpWash</p>
                    <span>Offline LAN POS</span>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Primary navigation">
                @foreach ($navItems as $item)
                    @php
                        $isActive = request()->routeIs($item['route']);
                        $link = route($item['route']);
                    @endphp

                    @if (isset($item['canany']))
                        @canany($item['canany'])
                            <a href="{{ $link }}" class="sidebar-link {{ $isActive ? 'sidebar-link--active' : '' }}">
                                <x-ui.nav-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endcanany
                    @else
                        @can($item['can'])
                            <a href="{{ $link }}" class="sidebar-link {{ $isActive ? 'sidebar-link--active' : '' }}">
                                <x-ui.nav-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endcan
                    @endif
                @endforeach
            </nav>
        </aside>

        <main class="app-main min-w-0 flex-1">
            <header class="app-topbar">
                <div>
                    <p class="topbar-branch">{{ auth()->user()->branch?->name ?? 'Local Branch' }}</p>
                    <h1>{{ $title ?? View::yieldContent('title', 'Dashboard') }}</h1>
                </div>
                <nav class="mobile-nav" aria-label="Mobile navigation">
                    @foreach ($navItems as $item)
                        @if (isset($item['canany']))
                            @canany($item['canany'])
                                <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route']) ? 'mobile-nav__link--active' : '' }}">{{ $item['label'] }}</a>
                            @endcanany
                        @else
                            @can($item['can'])
                                <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route']) ? 'mobile-nav__link--active' : '' }}">{{ $item['label'] }}</a>
                            @endcan
                        @endif
                    @endforeach
                </nav>
                <div class="topbar-tools" aria-label="Quick actions">
                    <button type="button" aria-label="Search">⌕</button>
                    <button type="button" class="topbar-bell" aria-label="Notifications">!</button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="topbar-logout">Logout</button>
                    </form>
                </div>
            </header>

            <section class="app-content">
                {{ $slot ?? '' }}
                @yield('content')
            </section>
        </main>
    </div>
    @livewireScripts
    @stack('scripts')
</body>
</html>
