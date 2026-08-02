<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 11</p>
            <h2>Order Management</h2>
        </div>
        <div class="module-actions">
            <span class="notice">Next: {{ $nextOrderNumber }}</span>
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
            @if (session('error'))
                <span class="notice notice--error">{{ session('error') }}</span>
            @endif
        </div>
    </section>

    <nav class="orders-tabs" aria-label="Order workbench tabs">
        <button type="button" wire:click="setActiveTab('create')" class="{{ $activeTab === 'create' ? 'is-active' : '' }}">
            {{ $editingId ? 'Edit Order' : 'Create Order' }}
        </button>
        <button type="button" wire:click="setActiveTab('queue')" class="{{ $activeTab === 'queue' ? 'is-active' : '' }}">
            Order Queue <span>{{ $orders->total() }}</span>
        </button>
    </nav>

    @if ($activeTab === 'create')
        <section class="orders-workbench orders-workbench--create">
        <form wire:submit="save" class="module-panel order-form-panel">
            <div class="order-panel-title">
                <div>
                    <h3>{{ $editingId ? 'Edit Order' : 'Place New Order' }}</h3>
                    <p>{{ $editingId ? 'Update customer, state, rows, and totals for this order.' : 'Build a ticket from customer, service, and product rows.' }}</p>
                </div>
                <span>{{ $editingId ? 'Editing' : count($rows).' row'.(count($rows) === 1 ? '' : 's') }}</span>
            </div>
            <section class="order-form-section">
                <div class="order-form-section__title">
                    <span>1</span>
                    <div>
                        <h4>Customer</h4>
                        <p>Select the account and starting workflow state.</p>
                    </div>
                </div>
            <div class="form-grid">
                <div class="field customer-search-field">
                    <span>Customer</span>
                    <div class="customer-search-box">
                        <input type="search" wire:model.live.debounce.250ms="customerSearch" placeholder="Search customer number, name, phone, or email">
                        @if ($customer_id)
                            <button type="button" wire:click="clearCustomer" class="btn-secondary">Clear</button>
                        @endif
                    </div>
                    @if (! $customer_id && $customerSearch !== '')
                        <div class="customer-results">
                            @forelse ($customerResults as $customer)
                                <button type="button" wire:click="selectCustomer({{ $customer->id }})">
                                    <strong>{{ $customer->customer_code }}</strong>
                                    <span>{{ $customer->name }}</span>
                                    <small>{{ $customer->phone }}{{ $customer->email ? ' - '.$customer->email : '' }}</small>
                                </button>
                            @empty
                                <p>No customers match your search.</p>
                            @endforelse
                        </div>
                    @elseif ($customer_id)
                        <p class="selected-customer">{{ $selectedCustomerLabel }}</p>
                    @endif
                    @error('customer_id') <small>{{ $message }}</small> @enderror
                </div>
                <label class="field">
                    <span>Order State</span>
                    <select wire:model.live="status">
                        @foreach ($statuses as $value => $label)
                            @if ($value !== 'cancelled')
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('status') <small>{{ $message }}</small> @enderror
                </label>
            </div>
            </section>

            @if ($customer_id)
                <section class="module-panel order-form-section order-form-section--nested">
                    <div class="order-form-section__title">
                        <span>2</span>
                        <div>
                            <h4>Billing</h4>
                            <p>Apply a subscription only when the order matches the package service.</p>
                        </div>
                    </div>
                    <div class="service-row__title">
                        <h3>Subscription Billing</h3>
                        <span class="badge {{ $useSubscription ? 'badge--success' : 'badge--muted' }}">{{ $useSubscription ? 'Applied' : 'Cash order' }}</span>
                    </div>
                    <div class="form-grid">
                        <label class="toggle-field">
                            <input type="checkbox" wire:model.live="useSubscription" @disabled($activeCustomerSubscriptions->isEmpty())>
                            <span>Use active subscription</span>
                        </label>
                        @if ($useSubscription)
                            <label class="field">
                                <span>Customer Subscription</span>
                                <select wire:model.live="customer_subscription_id">
                                    <option value="">Select subscription</option>
                                    @foreach ($activeCustomerSubscriptions as $subscription)
                                        <option value="{{ $subscription->id }}">
                                            {{ $subscription->plan?->name }} - {{ $subscription->plan?->service?->name }} - {{ $subscription->remainingUses() }} uses left
                                        </option>
                                    @endforeach
                                </select>
                                @error('customer_subscription_id') <small>{{ $message }}</small> @enderror
                            </label>
                        @elseif ($activeCustomerSubscriptions->isEmpty())
                            <p class="empty-state">No active subscription with remaining uses for this customer.</p>
                        @endif
                    </div>
                </section>
            @endif

            <section class="order-form-section">
                <div class="order-form-section__title">
                    <span>{{ $customer_id ? '3' : '2' }}</span>
                    <div>
                        <h4>Fulfillment</h4>
                        <p>Schedule pickup or delivery tasks while creating the ticket.</p>
                    </div>
                </div>
            <div class="form-grid">
                <label class="field">
                    <span>Pickup Needed</span>
                    <select wire:model.live="requestPickup">
                        <option value="0">No pickup task</option>
                        <option value="1">Schedule door pickup</option>
                    </select>
                </label>
                <label class="field">
                    <span>Delivery Needed</span>
                    <select wire:model.live="requestDelivery">
                        <option value="0">No delivery task</option>
                        <option value="1">Schedule door delivery</option>
                    </select>
                </label>
                @if ($requestPickup === '1')
                    <label class="field">
                        <span>Pickup Date</span>
                        <input type="date" wire:model="pickup_date">
                    </label>
                    <label class="field">
                        <span>Pickup Time</span>
                        <input type="time" wire:model="pickup_time">
                    </label>
                @endif
                @if ($requestDelivery === '1')
                    <label class="field">
                        <span>Delivery Date</span>
                        <input type="date" wire:model="delivery_date">
                    </label>
                    <label class="field">
                        <span>Delivery Time</span>
                        <input type="time" wire:model="delivery_time">
                    </label>
                @endif
            </div>
            </section>

            <section class="order-form-section">
                <div class="order-form-section__title">
                    <span>{{ $customer_id ? '4' : '3' }}</span>
                    <div>
                        <h4>Items</h4>
                        <p>Add product and service rows. The total updates as rows change.</p>
                    </div>
                </div>
            <div class="order-lines">
                <div class="order-lines__head">
                    <span>Product</span>
                    <span>Service</span>
                    <span>Quantity</span>
                    <span>Unit Price</span>
                    <span>Amount</span>
                    <span></span>
                </div>
                @foreach ($rows as $index => $row)
                    <div class="order-lines__row" wire:key="order-row-{{ $index }}">
                        <label class="field">
                            <span>Product</span>
                            <select wire:model.live="rows.{{ $index }}.product_id">
                                <option value="">Product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                            <small>State: {{ $statuses[$row['status'] ?? $status] ?? ucfirst(str_replace('_', ' ', $row['status'] ?? $status)) }}</small>
                            @error("rows.$index.product_id") <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="field">
                            <span>Service</span>
                            <select wire:model.live="rows.{{ $index }}.laundry_service_id">
                                <option value="">Service</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                                @endforeach
                            </select>
                            @error("rows.$index.laundry_service_id") <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="field">
                            <span>Quantity</span>
                            <input type="number" min="1" step="1" inputmode="numeric" wire:model.live="rows.{{ $index }}.quantity">
                            @error("rows.$index.quantity") <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="field">
                            <span>Unit Price</span>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                wire:model.live="rows.{{ $index }}.unit_price"
                                @readonly(! $canOverridePrices || empty($row['price_override_enabled']))
                            >
                            @if (! empty($row['original_unit_price']))
                                <small>Rate: GHS {{ number_format((float) $row['original_unit_price'], 2) }}</small>
                            @endif
                            @if ($canOverridePrices)
                                @if (empty($row['price_override_enabled']))
                                    <button type="button" wire:click="enablePriceOverride({{ $index }})" class="btn-secondary">Override</button>
                                @else
                                    <button type="button" wire:click="clearPriceOverride({{ $index }})" class="btn-secondary">Use Rate</button>
                                @endif
                            @endif
                            @error("rows.$index.unit_price") <small>{{ $message }}</small> @enderror
                        </label>
                        @if (! empty($row['price_override_enabled']))
                            <label class="field">
                                <span>Override Reason</span>
                                <input type="text" wire:model.live="rows.{{ $index }}.price_override_reason" placeholder="Required reason">
                                @error("rows.$index.price_override_reason") <small>{{ $message }}</small> @enderror
                            </label>
                        @endif
                        <label class="field order-amount-field">
                            <span>Amount</span>
                            <output>GHS {{ number_format((float) ($row['amount'] ?? 0), 2) }}</output>
                        </label>
                        <button type="button" wire:click="removeRow({{ $index }})" class="btn-danger order-remove-btn" title="Remove row" aria-label="Remove product row">
                            <span>Remove</span>
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="form-actions form-actions--between order-total-actions">
                <button type="button" wire:click="addRow" class="btn-secondary">Add Product Row</button>
                <div class="order-total-box">
                    <div><span>Subtotal</span><strong>GHS {{ number_format($subtotal, 2) }}</strong></div>
                    <div><span>Tax</span><strong>GHS {{ number_format($tax, 2) }}</strong></div>
                    @if ($useSubscription)
                        <div><span>Subscription</span><strong>- GHS {{ number_format($subscriptionDiscount, 2) }}</strong></div>
                    @endif
                    <div><span>Total</span><strong>GHS {{ number_format($total, 2) }}</strong></div>
                    @if ($useSubscription)
                        <div><span>Payable</span><strong>GHS {{ number_format($payableTotal, 2) }}</strong></div>
                    @endif
                </div>
            </div>
            </section>

            <div class="order-helper-grid">
                <div>
                    <span>Customer</span>
                    <strong>{{ $selectedCustomerLabel ?: 'Not selected' }}</strong>
                </div>
                <div>
                    <span>Items</span>
                    <strong>{{ collect($rows)->sum(fn ($row) => (int) ($row['quantity'] ?? 0)) }}</strong>
                </div>
                <div>
                    <span>State</span>
                    <strong>{{ $statuses[$status] ?? ucfirst($status) }}</strong>
                </div>
            </div>

            <label class="field">
                <span>Notes</span>
                <textarea rows="3" wire:model="notes"></textarea>
            </label>

            <div class="form-actions">
                <button type="button" wire:click="resetOrderForm" class="btn-secondary">{{ $editingId ? 'Cancel Edit' : 'Reset' }}</button>
                <button type="submit" class="btn-primary">{{ $editingId ? 'Update Order' : 'Save Order' }}</button>
            </div>
        </form>
        </section>
    @endif

    @if ($activeTab === 'queue')
        <section class="orders-workbench orders-workbench--queue">
        <section class="module-panel module-panel--list order-queue-panel">
            <div class="order-queue-header">
                <div>
                    <p class="dashboard-eyebrow">Live Queue</p>
                    <h3>Orders</h3>
                </div>
                <span>{{ $orders->total() }} records</span>
            </div>

            <div class="order-stat-strip">
                <div><span>Today</span><strong>{{ $orderStats['today'] }}</strong></div>
                <div><span>Active</span><strong>{{ $orderStats['active'] }}</strong></div>
                <div><span>Ready/Dispatch</span><strong>{{ $orderStats['ready'] }}</strong></div>
                <div><span>Unpaid</span><strong>{{ $orderStats['unpaid'] }}</strong></div>
                <div><span>Exceptions</span><strong>{{ $orderStats['exceptions'] }}</strong></div>
                <div><span>Balance</span><strong>GHS {{ number_format($orderStats['balance'], 2) }}</strong></div>
            </div>

            <div class="order-filter-chips">
                <button type="button" wire:click="setStatusFilter('all')" class="{{ $statusFilter === 'all' ? 'is-active' : '' }}">All</button>
                @foreach (['unpaid', 'part_paid', 'paid', 'needs_pickup', 'ready_delivery', 'has_exceptions', 'completed_today'] as $quickFilter)
                    <button type="button" wire:click="setStatusFilter('{{ $quickFilter }}')" class="{{ $statusFilter === $quickFilter ? 'is-active' : '' }}">
                        {{ $orderFilters[$quickFilter] }}
                    </button>
                @endforeach
            </div>

            <div class="list-toolbar order-list-toolbar">
                <label class="field">
                    <span>Search</span>
                    <input type="search" wire:model.live="search" placeholder="Order number or customer">
                </label>
                <label class="field">
                    <span>Status</span>
                    <select wire:model.live="statusFilter">
                        @foreach ($orderFilters as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                        <option disabled>──────────</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="service-list order-queue-list">
                @forelse ($orders as $order)
                    @php
                        $statusLabel = $statuses[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status));
                        $paymentLabel = ucfirst(str_replace('_', ' ', $order->payment_status));
                        $statusClass = 'badge--status-'.$order->status;
                        $isCompleted = $order->status === 'delivered' || (bool) $order->completed_at;
                        $isCancelled = $order->status === 'cancelled';
                        $hasPayment = (float) $order->amount_paid > 0 || (int) $order->payments_count > 0;
                        $warnings = $this->orderWarnings($order);
                        $statusActions = $isCancelled ? [] : $this->statusProgressActions($order);
                        $primaryStatus = array_key_first($statusActions);
                        $isDeleteLocked = $hasPayment || (int) $order->garment_tags_count > 0 || (int) $order->pickup_tasks_count > 0 || (int) $order->delivery_tasks_count > 0;
                        $paymentProgress = (float) $order->total > 0 ? min(100, ((float) $order->amount_paid / (float) $order->total) * 100) : 0;
                        $isSelected = $selectedQueueOrder?->id === $order->id;
                    @endphp
                    <article class="service-row order-queue-row {{ $isCancelled ? 'order-queue-row--cancelled' : '' }} {{ $warnings ? 'order-queue-row--problem' : '' }} {{ $isSelected ? 'order-queue-row--selected' : '' }}">
                        <button type="button" wire:click="selectQueueOrder({{ $order->id }})" class="order-queue-main">
                            <div class="order-card-top">
                                <div>
                                    <div class="order-title-line">
                                        <h3>{{ $order->order_number }}</h3>
                                        <div class="order-card-badges">
                                            <span class="badge {{ $isCancelled ? 'badge--cancelled' : $statusClass }}">{{ $statusLabel }}</span>
                                            <span class="badge {{ $order->payment_status === 'paid' ? 'badge--success' : 'badge--warning' }}">{{ $paymentLabel }}</span>
                                        </div>
                                    </div>
                                    <p>{{ $order->customer?->name ?? 'Customer' }} <span>{{ $order->items->count() }} item row{{ $order->items->count() === 1 ? '' : 's' }}</span></p>
                                </div>
                            </div>
                            @if ($isCancelled)
                                <p class="order-state-note">This order is cancelled and cannot be processed. Payment, receipt, and workflow actions are disabled.</p>
                            @endif
                            @if ($warnings)
                                <div class="order-warning-list">
                                    @foreach ($warnings as $warning)
                                        <span>{{ $warning }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="order-progress">
                                <div>
                                    <span>Payment</span>
                                    <strong>{{ number_format($paymentProgress, 0) }}%</strong>
                                </div>
                                <span aria-hidden="true"><b style="width: {{ $paymentProgress }}%"></b></span>
                            </div>
                            <div class="order-card-body">
                                <dl class="order-money-grid">
                                    <div><dt>Total</dt><dd>GHS {{ number_format((float) $order->total, 2) }}</dd></div>
                                    <div><dt>Paid</dt><dd>GHS {{ number_format((float) $order->amount_paid, 2) }}</dd></div>
                                    <div class="{{ (float) $order->balance > 0 ? 'order-money-due' : '' }}"><dt>Balance</dt><dd>GHS {{ number_format((float) $order->balance, 2) }}</dd></div>
                                </dl>
                                <p class="order-card-date">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </button>
                        <div class="row-actions">
                            @if ($primaryStatus)
                                <button type="button" wire:click="advanceOrderStatus({{ $order->id }}, '{{ $primaryStatus }}')" class="order-primary-action order-primary-action--status">{{ $statusActions[$primaryStatus] }}</button>
                            @elseif (! $isCancelled && (float) $order->balance > 0)
                                <button type="button" wire:click="openPaymentModal({{ $order->id }})" class="order-primary-action order-primary-action--pay">Pay</button>
                            @elseif ($isCompleted)
                                <a href="{{ route('receipts.orders.show', ['order' => $order, 'print' => 1]) }}" target="_blank" class="order-primary-action order-primary-action--print">Print</a>
                            @endif
                            @if (! $isCancelled && (float) $order->balance > 0 && $paymentProgress < 100 && $primaryStatus)
                                <button type="button" wire:click="openPaymentModal({{ $order->id }})" class="order-secondary-action">Pay</button>
                            @elseif (! $isCancelled && (float) $order->balance <= 0 && ! $isCompleted)
                                <a href="{{ route('receipts.orders.show', ['order' => $order]) }}" target="_blank" class="order-secondary-action">Receipt</a>
                            @endif
                            <details class="order-action-menu">
                                <summary>Actions</summary>
                                <div>
                                    @if ($isCancelled)
                                        <span class="order-menu-heading">Inactive order</span>
                                        <span class="order-menu-disabled">Payment disabled</span>
                                        <span class="order-menu-disabled">Workflow disabled</span>
                                        <button type="button" wire:click="showTimeline({{ $order->id }})">View Timeline</button>
                                    @else
                                        @if ((float) $order->balance > 0 && $order->payment_status !== 'paid')
                                            <button type="button" wire:click="openPaymentModal({{ $order->id }})">Record Payment</button>
                                        @else
                                            <span class="order-menu-disabled">Paid in full</span>
                                        @endif
                                        <span class="order-menu-heading">Workflow</span>
                                        @foreach ($statusActions as $targetStatus => $actionLabel)
                                            @if ($targetStatus !== $primaryStatus)
                                                <button type="button" wire:click="advanceOrderStatus({{ $order->id }}, '{{ $targetStatus }}')">{{ $actionLabel }}</button>
                                            @endif
                                        @endforeach
                                        <button type="button" wire:click="openTagsModal({{ $order->id }})">{{ $isCompleted ? 'Preview Tags' : ((int) $order->garment_tags_count > 0 ? 'Edit Tags' : 'Generate Tags') }}</button>
                                        <button type="button" wire:click="openPickupModal({{ $order->id }})">{{ $isCompleted ? 'Preview Pickup' : ((int) $order->pickup_tasks_count > 0 ? 'Edit Pickup' : 'Schedule Pickup') }}</button>
                                        <button type="button" wire:click="openDeliveryModal({{ $order->id }})">{{ $isCompleted ? 'Preview Delivery' : ((int) $order->delivery_tasks_count > 0 ? 'Edit Delivery' : 'Schedule Delivery') }}</button>
                                        <span class="order-menu-heading">Records</span>
                                        <button type="button" wire:click="showTimeline({{ $order->id }})">View Timeline</button>
                                        @if ($isCompleted)
                                            <a href="{{ route('receipts.orders.show', ['order' => $order, 'print' => 1]) }}" target="_blank">Print Receipt</a>
                                        @else
                                            <a href="{{ route('receipts.orders.show', ['order' => $order]) }}" target="_blank">Draft Receipt</a>
                                        @endif
                                        <span class="order-menu-heading">Admin</span>
                                        @if ($hasPayment)
                                            <span class="order-menu-disabled">Order locked: payment made</span>
                                            <button type="button" wire:click="openAdjustmentModal({{ $order->id }})">Request Adjustment</button>
                                        @else
                                            <button type="button" wire:click="edit({{ $order->id }})">Edit Order</button>
                                        @endif
                                    @endif
                                    @if ($isDeleteLocked)
                                        <span class="order-menu-disabled">Delete locked</span>
                                    @else
                                        <button type="button" wire:click="openDeleteModal({{ $order->id }})" class="is-danger">Delete Order</button>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">No orders found.</p>
                @endforelse
            </div>

            <div class="pagination-wrap">
                {{ $orders->links() }}
            </div>
        </section>

        <aside class="module-panel order-detail-panel">
            @if ($selectedQueueOrder)
                @php
                    $detailStatusLabel = $statuses[$selectedQueueOrder->status] ?? ucfirst(str_replace('_', ' ', $selectedQueueOrder->status));
                    $detailPaymentLabel = ucfirst(str_replace('_', ' ', $selectedQueueOrder->payment_status));
                    $detailIsCompleted = $selectedQueueOrder->status === 'delivered' || (bool) $selectedQueueOrder->completed_at;
                    $detailIsCancelled = $selectedQueueOrder->status === 'cancelled';
                    $detailHasPayment = (float) $selectedQueueOrder->amount_paid > 0 || (int) $selectedQueueOrder->payments_count > 0;
                    $detailWarnings = $this->orderWarnings($selectedQueueOrder);
                    $detailStatusActions = $detailIsCancelled ? [] : $this->statusProgressActions($selectedQueueOrder);
                    $detailPrimaryStatus = array_key_first($detailStatusActions);
                    $detailPaymentProgress = (float) $selectedQueueOrder->total > 0 ? min(100, ((float) $selectedQueueOrder->amount_paid / (float) $selectedQueueOrder->total) * 100) : 0;
                    $detailIsDeleteLocked = $detailHasPayment || (int) $selectedQueueOrder->garment_tags_count > 0 || (int) $selectedQueueOrder->pickup_tasks_count > 0 || (int) $selectedQueueOrder->delivery_tasks_count > 0;
                @endphp
                <div class="order-detail-header">
                    <div>
                        <p class="dashboard-eyebrow">Selected Order</p>
                        <h3>{{ $selectedQueueOrder->order_number }}</h3>
                        <span>{{ $selectedQueueOrder->customer?->name ?? 'Customer' }}</span>
                    </div>
                    <div class="order-card-badges">
                        <span class="badge {{ $detailIsCancelled ? 'badge--cancelled' : 'badge--status-'.$selectedQueueOrder->status }}">{{ $detailStatusLabel }}</span>
                        <span class="badge {{ $selectedQueueOrder->payment_status === 'paid' ? 'badge--success' : 'badge--warning' }}">{{ $detailPaymentLabel }}</span>
                    </div>
                </div>

                @if ($detailWarnings)
                    <div class="order-warning-list order-warning-list--detail">
                        @foreach ($detailWarnings as $warning)
                            <span>{{ $warning }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="order-progress order-progress--detail">
                    <div>
                        <span>Payment Progress</span>
                        <strong>{{ number_format($detailPaymentProgress, 0) }}%</strong>
                    </div>
                    <span aria-hidden="true"><b style="width: {{ $detailPaymentProgress }}%"></b></span>
                </div>

                <dl class="order-detail-money">
                    <div><dt>Total</dt><dd>GHS {{ number_format((float) $selectedQueueOrder->total, 2) }}</dd></div>
                    <div><dt>Paid</dt><dd>GHS {{ number_format((float) $selectedQueueOrder->amount_paid, 2) }}</dd></div>
                    <div class="{{ (float) $selectedQueueOrder->balance > 0 ? 'order-money-due' : '' }}"><dt>Balance</dt><dd>GHS {{ number_format((float) $selectedQueueOrder->balance, 2) }}</dd></div>
                </dl>

                <div class="order-detail-actions">
                    @if ($detailPrimaryStatus)
                        <button type="button" wire:click="advanceOrderStatus({{ $selectedQueueOrder->id }}, '{{ $detailPrimaryStatus }}')" class="order-primary-action order-primary-action--status">{{ $detailStatusActions[$detailPrimaryStatus] }}</button>
                    @elseif (! $detailIsCancelled && (float) $selectedQueueOrder->balance > 0)
                        <button type="button" wire:click="openPaymentModal({{ $selectedQueueOrder->id }})" class="order-primary-action order-primary-action--pay">Record Payment</button>
                    @elseif ($detailIsCompleted)
                        <a href="{{ route('receipts.orders.show', ['order' => $selectedQueueOrder, 'print' => 1]) }}" target="_blank" class="order-primary-action order-primary-action--print">Print Receipt</a>
                    @endif

                    <div class="order-detail-secondary">
                        @if (! $detailIsCancelled && (float) $selectedQueueOrder->balance > 0)
                            <button type="button" wire:click="openPaymentModal({{ $selectedQueueOrder->id }})" class="order-secondary-action">Pay</button>
                        @endif
                        <a href="{{ route('receipts.orders.show', ['order' => $selectedQueueOrder]) }}" target="_blank" class="order-secondary-action">Receipt</a>
                        <button type="button" wire:click="showTimeline({{ $selectedQueueOrder->id }})" class="order-secondary-action">Timeline</button>
                    </div>
                </div>

                <div class="order-detail-section">
                    <h4>Workflow</h4>
                    <div class="order-detail-button-grid">
                        @foreach ($detailStatusActions as $targetStatus => $actionLabel)
                            @if ($targetStatus !== $detailPrimaryStatus)
                                <button type="button" wire:click="advanceOrderStatus({{ $selectedQueueOrder->id }}, '{{ $targetStatus }}')">{{ $actionLabel }}</button>
                            @endif
                        @endforeach
                        <button type="button" wire:click="openTagsModal({{ $selectedQueueOrder->id }})">{{ $detailIsCompleted ? 'Preview Tags' : ((int) $selectedQueueOrder->garment_tags_count > 0 ? 'Edit Tags' : 'Generate Tags') }}</button>
                        <button type="button" wire:click="openPickupModal({{ $selectedQueueOrder->id }})">{{ $detailIsCompleted ? 'Preview Pickup' : ((int) $selectedQueueOrder->pickup_tasks_count > 0 ? 'Edit Pickup' : 'Schedule Pickup') }}</button>
                        <button type="button" wire:click="openDeliveryModal({{ $selectedQueueOrder->id }})">{{ $detailIsCompleted ? 'Preview Delivery' : ((int) $selectedQueueOrder->delivery_tasks_count > 0 ? 'Edit Delivery' : 'Schedule Delivery') }}</button>
                    </div>
                </div>

                <div class="order-detail-section">
                    <h4>Items</h4>
                    <div class="order-detail-items">
                        @foreach ($selectedQueueOrder->items as $item)
                            <div>
                                <strong>{{ $item->product?->name ?? $item->item_name }}</strong>
                                <span>{{ $item->service?->name ?? 'Service' }} x {{ number_format((float) $item->quantity, 0) }}</span>
                                <b>GHS {{ number_format((float) $item->line_total, 2) }}</b>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="order-detail-section">
                    <h4>Admin</h4>
                    <div class="order-detail-button-grid">
                        @if ($detailHasPayment)
                            <button type="button" wire:click="openAdjustmentModal({{ $selectedQueueOrder->id }})">Request Adjustment</button>
                        @else
                            <button type="button" wire:click="edit({{ $selectedQueueOrder->id }})">Edit Order</button>
                        @endif
                        @if ($detailIsDeleteLocked)
                            <span class="order-menu-disabled">Delete locked</span>
                        @else
                            <button type="button" wire:click="openDeleteModal({{ $selectedQueueOrder->id }})" class="is-danger">Delete Order</button>
                        @endif
                    </div>
                </div>
            @else
                <p class="empty-state">Select an order to view workflow details.</p>
            @endif
        </aside>
        </section>
    @endif

    @if ($modalOrder && $activeModal === 'payment')
        <div class="modal-backdrop order-created-preview-backdrop" wire:key="order-payment-modal">
            <form wire:submit="recordModalPayment" class="modal-panel order-action-modal order-action-modal--wide">
                <header class="modal-header">
                    <div>
                        <p class="dashboard-eyebrow">Record Payment</p>
                        <h3>{{ $modalOrder->order_number }}</h3>
                        <span>{{ $modalOrder->customer?->name ?? 'Customer' }}</span>
                    </div>
                    <button type="button" wire:click="closeActionModal" class="modal-close" aria-label="Close">x</button>
                </header>
                <div class="order-modal-summary">
                    <div><span>Total</span><strong>GHS {{ number_format((float) $modalOrder->total, 2) }}</strong></div>
                    <div><span>Paid</span><strong>GHS {{ number_format((float) $modalOrder->amount_paid, 2) }}</strong></div>
                    <div><span>Balance</span><strong>GHS {{ number_format((float) $modalOrder->balance, 2) }}</strong></div>
                </div>
                <div class="payment-helper">
                    <strong>Part payment and split payment are allowed.</strong>
                    <span>Use less than the balance to leave this order part-paid, or add rows when the customer pays with more than one method. Reference is the external transaction ID, POS auth code, bank transfer ref, cheque number, or receipt note for reconciliation.</span>
                </div>
                @if ($modalLoyaltySummary['enabled'])
                    <div class="payment-helper loyalty-helper">
                        <strong>{{ $modalLoyaltySummary['points'] }} loyalty points available</strong>
                        <span>Redeem up to {{ $modalLoyaltySummary['max_redeemable'] }} points on this order. Minimum redemption is {{ $modalLoyaltySummary['minimum'] }} points.</span>
                        <label class="field">
                            <span>Redeem Points</span>
                            <input type="number" min="0" step="1" max="{{ $modalLoyaltySummary['max_redeemable'] }}" wire:model.live="loyaltyRedeemPoints">
                            @error('loyaltyRedeemPoints') <small>{{ $message }}</small> @enderror
                        </label>
                        @if ($modalLoyaltySummary['redeem_value'] > 0)
                            <span>Loyalty credit: GHS {{ number_format((float) $modalLoyaltySummary['redeem_value'], 2) }}</span>
                        @endif
                    </div>
                @endif
                <div class="split-payment-list">
                    @foreach ($paymentLines as $index => $line)
                        <div class="split-payment-row" wire:key="payment-line-{{ $index }}">
                            <label class="field">
                                <span>Amount</span>
                                <input type="number" min="0" step="0.01" wire:model.live="paymentLines.{{ $index }}.amount">
                                @error("paymentLines.$index.amount") <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Method</span>
                                <select wire:model="paymentLines.{{ $index }}.method">
                                    @foreach ($paymentMethods as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error("paymentLines.$index.method") <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Reference</span>
                                <input type="text" wire:model="paymentLines.{{ $index }}.reference" placeholder="MoMo tx ID, POS auth, bank ref">
                                @error("paymentLines.$index.reference") <small>{{ $message }}</small> @enderror
                            </label>
                            @if (count($paymentLines) > 1)
                                <button type="button" wire:click="removePaymentLine({{ $index }})" class="btn-danger split-payment-remove">Remove</button>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('paymentLines') <p class="notice notice--error">{{ $message }}</p> @enderror
                <div class="split-payment-total">
                    <div><span>Total Tendered</span><strong>GHS {{ number_format((float) $modalPaymentTotal, 2) }}</strong></div>
                    <div><span>Balance After Payment</span><strong>GHS {{ number_format((float) $modalPaymentRemaining, 2) }}</strong></div>
                </div>
                <div class="form-grid">
                    <label class="field field--wide">
                        <span>Notes</span>
                        <input type="text" wire:model="payment_notes" placeholder="Optional internal note for this payment">
                        @error('payment_notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="form-actions">
                    <button type="button" wire:click="addPaymentLine" class="btn-secondary">Add Split Payment</button>
                    <button type="button" wire:click="closeActionModal" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    @endif

    @if ($modalOrder && in_array($activeModal, ['pickup', 'delivery'], true))
        @php
            $taskAction = $actionModalReadOnly ? 'Preview' : ($taskModalHasExisting ? 'Edit' : 'Schedule');
            $taskSubject = $activeModal === 'delivery' ? 'Delivery' : 'Pickup';
        @endphp
        <div class="modal-backdrop order-created-preview-backdrop" wire:key="order-task-modal">
            <form wire:submit="saveModalTask" class="modal-panel order-action-modal">
                <header class="modal-header">
                    <div>
                        <p class="dashboard-eyebrow">{{ $taskAction }} {{ $taskSubject }}</p>
                        <h3>{{ $modalOrder->order_number }}</h3>
                        <span>{{ $modalOrder->customer?->name ?? 'Customer' }}</span>
                    </div>
                    <button type="button" wire:click="closeActionModal" class="modal-close" aria-label="Close">x</button>
                </header>
                @if ($actionModalReadOnly)
                    <p class="notice notice--info">This order is completed, so {{ strtolower($taskSubject) }} details are shown as a preview.</p>
                    @unless ($taskModalHasExisting)
                        <p class="empty-state">No {{ strtolower($taskSubject) }} task was scheduled for this order.</p>
                    @endunless
                @endif
                <div class="form-grid">
                    <label class="field">
                        <span>Date</span>
                        <input type="date" wire:model="task_date" @disabled($actionModalReadOnly)>
                        @error('task_date') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Time</span>
                        <input type="time" wire:model="task_time" @disabled($actionModalReadOnly)>
                        @error('task_time') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field field--wide">
                        <span>Address</span>
                        <textarea rows="3" wire:model="task_address" @disabled($actionModalReadOnly)></textarea>
                        @error('task_address') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Assigned Staff</span>
                        <select wire:model="task_assigned_to" @disabled($actionModalReadOnly)>
                            <option value="">Unassigned</option>
                            @foreach ($deliveryStaff as $staffMember)
                                <option value="{{ $staffMember->id }}">{{ $staffMember->name }}</option>
                            @endforeach
                        </select>
                        @error('task_assigned_to') <small>{{ $message }}</small> @enderror
                    </label>
                    @if ($activeModal === 'delivery')
                        <label class="field">
                            <span>Route Zone</span>
                            <select wire:model="task_delivery_zone_id" @disabled($actionModalReadOnly)>
                                <option value="">Unzoned route</option>
                                @foreach ($deliveryZones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }} - GHS {{ number_format((float) $zone->fee, 2) }}</option>
                                @endforeach
                            </select>
                            @error('task_delivery_zone_id') <small>{{ $message }}</small> @enderror
                        </label>
                    @endif
                </div>
                <div class="form-actions">
                    <button type="button" wire:click="closeActionModal" class="btn-secondary">Cancel</button>
                    @unless ($actionModalReadOnly)
                        <button type="submit" class="btn-primary">{{ $taskModalHasExisting ? 'Save '.$taskSubject : 'Schedule '.$taskSubject }}</button>
                    @endunless
                </div>
            </form>
        </div>
    @endif

    @if ($modalOrder && $activeModal === 'tags')
        @php
            $tagAction = $actionModalReadOnly ? 'Preview' : ($tagModalHasExisting ? 'Edit' : 'Generate');
        @endphp
        <div class="modal-backdrop order-created-preview-backdrop" wire:key="order-tags-modal">
            <form wire:submit="generateModalTags" class="modal-panel order-action-modal order-action-modal--wide">
                <header class="modal-header">
                    <div>
                        <p class="dashboard-eyebrow">{{ $tagAction }} Tags</p>
                        <h3>{{ $modalOrder->order_number }}</h3>
                        <span>{{ $modalOrder->customer?->name ?? 'Customer' }}</span>
                    </div>
                    <button type="button" wire:click="closeActionModal" class="modal-close" aria-label="Close">x</button>
                </header>
                @if ($actionModalReadOnly)
                    <p class="notice notice--info">This order is completed, so generated tags are shown as a preview.</p>
                @endif
                <label class="field">
                    <span>Expected Garment Count</span>
                    <input type="number" min="1" step="1" wire:model="tag_expected_count" @disabled($actionModalReadOnly)>
                    @error('tag_expected_count') <small>{{ $message }}</small> @enderror
                </label>
                <div class="order-tag-rows">
                    @forelse ($tagRows as $index => $tagRow)
                        <div class="order-tag-row" wire:key="modal-tag-row-{{ $index }}">
                            <label class="field">
                                <span>Garment</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.garment_type" @disabled($actionModalReadOnly)>
                                @error("tagRows.$index.garment_type") <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Qty</span>
                                <input type="number" min="1" step="1" wire:model="tagRows.{{ $index }}.quantity" @disabled($actionModalReadOnly)>
                                @error("tagRows.$index.quantity") <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Color</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.color" @disabled($actionModalReadOnly)>
                            </label>
                            <label class="field">
                                <span>Condition</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.condition" @disabled($actionModalReadOnly)>
                            </label>
                        </div>
                    @empty
                        <p class="empty-state">No garment tags were generated for this order.</p>
                    @endforelse
                </div>
                <div class="form-actions">
                    <button type="button" wire:click="closeActionModal" class="btn-secondary">Cancel</button>
                    @unless ($actionModalReadOnly)
                        <button type="submit" class="btn-primary">{{ $tagModalHasExisting ? 'Save Tags' : 'Generate Tags' }}</button>
                    @endunless
                </div>
            </form>
        </div>
    @endif

    @if ($modalOrder && $activeModal === 'delete')
        <div class="modal-backdrop order-created-preview-backdrop" wire:key="order-delete-modal">
            <section class="modal-panel order-action-modal">
                <header class="modal-header">
                    <div>
                        <p class="dashboard-eyebrow">Delete Order</p>
                        <h3>{{ $modalOrder->order_number }}</h3>
                        <span>{{ $modalOrder->customer?->name ?? 'Customer' }}</span>
                    </div>
                    <button type="button" wire:click="closeActionModal" class="modal-close" aria-label="Close">x</button>
                </header>
                <p class="order-delete-copy">This order can only be deleted if it has no payments, garment tags, or pickup/delivery tasks.</p>
                <div class="form-actions">
                    <button type="button" wire:click="closeActionModal" class="btn-secondary">Cancel</button>
                    <button type="button" wire:click="confirmModalDelete" class="btn-danger">Delete Order</button>
                </div>
            </section>
        </div>
    @endif

    @if ($modalOrder && $activeModal === 'adjustment')
        <div class="modal-backdrop order-created-preview-backdrop" wire:key="order-adjustment-modal">
            <form wire:submit="recordPaymentCorrection" class="modal-panel order-action-modal">
                <header class="modal-header">
                    <div>
                        <p class="dashboard-eyebrow">Payment Correction</p>
                        <h3>{{ $modalOrder->order_number }}</h3>
                        <span>{{ $modalOrder->customer?->name ?? 'Customer' }}</span>
                    </div>
                    <button type="button" wire:click="closeActionModal" class="modal-close" aria-label="Close">x</button>
                </header>
                <div class="order-modal-summary">
                    <div><span>Total</span><strong>GHS {{ number_format((float) $modalOrder->total, 2) }}</strong></div>
                    <div><span>Paid</span><strong>GHS {{ number_format((float) $modalOrder->amount_paid, 2) }}</strong></div>
                    <div><span>Balance</span><strong>GHS {{ number_format((float) $modalOrder->balance, 2) }}</strong></div>
                </div>
                <p class="notice notice--info">This records an auditable payment correction row and immediately refreshes the paid amount, balance, and payment status.</p>
                <div class="form-grid">
                    <label class="field">
                        <span>Correction Type</span>
                        <select wire:model="adjustment_type">
                            <option value="adjustment">Manual Payment Correction</option>
                            <option value="refund">Refund</option>
                            <option value="void">Void Payment</option>
                        </select>
                        @error('adjustment_type') <small>{{ $message }}</small> @enderror
                    </label>
                    @if ($adjustment_type === 'adjustment')
                        <label class="field">
                            <span>Direction</span>
                            <select wire:model="adjustment_direction">
                                <option value="reduce">Reduce Paid Amount</option>
                                <option value="increase">Increase Paid Amount</option>
                            </select>
                            @error('adjustment_direction') <small>{{ $message }}</small> @enderror
                        </label>
                    @endif
                    <label class="field">
                        <span>Amount</span>
                        <input type="number" min="0.01" step="0.01" wire:model="adjustment_amount" placeholder="{{ $adjustment_type === 'void' ? 'Blank reverses all paid' : 'Required' }}">
                        @error('adjustment_amount') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field field--wide">
                        <span>Reason</span>
                        <textarea rows="3" wire:model="adjustment_reason" placeholder="Explain why this paid order needs correction"></textarea>
                        @error('adjustment_reason') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="form-actions">
                    <button type="button" wire:click="closeActionModal" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Record Correction</button>
                </div>
            </form>
        </div>
    @endif

    @if ($createdPreviewOrder)
        @php
            $isPaymentReceiptPreview = $receiptPreviewContext === 'payment';
        @endphp
        <div class="modal-backdrop order-created-preview-backdrop" wire:key="order-created-preview-modal">
            <section class="order-created-preview-modal" role="dialog" aria-modal="true" aria-labelledby="created-order-preview-title">
                <header class="order-created-preview-header">
                    <div>
                        <p class="dashboard-eyebrow">{{ $isPaymentReceiptPreview ? 'Payment Recorded' : 'Order Created' }}</p>
                        <h3 id="created-order-preview-title">{{ $createdPreviewOrder->order_number }}</h3>
                        <span>{{ $isPaymentReceiptPreview ? 'Print the updated receipt before closing this payment.' : ($createdPreviewOrder->customer?->name ?? 'Walk-in customer') }}</span>
                    </div>
                    <div class="order-created-preview-actions">
                        <button type="button" class="btn-primary" onclick="document.getElementById('created-order-receipt-frame')?.contentWindow?.print()">
                            Print Receipt
                        </button>
                        <a href="{{ route('receipts.orders.show', ['order' => $createdPreviewOrder, 'print' => 1]) }}" target="_blank" class="btn-secondary">
                            Open Print Page
                        </a>
                        <button type="button" wire:click="closeCreatedPreview" class="btn-secondary">
                            Close
                        </button>
                    </div>
                </header>
                <div class="order-created-preview-frame-wrap">
                    <iframe
                        id="created-order-receipt-frame"
                        title="Receipt preview for {{ $createdPreviewOrder->order_number }}"
                        src="{{ route('receipts.orders.show', ['order' => $createdPreviewOrder, 'embed' => 1]) }}"
                    ></iframe>
                </div>
            </section>
        </div>
    @endif

    @if ($timelineOrder)
        <div class="modal-backdrop order-created-preview-backdrop" wire:key="order-timeline-modal">
            <section class="order-created-preview-modal" role="dialog" aria-modal="true" aria-labelledby="order-timeline-title">
                <header class="order-created-preview-header">
                    <div>
                        <p class="dashboard-eyebrow">Order Timeline</p>
                        <h3 id="order-timeline-title">{{ $timelineOrder->order_number }}</h3>
                        <span>{{ $timelineOrder->customer?->name ?? 'Customer' }}</span>
                    </div>
                    <button type="button" wire:click="closeTimeline" class="btn-secondary">Close</button>
                </header>
                <div class="timeline-feed">
                    @forelse ($timelineEvents as $event)
                        <article class="timeline-entry">
                            <div class="timeline-entry__rail"></div>
                            <div class="timeline-entry__body">
                                <header class="timeline-entry__header">
                                    <h3>{{ $event['title'] }}</h3>
                                    <span>{{ $event['time'] }}</span>
                                </header>
                                <p>{{ $event['actor'] }}</p>
                                @if ($event['from'] || $event['to'])
                                    <div class="timeline-entry__changes">
                                        @if ($event['from'])
                                            <div>
                                                <strong>From</strong>
                                                @foreach ($event['from'] as $pair)
                                                    <span><b>{{ $pair['key'] }}</b>{{ $pair['value'] }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if ($event['to'])
                                            <div>
                                                <strong>To</strong>
                                                @foreach ($event['to'] as $pair)
                                                    <span><b>{{ $pair['key'] }}</b>{{ $pair['value'] }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="empty-state">No timeline events logged for this order yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</div>
