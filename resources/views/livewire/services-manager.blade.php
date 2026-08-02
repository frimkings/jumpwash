<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 5</p>
            <h2>Services</h2>
        </div>
        <div class="module-actions">
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
            @if (session('error'))
                <span class="notice notice--error">{{ session('error') }}</span>
            @endif
            <button type="button" wire:click="create" class="btn-primary">New Service</button>
        </div>
    </section>

    <section class="module-grid module-grid--wide">
        <section class="module-panel module-panel--list">
            <div class="list-toolbar">
                <label class="field">
                    <span>Search</span>
                    <input type="search" wire:model.live="search" placeholder="Service name or description">
                </label>
                <label class="field">
                    <span>Status</span>
                    <select wire:model.live="statusFilter">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
            </div>

            <div class="service-list">
                @forelse ($services as $service)
                    <article class="service-row">
                        <div>
                            <div class="service-row__title">
                                <h3>{{ $service->name }}</h3>
                                <span class="{{ $service->is_active ? 'badge badge--success' : 'badge badge--muted' }}">
                                    {{ $service->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p>{{ $service->description ?: 'No description yet.' }}</p>
                            <div class="service-row__meta">
                                <span>PHP {{ number_format((float) $service->price, 2) }} / {{ $service->unit }}</span>
                                <span>{{ number_format((float) $service->tax_percentage, 2) }}% tax</span>
                                <span>{{ $service->turnaround_hours }} hrs</span>
                            </div>
                        </div>
                        <div class="row-actions">
                            <button type="button" wire:click="edit({{ $service->id }})" class="btn-secondary">Edit</button>
                            <button type="button" wire:click="toggleStatus({{ $service->id }})" class="btn-secondary">
                                {{ $service->is_active ? 'Disable' : 'Enable' }}
                            </button>
                            <button type="button" wire:click="delete({{ $service->id }})" wire:confirm="Delete this service?" class="btn-danger">Delete</button>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">No services found.</p>
                @endforelse
            </div>
        </section>
    </section>

    @if ($showFormModal)
        <div class="modal-backdrop" role="presentation">
            <form wire:submit="save" class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="service-modal-title">
                <div class="modal-header">
                    <div>
                        <p class="dashboard-eyebrow">Service Setup</p>
                        <h3 id="service-modal-title">{{ $editingId ? 'Edit Service' : 'Create Service' }}</h3>
                    </div>
                    <button type="button" wire:click="resetForm" class="modal-close" aria-label="Close form">&times;</button>
                </div>

                <div class="form-grid form-grid--single">
                    <label class="field">
                        <span>Service Name</span>
                        <input type="text" wire:model="name" placeholder="Laundry">
                        @error('name') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Description</span>
                        <textarea rows="4" wire:model="description" placeholder="Describe the service scope and handling notes."></textarea>
                        @error('description') <small>{{ $message }}</small> @enderror
                    </label>
                    <div class="form-grid">
                        <label class="field">
                            <span>Base Price</span>
                            <input type="number" step="0.01" wire:model="price">
                            @error('price') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="field">
                            <span>Tax Percentage</span>
                            <input type="number" step="0.01" wire:model="tax_percentage">
                            @error('tax_percentage') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="field">
                            <span>Unit</span>
                            <input type="text" wire:model="unit">
                            @error('unit') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="field">
                            <span>Turnaround Hours</span>
                            <input type="number" wire:model="turnaround_hours">
                            @error('turnaround_hours') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>
                    <label class="toggle-field">
                        <input type="checkbox" wire:model="is_active">
                        <span>Status Active</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="button" wire:click="resetForm" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">{{ $editingId ? 'Update Service' : 'Save Service' }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
