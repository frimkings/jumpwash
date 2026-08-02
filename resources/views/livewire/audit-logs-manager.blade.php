<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 22</p>
            <h2>Audit Logging</h2>
        </div>
        <div class="module-actions">
            <span class="notice">Created | Updated | Deleted | Printed | Payment Received | Tag Reprinted | Status Changed</span>
        </div>
    </section>

    <section class="module-panel">
        <div class="audit-filters">
            <label class="field">
                <span>Search</span>
                <input type="search" wire:model.live="search" placeholder="User, action, module, values">
            </label>
            <label class="field">
                <span>Action</span>
                <select wire:model.live="actionFilter">
                    <option value="all">All</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}">{{ ucfirst(str_replace(['.', '_'], ' ', $action)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Start</span>
                <input type="date" wire:model.live="start_date">
            </label>
            <label class="field">
                <span>End</span>
                <input type="date" wire:model.live="end_date">
            </label>
        </div>
    </section>

    <section class="module-panel">
        <div class="audit-table">
            <div class="audit-table__head">
                <span>User</span><span>Action</span><span>Timestamp</span><span>Old Value</span><span>New Value</span>
            </div>
            @forelse ($logs as $log)
                <div class="audit-table__row">
                    <span>{{ $log->user?->name ?? 'System' }}</span>
                    <strong>{{ ucfirst(str_replace(['.', '_'], ' ', $log->action)) }}</strong>
                    <span>{{ $log->created_at->format('M d, Y h:i A') }}</span>
                    <code>{{ $log->old_values ? json_encode($log->old_values) : 'N/A' }}</code>
                    <code>{{ $log->new_values ? json_encode($log->new_values) : json_encode($log->properties) }}</code>
                </div>
            @empty
                <p class="empty-state">No audit logs found.</p>
            @endforelse
        </div>
    </section>
</div>
