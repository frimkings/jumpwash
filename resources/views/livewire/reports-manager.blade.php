<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 19</p>
            <h2>Reporting Module</h2>
        </div>
        <div class="module-actions">
            <a href="{{ route('reports.export', ['format' => 'pdf']).'?'.$exportQuery }}" class="btn-secondary">Export PDF</a>
            <a href="{{ route('reports.export', ['format' => 'excel']).'?'.$exportQuery }}" class="btn-primary">Export Excel</a>
        </div>
    </section>

    <section class="module-panel">
        <div class="report-filters">
            <label class="field">
                <span>Report</span>
                <select wire:model.live="type">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Filter</span>
                <select wire:model.live="period">
                    @foreach ($periods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Start Date</span>
                <input type="date" wire:model.live="start_date" @disabled($period !== 'custom')>
            </label>
            <label class="field">
                <span>End Date</span>
                <input type="date" wire:model.live="end_date" @disabled($period !== 'custom')>
            </label>
        </div>
    </section>

    <section class="analytics-grid">
        <article class="analytics-card"><p>Report</p><strong>{{ $report['title'] }}</strong></article>
        <article class="analytics-card"><p>Period</p><strong>{{ $report['start']->format('M d, Y') }} - {{ $report['end']->format('M d, Y') }}</strong></article>
        <article class="analytics-card"><p>Records</p><strong>{{ $report['summary']['records'] }}</strong></article>
    </section>

    <section class="module-panel">
        <h3>{{ $report['title'] }}</h3>
        <div class="report-table">
            <div class="report-table__head" style="--cols: {{ count($report['headings']) }}">
                @foreach ($report['headings'] as $heading)
                    <span>{{ $heading }}</span>
                @endforeach
            </div>
            @forelse ($report['rows'] as $row)
                <div class="report-table__row" style="--cols: {{ count($report['headings']) }}">
                    @foreach ($row as $value)
                        <span>{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</span>
                    @endforeach
                </div>
            @empty
                <p class="empty-state">No records found for this filter.</p>
            @endforelse
        </div>
    </section>
</div>
