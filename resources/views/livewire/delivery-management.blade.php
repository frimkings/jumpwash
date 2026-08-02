<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 14</p>
            <h2>Delivery Management</h2>
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

    <section class="delivery-summary-grid">
        <div class="module-panel">
            <h3>Delivery Calendar</h3>
            <div class="calendar-strip">
                @forelse ($calendarDays as $date => $dayTasks)
                    <div>
                        <strong>{{ $date === 'Unscheduled' ? $date : \Carbon\Carbon::parse($date)->format('M d') }}</strong>
                        <span>{{ $dayTasks->count() }} deliveries</span>
                    </div>
                @empty
                    <p class="empty-state">No deliveries scheduled.</p>
                @endforelse
            </div>
        </div>

        <div class="module-panel">
            <h3>Route Planning</h3>
            <div class="route-list">
                @forelse ($routes as $route => $routeTasks)
                    <div>
                        <strong>{{ $route }}</strong>
                        <span>{{ $routeTasks->count() }} stops</span>
                    </div>
                @empty
                    <p class="empty-state">No route stops yet.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="module-grid delivery-layout">
        <form wire:submit="save" class="module-panel">
            <h3>{{ $editingId ? 'Edit Delivery' : 'Schedule Delivery' }}</h3>

            <div class="form-grid">
                <label class="field">
                    <span>Delivery Type</span>
                    <select wire:model.live="type">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Status</span>
                    <select wire:model="status">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Customer</span>
                    <select wire:model.live="customer_id">
                        <option value="">Select customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Order</span>
                    <select wire:model="order_id">
                        <option value="">No order linked</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->order_number }} - {{ ucfirst(str_replace('_', ' ', $order->status)) }}</option>
                        @endforeach
                    </select>
                    @error('order_id') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Delivery Date</span>
                    <input type="date" wire:model="delivery_date">
                    @error('delivery_date') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Delivery Time</span>
                    <input type="time" wire:model="delivery_time">
                    @error('delivery_time') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Assigned Staff</span>
                    <select wire:model="assigned_to">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}{{ $person->staffProfile?->vehicle ? ' - '.$person->staffProfile->vehicle : '' }}</option>
                        @endforeach
                    </select>
                    @error('assigned_to') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Route Zone</span>
                    <select wire:model="delivery_zone_id">
                        <option value="">Unzoned route</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }} - GHS {{ number_format((float) $zone->fee, 2) }}</option>
                        @endforeach
                    </select>
                    @error('delivery_zone_id') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Address</span>
                    <textarea rows="3" wire:model="address"></textarea>
                    @error('address') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Proof of Delivery</span>
                    <textarea rows="3" wire:model="signature_data" placeholder="Recipient name, signature note, or failed delivery reason"></textarea>
                    @error('signature_data') <small>{{ $message }}</small> @enderror
                </label>
                <div class="field field--wide">
                    <span>Delivery Signature</span>
                    <div class="signature-pad" x-data="signaturePad($wire, 'delivery_signature_data')">
                        <canvas x-ref="canvas" width="520" height="160" @mousedown="start($event)" @mousemove="draw($event)" @mouseup="stop()" @mouseleave="stop()" @touchstart.prevent="start($event)" @touchmove.prevent="draw($event)" @touchend.prevent="stop()"></canvas>
                        <div class="signature-pad__actions">
                            <button type="button" class="btn-secondary" @click="clear()">Clear Signature</button>
                        </div>
                    </div>
                    <input type="hidden" wire:model="delivery_signature_data">
                </div>
            </div>

            <div class="form-actions">
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="btn-secondary">Cancel</button>
                @endif
                <button type="submit" class="btn-primary">{{ $editingId ? 'Update Delivery' : 'Save Delivery' }}</button>
            </div>
        </form>

        <section class="module-panel module-panel--list">
            <div class="list-toolbar delivery-toolbar">
                <label class="field">
                    <span>Search</span>
                    <input type="search" wire:model.live="search" placeholder="Customer, phone, order, address">
                </label>
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
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="service-list">
                @forelse ($tasks as $task)
                    <article class="service-row delivery-row">
                        <div>
                            <div class="service-row__title">
                                <h3>{{ $task->customer?->name ?? 'Customer' }}</h3>
                                <span class="badge {{ $task->status === 'delivered' ? 'badge--success' : 'badge--muted' }}">
                                    {{ $statuses[$task->status] ?? ucfirst($task->status) }}
                                </span>
                            </div>
                            <p>{{ $types[$task->type] ?? ucfirst(str_replace('_', ' ', $task->type)) }} scheduled for {{ $task->scheduled_at?->format('M d, Y h:i A') }}</p>
                            <div class="service-row__meta">
                                <span>{{ $task->customer?->phone ?? 'No phone' }}</span>
                                <span>{{ $task->order?->order_number ?? 'No linked order' }}</span>
                                <span>{{ $task->assignedStaff?->name ?? 'Unassigned' }}</span>
                                <span>{{ $task->deliveryZone?->name ?? 'Unzoned route' }}</span>
                                <span>{{ $task->signature_data || $task->delivery_signature_path ? 'Proof captured' : 'Proof pending' }}</span>
                            </div>
                            <p>{{ $task->address ?: 'No delivery address' }}</p>
                            @if ($task->delivery_signature_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($task->delivery_signature_path) }}" alt="Delivery signature" class="signature-thumb">
                            @endif
                        </div>
                        <div class="row-actions">
                            @foreach ($statuses as $value => $label)
                                @if ($task->status !== $value)
                                    <button type="button" wire:click="setStatus({{ $task->id }}, '{{ $value }}')" class="btn-secondary">{{ $label }}</button>
                                @endif
                            @endforeach
                            <button type="button" wire:click="edit({{ $task->id }})" class="btn-secondary">Edit</button>
                            <button type="button" wire:click="delete({{ $task->id }})" wire:confirm="Delete this delivery task?" class="btn-danger">Delete</button>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">No delivery tasks found.</p>
                @endforelse
            </div>
        </section>
    </section>
</div>

@push('scripts')
    <script>
        window.signaturePad = function ($wire, property) {
            return {
                drawing: false,
                ctx: null,
                init() {
                    this.ctx = this.$refs.canvas.getContext('2d');
                    this.ctx.lineWidth = 2;
                    this.ctx.lineCap = 'round';
                    this.ctx.strokeStyle = '#111827';
                },
                point(event) {
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    const source = event.touches ? event.touches[0] : event;
                    return { x: source.clientX - rect.left, y: source.clientY - rect.top };
                },
                start(event) {
                    this.drawing = true;
                    const point = this.point(event);
                    this.ctx.beginPath();
                    this.ctx.moveTo(point.x, point.y);
                },
                draw(event) {
                    if (!this.drawing) return;
                    const point = this.point(event);
                    this.ctx.lineTo(point.x, point.y);
                    this.ctx.stroke();
                    $wire.set(property, this.$refs.canvas.toDataURL('image/png'));
                },
                stop() {
                    this.drawing = false;
                    $wire.set(property, this.$refs.canvas.toDataURL('image/png'));
                },
                clear() {
                    this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                    $wire.set(property, '');
                },
            };
        };
    </script>
@endpush
