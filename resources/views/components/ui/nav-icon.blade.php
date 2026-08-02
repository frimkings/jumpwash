@props(['name'])

<svg {{ $attributes->merge(['class' => 'nav-icon', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('home')
            <path d="m3 11 9-8 9 8" /><path d="M5 10v10h5v-6h4v6h5V10" />
            @break
        @case('orders')
            <path d="M7 3h10l2 3v15H5V6l2-3Z" /><path d="M7 6h10" /><path d="M8 11h8" /><path d="M8 15h6" />
            @break
        @case('tag')
            <path d="M20 13 13 20 4 11V4h7l9 9Z" /><path d="M7.5 7.5h.01" />
            @break
        @case('customers')
            <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" /><path d="M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('staff')
            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /><path d="M4 21a8 8 0 0 1 16 0" />
            @break
        @case('payments')
            <path d="M3 7h18v10H3z" /><path d="M3 10h18" /><path d="M7 15h3" />
            @break
        @case('services')
            <path d="M12 3v18" /><path d="M5 7h14" /><path d="M7 12h10" /><path d="M9 17h6" />
            @break
        @case('products')
            <path d="m21 8-9-5-9 5 9 5 9-5Z" /><path d="M3 8v8l9 5 9-5V8" /><path d="M12 13v8" />
            @break
        @case('rates')
            <path d="M4 19V5" /><path d="M4 19h16" /><path d="M8 16V9" /><path d="M12 16V6" /><path d="M16 16v-4" />
            @break
        @case('subscriptions')
            <path d="M6 3h12v18l-6-3-6 3V3Z" /><path d="M9 8h6" /><path d="M9 12h4" />
            @break
        @case('pickup')
            <path d="M3 7h11v10H3z" /><path d="M14 11h3l3 3v3h-6" /><path d="M7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" /><path d="M17 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
            @break
        @case('delivery')
            <path d="M5 17H3V6h12v11H9" /><path d="M15 9h3l3 4v4h-2" /><path d="M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" /><path d="M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
            @break
        @case('calendar')
            <path d="M8 2v4" /><path d="M16 2v4" /><path d="M3 9h18" /><path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z" />
            @break
        @case('notifications')
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z" /><path d="M10 21h4" />
            @break
        @case('reports')
            <path d="M4 19V5" /><path d="M4 19h16" /><path d="M8 15l3-3 3 2 5-6" />
            @break
        @case('audit')
            <path d="M9 11h6" /><path d="M9 15h4" /><path d="M6 3h9l3 3v15H6z" /><path d="M15 3v4h4" />
            @break
        @case('access')
            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /><path d="M4 21a8 8 0 0 1 16 0" /><path d="m17 11 2 2 4-4" />
            @break
        @case('backup')
            <path d="M12 3v10" /><path d="m8 9 4 4 4-4" /><path d="M5 17h14v4H5z" />
            @break
        @case('settings')
            <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" /><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.05.05-2.12 2.12-.05-.05a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V20h-3v-.08a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.87.34l-.05.05-2.12-2.12.05-.05A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3v-3h.08a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.87l-.05-.05 2.12-2.12.05.05A1.7 1.7 0 0 0 8.3 6.35a1.7 1.7 0 0 0 1-1.55V4h3v.08a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.05-.05 2.12 2.12-.05.05A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.55 1H21v3h-.08a1.7 1.7 0 0 0-1.52 1Z" />
            @break
    @endswitch
</svg>
