<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 15</p>
            <h2>FullCalendar Integration</h2>
        </div>
        <div class="module-actions calendar-legend">
            <span class="legend-chip legend-chip--pickup">Pickup Schedule</span>
            <span class="legend-chip legend-chip--delivery">Delivery Schedule</span>
            <span class="legend-chip legend-chip--staff">Staff Assignments</span>
            <span class="legend-chip legend-chip--subscription">Subscription Expiry</span>
        </div>
    </section>

    <section class="kpi-grid">
        <article class="kpi-card kpi-card--pickup">
            <p>Pickup Schedule</p>
            <strong>{{ $counts['pickups'] }}</strong>
        </article>
        <article class="kpi-card kpi-card--ready">
            <p>Delivery Schedule</p>
            <strong>{{ $counts['deliveries'] }}</strong>
        </article>
        <article class="kpi-card kpi-card--wash">
            <p>Staff Assignments</p>
            <strong>{{ $counts['assignments'] }}</strong>
        </article>
        <article class="kpi-card kpi-card--plans">
            <p>Subscription Expiry</p>
            <strong>{{ $counts['subscriptions'] }}</strong>
        </article>
    </section>

    <section class="module-panel calendar-panel" wire:ignore>
        <div id="operations-calendar" data-events='@json($events->values())'></div>
    </section>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/fullcalendar/fullcalendar.local.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/fullcalendar/fullcalendar.local.js') }}"></script>
    <script>
        document.addEventListener('livewire:navigated', loadOperationsCalendar);
        document.addEventListener('DOMContentLoaded', loadOperationsCalendar);

        function loadOperationsCalendar() {
            const target = document.getElementById('operations-calendar');
            if (!target || target.dataset.rendered === 'true' || !window.FullCalendar) {
                return;
            }

            const events = JSON.parse(target.dataset.events || '[]');
            const calendar = new FullCalendar.Calendar(target, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay',
                },
                events,
                eventClick: function (info) {
                    const props = info.event.extendedProps || {};
                    const lines = [
                        info.event.title,
                        props.category ? 'Category: ' + props.category : null,
                        props.status ? 'Status: ' + props.status : null,
                        props.staff ? 'Staff: ' + props.staff : null,
                        props.order ? 'Order/Plan: ' + props.order : null,
                        props.address ? 'Address: ' + props.address : null,
                    ].filter(Boolean);
                    alert(lines.join("\n"));
                },
            });

            calendar.render();
            target.dataset.rendered = 'true';
        }
    </script>
@endpush
