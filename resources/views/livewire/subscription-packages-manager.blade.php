<div class="module-page subscription-page">
    <section class="module-header subscription-hero">
        <div>
            <p class="dashboard-eyebrow">Section 8</p>
            <h2>Subscription Workbench</h2>
            <p class="subscription-hero__copy">Manage packages, assign customers, and follow renewals from one place.</p>
        </div>
        <div class="module-actions">
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
            @if (session('error'))
                <span class="notice notice--error">{{ session('error') }}</span>
            @endif
            <button type="button" wire:click="openPackageForm" class="btn-secondary">New Package</button>
            <button type="button" wire:click="setActiveTab('assign')" class="btn-primary">Assign Subscription</button>
        </div>
    </section>

    <section class="subscription-stats">
        <article>
            <span>Active packages</span>
            <strong>{{ $subscriptionStats['active_packages'] }}</strong>
        </article>
        <article class="{{ $subscriptionStats['needs_setup'] > 0 ? 'subscription-stat--warning' : '' }}">
            <span>Needs setup</span>
            <strong>{{ $subscriptionStats['needs_setup'] }}</strong>
        </article>
        <article>
            <span>Active customers</span>
            <strong>{{ $subscriptionStats['active_subscriptions'] }}</strong>
        </article>
        <article class="{{ $subscriptionStats['expiring_soon'] > 0 ? 'subscription-stat--due' : '' }}">
            <span>Expiring soon</span>
            <strong>{{ $subscriptionStats['expiring_soon'] }}</strong>
        </article>
        <article>
            <span>Remaining uses</span>
            <strong>{{ $subscriptionStats['remaining_uses'] }}</strong>
        </article>
    </section>

    <nav class="subscription-tabs" aria-label="Subscription views">
        <button type="button" wire:click="setActiveTab('packages')" class="{{ $activeTab === 'packages' ? 'is-active' : '' }}">Packages</button>
        <button type="button" wire:click="setActiveTab('subscriptions')" class="{{ $activeTab === 'subscriptions' ? 'is-active' : '' }}">Customer Subscriptions</button>
        <button type="button" wire:click="setActiveTab('assign')" class="{{ $activeTab === 'assign' ? 'is-active' : '' }}">Assign Subscription</button>
        <button type="button" wire:click="setActiveTab('expiring')" class="{{ $activeTab === 'expiring' ? 'is-active' : '' }}">Expiring / Due</button>
    </nav>

    @if ($activeTab === 'packages')
        <section class="subscription-package-layout {{ $showPackageForm || $editingId ? 'has-editor' : '' }}">
            <section class="module-panel module-panel--list subscription-list-panel">
                <div class="subscription-panel-heading">
                    <div>
                        <p class="dashboard-eyebrow">Packages</p>
                        <h3>Available Subscription Packages</h3>
                    </div>
                    <button type="button" wire:click="openPackageForm" class="btn-secondary">New Package</button>
                </div>

                <div class="list-toolbar">
                    <label class="field">
                        <span>Search</span>
                        <input type="search" wire:model.live.debounce.250ms="search" placeholder="Package or service">
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

                <div class="subscription-package-list">
                    @forelse ($packages as $package)
                        @php
                            $issues = $this->packageIssues($package);
                            $displayAmount = (float) ($package->amount ?: $package->price);
                            $displayUsage = (int) ($package->usage_limit ?: $package->wash_limit);
                            $displayValidity = (int) ($package->validity_months ?: max(1, (int) ceil(((int) $package->validity_days) / 30)));
                        @endphp
                        <article class="subscription-package-card {{ $issues ? 'has-warning' : '' }}" wire:key="package-{{ $package->id }}">
                            <div class="subscription-package-card__rail"></div>
                            <div class="subscription-package-card__body">
                                <div class="subscription-row-title">
                                    <h3>{{ $package->name }}</h3>
                                    @if ($issues)
                                        <span class="badge badge--warning">Needs setup</span>
                                    @else
                                        <span class="{{ $package->is_active ? 'badge badge--success' : 'badge badge--muted' }}">
                                            {{ $package->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    @endif
                                </div>
                                <p>{{ $package->service?->name ?? 'No service selected' }}</p>
                                @if ($issues)
                                    <p class="subscription-warning">{{ implode(', ', $issues) }}</p>
                                @endif
                                <div class="subscription-metrics">
                                    <span><strong>{{ $displayValidity }}</strong> months</span>
                                    <span><strong>{{ $displayUsage }}</strong> uses</span>
                                    <span>{{ $package->pickup_included ? 'Pickup included' : 'No pickup' }}</span>
                                    <span><strong>GHS {{ number_format($displayAmount, 2) }}</strong></span>
                                </div>
                            </div>
                            <div class="subscription-card-actions">
                                <button type="button" wire:click="edit({{ $package->id }})" class="btn-secondary">Edit</button>
                                <button type="button" wire:click="toggleStatus({{ $package->id }})" class="btn-secondary">
                                    {{ $package->is_active ? 'Disable' : 'Enable' }}
                                </button>
                                <button type="button" wire:click="delete({{ $package->id }})" wire:confirm="Delete this package?" class="btn-danger">Delete</button>
                            </div>
                        </article>
                    @empty
                        <p class="empty-state">No subscription packages found.</p>
                    @endforelse
                </div>
            </section>

            @if ($showPackageForm || $editingId)
                <form wire:submit="save" class="module-panel subscription-side-panel">
                    <div class="subscription-panel-heading">
                        <div>
                            <p class="dashboard-eyebrow">{{ $editingId ? 'Edit' : 'Create' }}</p>
                            <h3>{{ $editingId ? 'Edit Package' : 'New Package' }}</h3>
                        </div>
                        <button type="button" wire:click="cancelPackageForm" class="btn-secondary">Close</button>
                    </div>
                    <div class="form-grid form-grid--single">
                        <label class="field">
                            <span>Package Name</span>
                            <input type="text" wire:model="name" placeholder="Silver">
                            @error('name') <small>{{ $message }}</small> @enderror
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
                        <div class="form-grid">
                            <label class="field">
                                <span>Validity Months</span>
                                <input type="number" min="1" wire:model="validity_months">
                                @error('validity_months') <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Usage Limit</span>
                                <input type="number" min="1" wire:model="usage_limit">
                                @error('usage_limit') <small>{{ $message }}</small> @enderror
                            </label>
                        </div>
                        <label class="field">
                            <span>Amount</span>
                            <input type="number" min="0.01" step="0.01" wire:model="amount">
                            @error('amount') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" wire:model="pickup_included">
                            <span>Pickup Included</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" wire:model="is_active">
                            <span>Status Active</span>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="button" wire:click="cancelPackageForm" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">{{ $editingId ? 'Update Package' : 'Save Package' }}</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    @if ($activeTab === 'assign')
        <section class="subscription-assign-layout">
            <form wire:submit="assignSubscription" class="module-panel">
                <div class="subscription-panel-heading">
                    <div>
                        <p class="dashboard-eyebrow">Assignment</p>
                        <h3>Assign Customer Subscription</h3>
                    </div>
                    <button type="button" wire:click="resetSubscriptionForm" class="btn-secondary">Reset</button>
                </div>

                <div class="form-grid form-grid--single">
                    <div class="subscription-picker">
                        <label class="field">
                            <span>Customer</span>
                            <input type="search" wire:model.live.debounce.250ms="customerSearch" placeholder="Search by name, phone, or code">
                            @error('subscription_customer_id') <small>{{ $message }}</small> @enderror
                        </label>
                        @if ($selectedCustomer)
                            <div class="subscription-selected">
                                <span>{{ $selectedCustomer->customer_code }} - {{ $selectedCustomer->name }} - {{ $selectedCustomer->phone }}</span>
                                <button type="button" wire:click="clearSubscriptionCustomer">Change</button>
                            </div>
                        @else
                            <div class="subscription-picker-results">
                                @forelse ($customers as $customer)
                                    <button type="button" wire:click="selectSubscriptionCustomer({{ $customer->id }})">
                                        <strong>{{ $customer->name }}</strong>
                                        <span>{{ $customer->customer_code }} - {{ $customer->phone }}</span>
                                    </button>
                                @empty
                                    <p>No matching customers.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>

                    <div class="subscription-picker">
                        <label class="field">
                            <span>Package</span>
                            <input type="search" wire:model.live.debounce.250ms="packageSearch" placeholder="Search active package or service">
                            @error('subscription_plan_id') <small>{{ $message }}</small> @enderror
                        </label>
                        @if ($selectedPackage)
                            <div class="subscription-selected">
                                <span>{{ $selectedPackage->name }} - {{ $selectedPackage->service?->name }} - {{ $selectedPackage->usage_limit ?: $selectedPackage->wash_limit }} uses</span>
                                <button type="button" wire:click="clearSubscriptionPackage">Change</button>
                            </div>
                        @else
                            <div class="subscription-picker-results">
                                @forelse ($activePackages as $package)
                                    <button type="button" wire:click="selectSubscriptionPackage({{ $package->id }})">
                                        <strong>{{ $package->name }}</strong>
                                        <span>{{ $package->service?->name }} - {{ $package->usage_limit ?: $package->wash_limit }} uses - GHS {{ number_format((float) ($package->amount ?: $package->price), 2) }}</span>
                                    </button>
                                @empty
                                    <p>No complete active packages.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>

                    <div class="form-grid">
                        <label class="field">
                            <span>Start Date</span>
                            <input type="date" wire:model="subscription_starts_at">
                            @error('subscription_starts_at') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="toggle-field subscription-auto-renew">
                            <input type="checkbox" wire:model="subscription_auto_renew">
                            <span>Auto Renew</span>
                        </label>
                    </div>

                    <label class="field">
                        <span>Remarks</span>
                        <textarea rows="3" wire:model="subscription_remarks"></textarea>
                        @error('subscription_remarks') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Assign Subscription</button>
                </div>
            </form>

            <section class="module-panel subscription-assignment-preview">
                <p class="dashboard-eyebrow">Ready check</p>
                <h3>Before assigning</h3>
                <div class="subscription-ready-list">
                    <span class="{{ $selectedCustomer ? 'is-ready' : '' }}">{{ $selectedCustomer ? 'Customer selected' : 'Choose a customer' }}</span>
                    <span class="{{ $selectedPackage ? 'is-ready' : '' }}">{{ $selectedPackage ? 'Package selected' : 'Choose a package' }}</span>
                    <span class="{{ $subscription_starts_at ? 'is-ready' : '' }}">{{ $subscription_starts_at ? 'Start date set' : 'Set start date' }}</span>
                </div>
                @if ($selectedPackage)
                    <div class="subscription-mini-summary">
                        <strong>{{ $selectedPackage->name }}</strong>
                        <span>{{ $selectedPackage->service?->name }}</span>
                        <span>{{ $selectedPackage->validity_months ?: max(1, (int) ceil(((int) $selectedPackage->validity_days) / 30)) }} months</span>
                        <span>{{ $selectedPackage->usage_limit ?: $selectedPackage->wash_limit }} uses</span>
                    </div>
                @endif
            </section>
        </section>
    @endif

    @if ($activeTab === 'subscriptions' || $activeTab === 'expiring')
        <section class="module-panel subscription-directory">
            <div class="subscription-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">{{ $activeTab === 'expiring' ? 'Due work' : 'Customers' }}</p>
                    <h3>{{ $activeTab === 'expiring' ? 'Expiring and Exhausted Subscriptions' : 'Customer Subscriptions' }}</h3>
                </div>
                <button type="button" wire:click="expireDueSubscriptions" class="btn-secondary">Expire Due</button>
            </div>

            <div class="subscription-directory__tools">
                <label class="field">
                    <span>Search</span>
                    <input type="search" wire:model.live.debounce.250ms="subscriptionSearch" placeholder="Customer, phone, package, or number">
                </label>
                <div class="subscription-filter-bar">
                    <button type="button" wire:click="setSubscriptionFilter('active')" class="{{ $subscriptionFilter === 'active' ? 'is-active' : '' }}">Active</button>
                    <button type="button" wire:click="setSubscriptionFilter('due_7')" class="{{ $subscriptionFilter === 'due_7' ? 'is-active' : '' }}">7 days</button>
                    <button type="button" wire:click="setSubscriptionFilter('due_30')" class="{{ $subscriptionFilter === 'due_30' ? 'is-active' : '' }}">30 days</button>
                    <button type="button" wire:click="setSubscriptionFilter('exhausted')" class="{{ $subscriptionFilter === 'exhausted' ? 'is-active' : '' }}">Exhausted</button>
                    <button type="button" wire:click="setSubscriptionFilter('cancelled_expired')" class="{{ $subscriptionFilter === 'cancelled_expired' ? 'is-active' : '' }}">Closed</button>
                    <button type="button" wire:click="setSubscriptionFilter('all')" class="{{ $subscriptionFilter === 'all' ? 'is-active' : '' }}">All</button>
                </div>
            </div>

            <div class="subscription-detail-layout {{ $showSubscriptionEditor ? 'has-editor' : '' }}">
                <div class="subscription-customer-list">
                    @forelse ($customerSubscriptions as $subscription)
                        @php
                            $limit = max($subscription->usageLimit(), 1);
                            $remaining = $subscription->remainingUses();
                            $usedPercent = min(100, max(0, (int) round((($limit - $remaining) / $limit) * 100)));
                            $isDue = $subscription->status === 'active' && $subscription->ends_at && $subscription->ends_at->between(today(), today()->addDays(30));
                        @endphp
                        <button type="button" wire:click="selectSubscription({{ $subscription->id }})" class="subscription-customer-card {{ $selectedSubscription?->id === $subscription->id ? 'is-selected' : '' }} {{ $isDue ? 'is-due' : '' }}" wire:key="subscription-{{ $subscription->id }}">
                            <span class="subscription-customer-card__top">
                                <strong>{{ $subscription->customer?->name ?? 'Customer' }}</strong>
                                <em class="badge {{ $subscription->status === 'active' ? 'badge--success' : 'badge--muted' }}">{{ ucfirst($subscription->status) }}</em>
                            </span>
                            <span>{{ $subscription->plan?->name ?? 'Package' }} - {{ $subscription->plan?->service?->name ?? 'Service' }}</span>
                            <span class="subscription-customer-card__chips">
                                <em>{{ $subscription->subscription_no }}</em>
                                <em>{{ $remaining }} / {{ $limit }} uses left</em>
                                <em>{{ $subscription->ends_at?->format('M d, Y') }}</em>
                            </span>
                            <span class="subscription-usage-bar"><i style="width: {{ $usedPercent }}%"></i></span>
                        </button>
                    @empty
                        <p class="empty-state">No customer subscriptions found.</p>
                    @endforelse
                </div>

                <aside class="subscription-detail-panel">
                    @if ($selectedSubscription)
                        @php
                            $limit = max($selectedSubscription->usageLimit(), 1);
                            $remaining = $selectedSubscription->remainingUses();
                            $used = max(0, $limit - $remaining);
                            $usedPercent = min(100, max(0, (int) round(($used / $limit) * 100)));
                        @endphp
                        <div class="subscription-row-title">
                            <h3>{{ $selectedSubscription->customer?->name ?? 'Customer' }}</h3>
                            <span class="badge {{ $selectedSubscription->status === 'active' ? 'badge--success' : 'badge--muted' }}">{{ ucfirst($selectedSubscription->status) }}</span>
                        </div>
                        <p>{{ $selectedSubscription->plan?->name ?? 'Package' }} - {{ $selectedSubscription->plan?->service?->name ?? 'Service' }}</p>
                        <div class="subscription-detail-grid">
                            <span><strong>{{ $selectedSubscription->subscription_no }}</strong> Number</span>
                            <span><strong>{{ $remaining }}</strong> Uses left</span>
                            <span><strong>{{ $used }}</strong> Uses consumed</span>
                            <span><strong>{{ $selectedSubscription->auto_renew ? 'Auto' : 'Manual' }}</strong> Renewal</span>
                        </div>
                        <div class="subscription-detail-dates">
                            <span>{{ $selectedSubscription->starts_at?->format('M d, Y') }}</span>
                            <i></i>
                            <span>{{ $selectedSubscription->ends_at?->format('M d, Y') }}</span>
                        </div>
                        <span class="subscription-usage-bar subscription-usage-bar--large"><i style="width: {{ $usedPercent }}%"></i></span>
                        @if ($selectedSubscription->remarks)
                            <p class="subscription-remarks">{{ $selectedSubscription->remarks }}</p>
                        @endif
                        <div class="subscription-quick-actions">
                            <button type="button" wire:click="editSubscription({{ $selectedSubscription->id }})" class="btn-secondary">Edit Subscription</button>
                            <button type="button" wire:click="extendSubscription({{ $selectedSubscription->id }}, 30)" class="btn-secondary">Extend 30 Days</button>
                            <button type="button" wire:click="addSubscriptionUses({{ $selectedSubscription->id }}, 1)" class="btn-secondary">Add 1 Use</button>
                            <button type="button" wire:click="renewSubscription({{ $selectedSubscription->id }})" class="btn-secondary">Renew</button>
                            @if ($selectedSubscription->status !== 'cancelled')
                                <button type="button" wire:click="cancelSubscription({{ $selectedSubscription->id }})" wire:confirm="Cancel this subscription?" class="btn-danger">Cancel</button>
                            @endif
                        </div>
                    @else
                        <p class="empty-state">Select a customer subscription to see details.</p>
                    @endif
                </aside>

                @if ($showSubscriptionEditor)
                    <form wire:submit="updateSubscription" class="subscription-detail-panel subscription-editor-panel">
                        <div class="subscription-panel-heading">
                            <div>
                                <p class="dashboard-eyebrow">Admin update</p>
                                <h3>Edit Subscription</h3>
                            </div>
                            <button type="button" wire:click="cancelSubscriptionEditor" class="btn-secondary">Close</button>
                        </div>

                        <div class="subscription-picker">
                            <label class="field">
                                <span>Customer</span>
                                <input type="search" wire:model.live.debounce.250ms="editCustomerSearch" placeholder="Search customer">
                                @error('edit_subscription_customer_id') <small>{{ $message }}</small> @enderror
                            </label>
                            @if ($selectedEditCustomer)
                                <div class="subscription-selected">
                                    <span>{{ $selectedEditCustomer->customer_code }} - {{ $selectedEditCustomer->name }} - {{ $selectedEditCustomer->phone }}</span>
                                    <button type="button" wire:click="clearEditSubscriptionCustomer">Change</button>
                                </div>
                            @else
                                <div class="subscription-picker-results">
                                    @forelse ($editCustomers as $customer)
                                        <button type="button" wire:click="selectEditSubscriptionCustomer({{ $customer->id }})">
                                            <strong>{{ $customer->name }}</strong>
                                            <span>{{ $customer->customer_code }} - {{ $customer->phone }}</span>
                                        </button>
                                    @empty
                                        <p>No matching customers.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>

                        <div class="subscription-picker">
                            <label class="field">
                                <span>Package</span>
                                <input type="search" wire:model.live.debounce.250ms="editPackageSearch" placeholder="Search package">
                                @error('edit_subscription_plan_id') <small>{{ $message }}</small> @enderror
                            </label>
                            @if ($selectedEditPackage)
                                <div class="subscription-selected">
                                    <span>{{ $selectedEditPackage->name }} - {{ $selectedEditPackage->service?->name }} - {{ $selectedEditPackage->usage_limit ?: $selectedEditPackage->wash_limit }} uses</span>
                                    <button type="button" wire:click="clearEditSubscriptionPackage">Change</button>
                                </div>
                            @else
                                <div class="subscription-picker-results">
                                    @forelse ($editPackages as $package)
                                        <button type="button" wire:click="selectEditSubscriptionPackage({{ $package->id }})">
                                            <strong>{{ $package->name }}</strong>
                                            <span>{{ $package->service?->name }} - {{ $package->usage_limit ?: $package->wash_limit }} uses</span>
                                        </button>
                                    @empty
                                        <p>No complete packages.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>

                        <div class="form-grid">
                            <label class="field">
                                <span>Start Date</span>
                                <input type="date" wire:model="edit_subscription_starts_at">
                                @error('edit_subscription_starts_at') <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Expiry Date</span>
                                <input type="date" wire:model="edit_subscription_ends_at">
                                @error('edit_subscription_ends_at') <small>{{ $message }}</small> @enderror
                            </label>
                        </div>

                        <label class="field">
                            <span>Status</span>
                            <select wire:model="edit_subscription_status">
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="exhausted">Exhausted</option>
                            </select>
                            @error('edit_subscription_status') <small>{{ $message }}</small> @enderror
                        </label>

                        <div class="form-grid">
                            <label class="field">
                                <span>Usage Limit</span>
                                <input type="number" min="1" wire:model="edit_subscription_usage_limit">
                                @error('edit_subscription_usage_limit') <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Used</span>
                                <input type="number" min="0" wire:model="edit_subscription_used_uses">
                                @error('edit_subscription_used_uses') <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Remaining</span>
                                <input type="number" min="0" wire:model="edit_subscription_remaining_uses">
                                @error('edit_subscription_remaining_uses') <small>{{ $message }}</small> @enderror
                            </label>
                        </div>

                        <label class="toggle-field">
                            <input type="checkbox" wire:model="edit_subscription_auto_renew">
                            <span>Auto Renew</span>
                        </label>

                        <label class="toggle-field">
                            <input type="checkbox" wire:model="edit_confirm_identity_change">
                            <span>Confirm customer/package correction after usage</span>
                            @error('edit_confirm_identity_change') <small>{{ $message }}</small> @enderror
                        </label>

                        <label class="field">
                            <span>Reason</span>
                            <input type="text" wire:model="edit_subscription_adjustment_reason" placeholder="Required for date, status, usage, customer, or package changes">
                            @error('edit_subscription_adjustment_reason') <small>{{ $message }}</small> @enderror
                        </label>

                        <label class="field">
                            <span>Remarks</span>
                            <textarea rows="3" wire:model="edit_subscription_remarks"></textarea>
                            @error('edit_subscription_remarks') <small>{{ $message }}</small> @enderror
                        </label>

                        <div class="form-actions">
                            <button type="button" wire:click="cancelSubscriptionEditor" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Update Subscription</button>
                        </div>
                    </form>
                @endif
            </div>
        </section>
    @endif
</div>
