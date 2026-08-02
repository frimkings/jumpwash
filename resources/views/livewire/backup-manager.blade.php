<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 23</p>
            <h2>Backup Module</h2>
        </div>
        <div class="module-actions">
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
        </div>
    </section>

    <section class="module-grid backup-layout">
        <form wire:submit="createBackup" class="module-panel">
            <h3>Create Offline Backup</h3>
            <div class="form-grid">
                <label class="field">
                    <span>Backup Type</span>
                    <select wire:model="type">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Mode</span>
                    <select wire:model.live="mode">
                        <option value="manual">Manual Backup</option>
                        <option value="scheduled">Scheduled Backup</option>
                    </select>
                </label>
                <label class="field">
                    <span>Target</span>
                    <select wire:model.live="target">
                        @foreach ($targets as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Scheduled Time</span>
                    <input type="datetime-local" wire:model="scheduled_at" @disabled($mode !== 'scheduled')>
                </label>
                <label class="field field--wide">
                    <span>External / USB / Network Folder Path</span>
                    <input type="text" wire:model="target_path" placeholder="Example: E:\JumpWashBackups or \\SERVER\Backups">
                    @error('target_path') <small>{{ $message }}</small> @enderror
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Run Backup</button>
            </div>
        </form>

        <section class="module-panel">
            <h3>Backup Targets</h3>
            <div class="backup-targets">
                <div><strong>External Drive</strong><span>Use a drive path such as E:\Backups.</span></div>
                <div><strong>USB</strong><span>Use the USB drive letter path.</span></div>
                <div><strong>Network Folder</strong><span>Use a LAN share path such as \\SERVER\Backups.</span></div>
            </div>
        </section>
    </section>

    <section class="module-panel module-panel--list">
        <h3>Backup History</h3>
        <div class="backup-table">
            <div class="backup-table__head">
                <span>Backup No</span><span>Type</span><span>Mode</span><span>Target</span><span>Size</span><span>Status</span><span>Created</span>
            </div>
            @forelse ($backups as $backup)
                <div class="backup-table__row">
                    <strong>{{ $backup->backup_number }}</strong>
                    <span>{{ $types[$backup->type] ?? ucfirst($backup->type) }}</span>
                    <span>{{ ucfirst($backup->mode) }}</span>
                    <span>{{ $targets[$backup->target] ?? ucfirst($backup->target) }}</span>
                    <span>{{ number_format($backup->file_size / 1024, 1) }} KB</span>
                    <span>{{ ucfirst($backup->status) }}</span>
                    <span>{{ $backup->created_at->format('M d, Y h:i A') }}</span>
                </div>
            @empty
                <p class="empty-state">No backups created yet.</p>
            @endforelse
        </div>
    </section>
</div>
