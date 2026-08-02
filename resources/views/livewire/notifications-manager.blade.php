<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 20</p>
            <h2>Notifications Module</h2>
        </div>
        <div class="module-actions">
            <span class="notice">{{ $unreadCount }} unread</span>
            <button type="button" wire:click="refreshNotifications" class="btn-secondary">Refresh Offline Alerts</button>
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
        </div>
    </section>

    <section class="module-panel">
        <div class="notification-architecture">
            <div>
                <strong>Offline Local</strong>
                <span>Active now, stored in local database.</span>
            </div>
            <div>
                <strong>SMS</strong>
                <span>Future adapter-ready channel.</span>
            </div>
            <div>
                <strong>WhatsApp</strong>
                <span>Future adapter-ready channel.</span>
            </div>
        </div>
    </section>

    <section class="module-panel">
        <div class="notification-filters">
            <label class="field">
                <span>Type</span>
                <select wire:model.live="typeFilter">
                    <option value="all">All</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Status</span>
                <select wire:model.live="statusFilter">
                    <option value="all">All</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
            </label>
            <label class="field">
                <span>Channel</span>
                <select wire:model.live="channelFilter">
                    <option value="all">All</option>
                    @foreach ($channels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="module-panel module-panel--list">
        <div class="service-list">
            @forelse ($notifications as $notification)
                <article class="service-row notification-row {{ $notification->status === 'unread' ? 'notification-row--unread' : '' }}">
                    <div>
                        <div class="service-row__title">
                            <h3>{{ $notification->title }}</h3>
                            <span class="badge {{ $notification->status === 'unread' ? 'badge--success' : 'badge--muted' }}">{{ ucfirst($notification->status) }}</span>
                        </div>
                        <p>{{ $notification->message }}</p>
                        <div class="service-row__meta">
                            <span>{{ $types[$notification->type] ?? ucfirst(str_replace('_', ' ', $notification->type)) }}</span>
                            <span>{{ $channels[$notification->channel] ?? ucfirst($notification->channel) }}</span>
                            <span>{{ $notification->created_at->format('M d, Y h:i A') }}</span>
                            <span>SMS/WhatsApp ready</span>
                        </div>
                    </div>
                    <div class="row-actions">
                        @if ($notification->status === 'unread')
                            <button type="button" wire:click="markRead({{ $notification->id }})" class="btn-secondary">Mark Read</button>
                        @else
                            <button type="button" wire:click="markUnread({{ $notification->id }})" class="btn-secondary">Mark Unread</button>
                        @endif
                    </div>
                </article>
            @empty
                <p class="empty-state">No notifications found.</p>
            @endforelse
        </div>
    </section>
</div>
