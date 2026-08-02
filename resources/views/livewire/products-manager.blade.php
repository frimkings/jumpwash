<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 6</p>
            <h2>Products</h2>
        </div>
        <div class="module-actions">
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
            @if (session('error'))
                <span class="notice notice--error">{{ session('error') }}</span>
            @endif
        </div>
    </section>

    <section class="module-grid">
        <form wire:submit="save" class="module-panel">
            <h3>{{ $editingId ? 'Edit Product' : 'Create Product' }}</h3>

            <label class="field">
                <span>Image</span>
                <input type="file" wire:model="image" accept="image/*">
                @error('image') <small>{{ $message }}</small> @enderror
            </label>

            <div class="form-grid form-grid--single">
                <label class="field">
                    <span>Product Name</span>
                    <input type="text" wire:model="name" placeholder="Shirt">
                    @error('name') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Description</span>
                    <textarea rows="4" wire:model="description" placeholder="Describe garment handling notes."></textarea>
                    @error('description') <small>{{ $message }}</small> @enderror
                </label>
                <label class="toggle-field">
                    <input type="checkbox" wire:model="is_active">
                    <span>Status Active</span>
                </label>
            </div>

            <div class="form-actions">
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="btn-secondary">Cancel</button>
                @endif
                <button type="submit" class="btn-primary">{{ $editingId ? 'Update Product' : 'Save Product' }}</button>
            </div>
        </form>

        <section class="module-panel module-panel--list">
            <div class="list-toolbar">
                <label class="field">
                    <span>Search</span>
                    <input type="search" wire:model.live="search" placeholder="Product name or description">
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
                @forelse ($products as $product)
                    <article class="service-row product-row">
                        <div>
                            <div class="service-row__title">
                                <h3>{{ $product->name }}</h3>
                                <span class="{{ $product->is_active ? 'badge badge--success' : 'badge badge--muted' }}">
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p>{{ $product->description ?: 'No description yet.' }}</p>
                        </div>
                        <div class="row-actions">
                            <button type="button" wire:click="edit({{ $product->id }})" class="btn-secondary">Edit</button>
                            <button type="button" wire:click="toggleStatus({{ $product->id }})" class="btn-secondary">
                                {{ $product->is_active ? 'Disable' : 'Enable' }}
                            </button>
                            <button type="button" wire:click="delete({{ $product->id }})" wire:confirm="Delete this product?" class="btn-danger">Delete</button>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">No products found.</p>
                @endforelse
            </div>
        </section>
    </section>
</div>
