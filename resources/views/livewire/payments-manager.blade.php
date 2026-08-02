<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 16</p>
            <h2>Payment Module</h2>
        </div>
        <div class="module-actions">
            <span class="notice">Next: {{ $nextPaymentNumber }}</span>
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
        </div>
    </section>

    <section class="payments-layout">
        <section class="module-panel payment-capture-panel">
            <div class="payment-panel-heading">
                <div>
                    <h3>Receive Payment</h3>
                    @if ($selectedOrder)
                        <p>{{ $selectedOrder->customer?->name }} - {{ $selectedOrder->customer?->phone }}</p>
                    @endif
                </div>
                @if ($selectedOrder)
                    <span class="badge {{ $selectedOrder->payment_status === 'paid' ? 'badge--success' : ($selectedOrder->payment_status === 'part_paid' ? 'badge--warning' : 'badge--muted') }}">
                        {{ $statuses[$selectedOrder->payment_status] ?? ucfirst(str_replace('_', ' ', $selectedOrder->payment_status)) }}
                    </span>
                @endif
            </div>

            @if ($selectedOrder)
                <div class="payment-summary">
                    <div class="payment-summary__primary">
                        <span>Balance</span>
                        <strong>GHS {{ number_format((float) $selectedOrder->balance, 2) }}</strong>
                    </div>
                    <div>
                        <span>Order Total</span>
                        <strong>GHS {{ number_format((float) $selectedOrder->total_amount, 2) }}</strong>
                    </div>
                    <div>
                        <span>Amount Paid</span>
                        <strong>GHS {{ number_format((float) $selectedOrder->amount_paid, 2) }}</strong>
                    </div>
                    <div>
                        <span>History</span>
                        <strong>{{ $selectedOrder->payments->count() }} {{ Str::plural('payment', $selectedOrder->payments->count()) }}</strong>
                    </div>
                    <div>
                        <span>Loyalty Points</span>
                        <strong>{{ (int) ($selectedOrder->customer?->loyalty_points ?? 0) }}</strong>
                    </div>
                </div>

                <div class="selected-payment-order">
                    <div>
                        <span>Selected Order</span>
                        <h3>{{ $selectedOrder->order_number }}</h3>
                    </div>
                    <div class="receipt-actions">
                        <a href="{{ route('receipts.orders.show', $selectedOrder) }}" target="_blank" class="btn-secondary">Preview Receipt</a>
                        <a href="{{ route('receipts.orders.show', ['order' => $selectedOrder, 'print' => 1]) }}" target="_blank" class="btn-primary">Print</a>
                        <a href="{{ route('receipts.orders.show', ['order' => $selectedOrder, 'duplicate' => 1]) }}" target="_blank" class="btn-secondary">Duplicate Copy</a>
                    </div>
                </div>

                @if ((float) $selectedOrder->balance <= 0)
                    <p class="notice notice--success payment-paid-notice">This order is fully paid. Receipt actions remain available.</p>
                @endif

                <form wire:submit="recordPayment" class="form-grid">
                    <label class="field">
                        <span>Amount</span>
                        <input type="number" min="0" step="0.01" wire:model="amount">
                        @error('amount') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Payment Method</span>
                        <select wire:model="payment_method">
                            @foreach ($methods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_method') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field field--wide">
                        <span>Reference</span>
                        <input type="text" wire:model="reference" placeholder="Transaction ID, cheque number, POS reference">
                        @error('reference') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Redeem Points</span>
                        <input type="number" min="0" step="1" max="{{ $loyaltyMaxRedeemable }}" wire:model.live="redeemPoints">
                        @error('redeemPoints') <small>{{ $message }}</small> @enderror
                    </label>
                    <div class="selected-payment-order">
                        <span>Redeemable</span>
                        <p>{{ $loyaltyMaxRedeemable }} points max · minimum {{ $loyaltyMinimumRedeem }} points</p>
                        <strong>GHS {{ number_format(min((float) $loyaltyRedeemValue, (float) $selectedOrder->balance), 2) }} loyalty credit</strong>
                    </div>
                    <label class="field field--wide">
                        <span>Notes</span>
                        <textarea rows="3" wire:model="notes"></textarea>
                        @error('notes') <small>{{ $message }}</small> @enderror
                    </label>

                    <div class="form-actions field--wide">
                        <button type="button" wire:click="resetPaymentForm" class="btn-secondary">Reset</button>
                        <button type="submit" class="btn-primary" @disabled((float) $selectedOrder->balance <= 0)>Save Payment</button>
                    </div>
                </form>
            @else
                <p class="empty-state">Create an order before recording payments.</p>
            @endif
        </section>

        <section class="module-panel module-panel--list">
            <div class="payment-list-heading">
                <h3>Orders</h3>
                <span>{{ $orders->count() }} shown</span>
            </div>
            <div class="list-toolbar">
                <label class="field">
                    <span>Search</span>
                    <input type="search" wire:model.live="search" placeholder="Order number, customer, phone">
                </label>
                <label class="field">
                    <span>Payment Status</span>
                    <select wire:model.live="paymentStatusFilter">
                        <option value="all">All</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="service-list">
                @forelse ($orders as $order)
                    @php
                        $paymentProgress = (float) $order->total_amount > 0 ? min(100, ((float) $order->amount_paid / (float) $order->total_amount) * 100) : 0;
                    @endphp
                    <article class="service-row payment-order-row {{ $selectedOrder?->id === $order->id ? 'payment-order-row--active' : '' }}">
                        <button type="button" wire:click="selectOrder({{ $order->id }})" class="payment-order-button">
                            <div class="service-row__title">
                                <h3>{{ $order->order_number }}</h3>
                                <span class="badge {{ $order->payment_status === 'paid' ? 'badge--success' : ($order->payment_status === 'part_paid' ? 'badge--warning' : 'badge--muted') }}">
                                    {{ $statuses[$order->payment_status] ?? ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                </span>
                            </div>
                            <p>{{ $order->customer?->name }} - {{ $order->customer?->phone }}</p>
                            <div class="payment-progress" aria-hidden="true">
                                <span style="width: {{ $paymentProgress }}%"></span>
                            </div>
                            <div class="service-row__meta">
                                <span>Total GHS {{ number_format((float) $order->total_amount, 2) }}</span>
                                <span>Paid GHS {{ number_format((float) $order->amount_paid, 2) }}</span>
                                <span class="{{ (float) $order->balance > 0 ? 'payment-meta-due' : '' }}">Balance GHS {{ number_format((float) $order->balance, 2) }}</span>
                            </div>
                        </button>
                    </article>
                @empty
                    <p class="empty-state">No orders found.</p>
                @endforelse
            </div>
        </section>
    </section>

    <section class="module-panel">
        <h3>Payment History</h3>
        @if ($selectedOrder)
            <div class="payment-table">
                <div class="payment-table__head">
                    <span>Payment Number</span>
                    <span>Method</span>
                    <span>Amount</span>
                    <span>Reference</span>
                    <span>Received By</span>
                    <span>Date</span>
                </div>
                @forelse ($selectedOrder->payments as $payment)
                    <div class="payment-table__row">
                        <strong data-label="Payment Number">{{ $payment->payment_number ?? $payment->receipt_number ?? $payment->receipt_no }}</strong>
                        <span data-label="Method">{{ $methods[$payment->payment_method ?? $payment->method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method ?? $payment->method ?? 'Payment')) }}</span>
                        <span data-label="Amount">GHS {{ number_format((float) $payment->amount, 2) }}</span>
                        <span data-label="Reference">{{ $payment->reference ?: 'No reference' }}</span>
                        <span data-label="Received By">{{ $payment->receiver?->name ?? 'System' }}</span>
                        <span data-label="Date">{{ $payment->created_at?->format('M d, Y h:i A') }}</span>
                    </div>
                @empty
                    <p class="empty-state">No payments recorded for this order.</p>
                @endforelse
            </div>
        @else
            <p class="empty-state">Select an order to view payment history.</p>
        @endif
    </section>
</div>
