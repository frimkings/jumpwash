<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 7</p>
            <h2>Rate Chart</h2>
        </div>
        @if (session('status'))
            <span class="notice notice--success">{{ session('status') }}</span>
        @endif
    </section>

    <section class="module-grid">
        <form wire:submit="save" class="module-panel">
            <h3>{{ $editingId ? 'Edit Rate' : 'Create Rate' }}</h3>
            @if ($product_id && $laundry_service_id)
                <p class="notice">Selected pair is ready for pricing. Enter a positive amount to mark it complete.</p>
            @endif
            <div class="form-grid form-grid--single">
                <label class="field">
                    <span>Product</span>
                    <select wire:model="product_id">
                        <option value="">Select product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    @error('product_id') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Service</span>
                    <select wire:model="laundry_service_id">
                        <option value="">Select service</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('laundry_service_id') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Price</span>
                    <input type="number" min="0.01" step="0.01" wire:model="price">
                    @error('price') <small>{{ $message }}</small> @enderror
                </label>
            </div>
            <div class="form-actions">
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="btn-secondary">Cancel</button>
                @endif
                <button type="submit" class="btn-primary">{{ $editingId ? 'Update Rate' : 'Save Rate' }}</button>
            </div>
        </form>

        <section class="module-panel module-panel--list">
            <div class="service-row__title">
                <h3>Rate Completeness</h3>
                <span class="badge {{ $missingRates->isEmpty() ? 'badge--success' : 'badge--warning' }}">
                    {{ $coveragePercent }}% complete
                </span>
            </div>
            <div class="service-row__meta">
                <span>{{ $completeCombinations }} priced</span>
                <span>{{ $missingRates->count() }} incomplete</span>
                <span>{{ $totalCombinations }} total pairs</span>
            </div>

            <div class="service-list">
                @forelse ($missingRates->take(12) as $missingRate)
                    <article class="service-row">
                        <div>
                            <div class="service-row__title">
                                <h3>{{ $missingRate['product_name'] }}</h3>
                                <span class="badge badge--warning">{{ $missingRate['reason'] }}</span>
                            </div>
                            <p>{{ $missingRate['service_name'] }}</p>
                            <div class="service-row__meta">
                                <span>{{ $missingRate['price'] === null ? 'No rate exists' : 'Current price GHS '.number_format((float) $missingRate['price'], 2) }}</span>
                            </div>
                        </div>
                        <div class="row-actions">
                            <button
                                type="button"
                                wire:click="selectMissingRate({{ $missingRate['product_id'] }}, {{ $missingRate['service_id'] }})"
                                class="btn-secondary"
                            >
                                Set Rate
                            </button>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">All active product and service combinations have positive prices.</p>
                @endforelse
            </div>

            @if ($missingRates->count() > 12)
                <p class="notice">{{ $missingRates->count() - 12 }} more incomplete pairs. Use products/services cleanup or continue setting rates above.</p>
            @endif
        </section>

        <section class="module-panel module-panel--list">
            <div class="list-toolbar list-toolbar--single">
                <label class="field">
                    <span>Search</span>
                    <input type="search" wire:model.live="search" placeholder="Product or service">
                </label>
            </div>

            <div class="rate-table">
                <div class="rate-table__head">
                    <span>Product</span>
                    <span>Service</span>
                    <span>Price</span>
                    <span></span>
                </div>
                @forelse ($rates as $rate)
                    <div class="rate-table__row">
                        <span>{{ $rate->product?->name }}</span>
                        <span>{{ $rate->service?->name }}</span>
                        <strong>GHS {{ number_format((float) $rate->price, 2) }}</strong>
                        <div class="row-actions">
                            <button type="button" wire:click="edit({{ $rate->id }})" class="btn-secondary">Edit</button>
                            <button type="button" wire:click="delete({{ $rate->id }})" wire:confirm="Delete this rate?" class="btn-danger">Delete</button>
                        </div>
                    </div>
                @empty
                    <p class="empty-state">No product-service rates found.</p>
                @endforelse
            </div>
        </section>
    </section>
</div>
