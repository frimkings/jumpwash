<div class="module-page registry-page">
    <section class="module-header registry-page-header">
        <div>
            <h2>Registry Hub</h2>
            <p class="dashboard-eyebrow">Laundry customer administration dashboard</p>
        </div>
        <div class="module-actions">
            <button type="button" wire:click="exportCustomers" class="btn-secondary registry-export-btn">Export CSV</button>
            <label class="btn-primary registry-import-btn">
                <span>{{ $importFile ? 'CSV Selected' : 'Import CSV' }}</span>
                <input type="file" wire:model="importFile" accept=".csv,text/csv">
            </label>
            @if ($importFile)
                <button type="button" wire:click="importCustomers" class="btn-primary">Run Import</button>
            @endif
            <button type="button" wire:click="downloadTemplate" class="btn-secondary">Template</button>
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
            @if (session('error'))
                <span class="notice notice--error">{{ session('error') }}</span>
            @endif
        </div>
    </section>

    <section class="customer-layout">
        <form wire:submit="save" class="module-panel registry-card registry-form-card">
            <div class="registry-card-title">
                <x-ui.nav-icon name="customers" />
                <h3>{{ $editingId ? 'Edit Customer' : 'Registration' }}</h3>
            </div>

            <div class="form-grid">
                <label class="field field--wide">
                    <span>PX Number</span>
                    <input type="text" value="{{ $editingId ? $customer_code : $nextCustomerNumber }}" readonly>
                </label>
                <label class="field field--wide">
                    <span>Full Name</span>
                    <input type="text" wire:model="full_name" placeholder="Enter customer name...">
                    @error('full_name') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Email Address</span>
                    <input type="email" wire:model="email">
                    @error('email') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Contact</span>
                    <input type="text" wire:model="phone">
                    @error('phone') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Status</span>
                    <select wire:model="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </label>
                <label class="field field--wide">
                    <span>Address</span>
                    <input type="text" wire:model="address">
                    @error('address') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>GPS Location</span>
                    <input type="text" wire:model="gps_location" placeholder="Latitude, Longitude or map reference">
                    @error('gps_location') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Notes</span>
                    <textarea rows="3" wire:model="notes" placeholder="Customer preferences, garment notes, pickup instructions"></textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="form-actions registry-form-actions">
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="btn-secondary">Cancel</button>
                @else
                    <button type="button" wire:click="quickRegister" class="btn-secondary">Quick Registration</button>
                @endif
                <button type="submit" class="btn-primary">{{ $editingId ? 'Update Customer' : 'Save Customer' }}</button>
            </div>
        </form>

        <section class="module-panel module-panel--list registry-card registry-list-card">
            <div class="registry-list-header">
                <div>
                    <h3>Customer List</h3>
                    <span>Active: {{ $activeCustomers }}</span>
                    <span>Inactive: {{ $inactiveCustomers }}</span>
                </div>
                <div class="registry-badge">Records {{ $customers->total() }}</div>
            </div>

            <div class="list-toolbar registry-toolbar">
                <label class="field">
                    <span>Search Profile</span>
                    <input type="search" wire:model.live="search" placeholder="Number, name, phone, email">
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

            <div class="customer-table">
                <div class="customer-table__head">
                    <span></span>
                    <span>Customer Profile</span>
                    <span>Customer Info</span>
                    <span>Actions</span>
                </div>

                @forelse ($customers as $customer)
                    <article class="customer-row {{ $selectedId === $customer->id ? 'customer-row--active' : '' }}">
                        <label class="registry-check">
                            <input type="checkbox" @checked($selectedId === $customer->id) wire:click="selectCustomer({{ $customer->id }})">
                        </label>

                        <button type="button" wire:click="selectCustomer({{ $customer->id }})" class="customer-row__main">
                            <div>
                                <h3>{{ $customer->name }}</h3>
                                <p>{{ $customer->customer_code }} &middot; {{ $customer->phone }}</p>
                            </div>
                        </button>

                        <div class="customer-row__contact">
                            <strong>{{ $customer->is_active ? 'Active' : 'Inactive' }}</strong>
                            <span>{{ $customer->email ?: 'No email' }} | {{ $customer->address ?: 'No address' }}</span>
                        </div>

                        <div class="row-actions registry-actions">
                            <button type="button" wire:click="selectCustomer({{ $customer->id }})" class="registry-icon-btn registry-icon-btn--profile" title="View profile">View</button>
                            <button type="button" wire:click="edit({{ $customer->id }})" class="registry-icon-btn registry-icon-btn--edit" title="Edit customer">Edit</button>
                            <button type="button" wire:click="toggleStatus({{ $customer->id }})" class="registry-icon-btn registry-icon-btn--status" title="{{ $customer->is_active ? 'Disable customer' : 'Enable customer' }}">
                                {{ $customer->is_active ? 'Off' : 'On' }}
                            </button>
                            <button type="button" wire:click="delete({{ $customer->id }})" wire:confirm="Delete this customer?" class="registry-icon-btn registry-icon-btn--delete" title="Delete customer">Del</button>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">No customers found.</p>
                @endforelse
            </div>

            <div class="registry-pagination">
                {{ $customers->links() }}
            </div>
        </section>
    </section>

    @if ($selectedCustomer && $showProfileModal)
        <div class="modal-backdrop customer-profile-backdrop" wire:key="customer-profile-modal">
            <section class="modal-panel customer-profile-modal">
                <div class="modal-header customer-profile-header">
                    <div>
                        <p class="dashboard-eyebrow">Customer Profile</p>
                        <h3>{{ $selectedCustomer->name }}</h3>
                        <span>{{ $selectedCustomer->customer_code }} &middot; {{ $selectedCustomer->phone }}</span>
                    </div>
                    <div class="customer-profile-header__actions">
                        <span class="{{ $selectedCustomer->is_active ? 'badge badge--success' : 'badge badge--muted' }}">
                            {{ $selectedCustomer->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <button type="button" wire:click="edit({{ $selectedCustomer->id }})" class="btn-primary">Edit Customer</button>
                        <button type="button" wire:click="closeProfile" class="modal-close" aria-label="Close profile">×</button>
                    </div>
                </div>

                <div class="profile-grid">
                    <div class="profile-card">
                        <h4>Contact</h4>
                        <p>{{ $selectedCustomer->customer_code }}</p>
                        <p>{{ $selectedCustomer->phone }}</p>
                        <p>{{ $selectedCustomer->email ?: 'No email' }}</p>
                        <p>{{ $selectedCustomer->address ?: 'No address' }}</p>
                        <p>{{ $selectedCustomer->gps_location ?: 'No GPS location' }}</p>
                        <p>{{ $selectedCustomer->notes ?: 'No notes' }}</p>
                    </div>

                    <div class="profile-card">
                        <h4>Customer Orders</h4>
                        @forelse ($selectedCustomer->orders as $order)
                            <div class="profile-line">
                                <span>{{ $order->order_number }}</span>
                                <strong>GHS {{ number_format((float) $order->total, 2) }}</strong>
                                <small>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</small>
                            </div>
                        @empty
                            <p class="empty-state">No orders yet.</p>
                        @endforelse
                    </div>

                    <div class="profile-card">
                        <h4>Customer Payments</h4>
                        @forelse ($selectedCustomer->payments as $payment)
                            <div class="profile-line">
                                <span>{{ $payment->receipt_number }}</span>
                                <strong>GHS {{ number_format((float) $payment->amount, 2) }}</strong>
                                <small>{{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</small>
                            </div>
                        @empty
                            <p class="empty-state">No payments yet.</p>
                        @endforelse
                    </div>

                    <div class="profile-card">
                        <h4>Loyalty Points</h4>
                        <p>{{ (int) $selectedCustomer->loyalty_points }} points available</p>
                        <form wire:submit="adjustLoyaltyPoints" class="inline-form">
                            <label class="field">
                                <span>Adjust Points</span>
                                <input type="number" step="1" wire:model="loyalty_adjustment_points" placeholder="+10 or -10">
                                @error('loyalty_adjustment_points') <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Reason</span>
                                <input type="text" wire:model="loyalty_adjustment_reason" placeholder="Required">
                                @error('loyalty_adjustment_reason') <small>{{ $message }}</small> @enderror
                            </label>
                            <button type="submit" class="btn-secondary">Save Adjustment</button>
                        </form>
                        @forelse ($selectedCustomer->loyaltyTransactions as $transaction)
                            <div class="profile-line">
                                <span>{{ ucfirst($transaction->type) }}</span>
                                <strong>{{ $transaction->points > 0 ? '+' : '' }}{{ $transaction->points }} pts</strong>
                                <small>{{ $transaction->created_at->format('M d, Y') }}</small>
                            </div>
                        @empty
                            <p class="empty-state">No loyalty activity yet.</p>
                        @endforelse
                    </div>

                    <div class="profile-card">
                        <h4>Customer Subscriptions</h4>
                        @forelse ($selectedCustomer->subscriptions as $subscription)
                            <div class="profile-line">
                                <span>{{ $subscription->plan?->name ?? 'Subscription' }}</span>
                                <strong>{{ $subscription->remainingUses() }} left</strong>
                                <small>{{ ucfirst($subscription->status) }} &middot; {{ $subscription->ends_at->format('M d, Y') }}</small>
                            </div>
                        @empty
                            <p class="empty-state">No subscriptions yet.</p>
                        @endforelse
                    </div>

                    <div class="profile-card profile-card--wide">
                        <h4>Customer History</h4>
                        @forelse ($selectedCustomer->history as $history)
                            <div class="profile-line">
                                <span>{{ ucfirst(str_replace('_', ' ', $history->action)) }}</span>
                                <strong>{{ $history->module }}</strong>
                                <small>{{ $history->created_at->format('M d, Y h:i A') }}</small>
                            </div>
                        @empty
                            <p class="empty-state">No activity history yet.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
