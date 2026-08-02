<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\ActivityLog;
use App\Models\DeliveryZone;
use App\Models\GarmentTag;
use App\Models\LaundryService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupDeliveryTask;
use App\Models\Product;
use App\Models\RateChart;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderCreationService;
use App\Support\LaundryWorkflow;
use App\Support\LoyaltyService;
use App\Support\PerformanceCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersManager extends Component
{
    use WithPagination;

    private const ORDERS_PER_PAGE = 10;

    public const STATUSES = [
        'pending_pickup' => 'Pending Pickup',
        'picked_up' => 'Picked Up',
        'received' => 'Received',
        'processing' => 'Processing',
        'ready' => 'Ready',
        'out_for_delivery' => 'Out For Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'mobile_money' => 'Mobile Money',
        'bank_transfer' => 'Bank Transfer',
        'pos_card' => 'POS/Card',
        'cheque' => 'Cheque',
    ];

    public const ORDER_FILTERS = [
        'all' => 'All',
        'unpaid' => 'Unpaid',
        'part_paid' => 'Part Paid',
        'paid' => 'Paid',
        'needs_pickup' => 'Needs Pickup',
        'ready_delivery' => 'Ready for Delivery',
        'has_exceptions' => 'Has Exceptions',
        'completed_today' => 'Completed Today',
    ];

    private const STATUS_PROGRESS = [
        'pending_pickup' => ['picked_up', 'received'],
        'picked_up' => ['received'],
        'received' => ['processing'],
        'processing' => ['ready'],
        'ready' => ['out_for_delivery', 'delivered'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public string $customer_id = '';
    public string $customerSearch = '';
    public string $selectedCustomerLabel = '';
    public string $status = 'received';
    public array $rows = [];
    public string $notes = '';
    public string $search = '';
    public string $statusFilter = 'all';
    public bool $useSubscription = false;
    public string $customer_subscription_id = '';
    public float $taxRate = 0;
    public ?int $editingId = null;
    public ?int $createdPreviewOrderId = null;
    public string $receiptPreviewContext = 'created';
    public string $requestPickup = '0';
    public string $requestDelivery = '0';
    public string $pickup_date = '';
    public string $pickup_time = '';
    public string $delivery_date = '';
    public string $delivery_time = '';
    public ?int $timelineOrderId = null;
    public string $activeTab = 'create';
    public ?string $activeModal = null;
    public ?int $modalOrderId = null;
    public string $payment_notes = '';
    public array $paymentLines = [];
    public string $loyaltyRedeemPoints = '0';
    public string $task_date = '';
    public string $task_time = '';
    public string $task_address = '';
    public ?int $task_assigned_to = null;
    public ?int $task_delivery_zone_id = null;
    public string $tag_expected_count = '0';
    public array $tagRows = [];
    public bool $taskModalHasExisting = false;
    public bool $tagModalHasExisting = false;
    public bool $actionModalReadOnly = false;
    public string $adjustment_type = 'adjustment';
    public string $adjustment_direction = 'reduce';
    public string $adjustment_amount = '';
    public string $adjustment_reason = '';
    public ?int $selectedQueueOrderId = null;

    public function mount(): void
    {
        $this->taxRate = (float) (Setting::where('branch_id', auth()->user()?->branch_id)->where('key', 'tax_percentage')->value('value') ?? 0);
        $this->pickup_date = now()->toDateString();
        $this->pickup_time = now()->format('H:i');
        $this->delivery_date = now()->toDateString();
        $this->delivery_time = now()->addHours(2)->format('H:i');
        $this->task_date = now()->toDateString();
        $this->task_time = now()->format('H:i');
        $this->addRow();
    }

    public function addRow(): void
    {
        $this->rows[] = [
            'id' => null,
            'product_id' => '',
            'laundry_service_id' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'original_unit_price' => null,
            'price_override_enabled' => false,
            'price_override_reason' => '',
            'amount' => 0,
            'status' => $this->status,
        ];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);

        if (count($this->rows) === 0) {
            $this->addRow();
        }

        $this->recalculateRows();
    }

    public function updatedRows(): void
    {
        $this->mergeDuplicateRows();
        $this->syncRowsStatus();
        $this->recalculateRows();
    }

    public function updatedStatus(string $status): void
    {
        if (! array_key_exists($status, self::STATUSES)) {
            return;
        }

        if ($status === 'cancelled') {
            $this->status = 'received';
            $this->addError('status', 'Cancelling orders is disabled.');
            return;
        }

        $this->syncRowsStatus();
    }

    public function updatedCustomerSearch(): void
    {
        if ($this->customer_id && $this->customerSearch !== $this->selectedCustomerLabel) {
            $this->customer_id = '';
            $this->selectedCustomerLabel = '';
        }
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->findOrFail($customerId);

        $this->customer_id = (string) $customer->id;
        $this->selectedCustomerLabel = $customer->customer_code.' - '.$customer->name.' - '.$customer->phone;
        $this->customerSearch = $this->selectedCustomerLabel;
        $this->useSubscription = false;
        $this->customer_subscription_id = '';
    }

    public function clearCustomer(): void
    {
        $this->customer_id = '';
        $this->customerSearch = '';
        $this->selectedCustomerLabel = '';
        $this->useSubscription = false;
        $this->customer_subscription_id = '';
    }

    public function save(): void
    {
        $this->syncRowsStatus();
        $this->recalculateRows();

        $validated = $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.product_id' => ['required', 'exists:products,id'],
            'rows.*.laundry_service_id' => ['required', 'exists:laundry_services,id'],
            'rows.*.quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'rows.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'rows.*.price_override_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->preparePriceOverridesForSave()) {
            return;
        }

        if (! $this->validatePricedRowsForSave()) {
            return;
        }

        if (! $this->validateStatusForSave()) {
            return;
        }

        $subscription = $this->useSubscription ? $this->validatedSubscriptionForSave() : null;

        if ($this->useSubscription && ! $subscription) {
            return;
        }

        $wasEditing = (bool) $this->editingId;
        $createdOrder = null;

        if ($wasEditing) {
            if (! $this->updateOrder($validated)) {
                return;
            }
        } else {
            $createdOrder = app(OrderCreationService::class)->create([
                'branch_id' => auth()->user()?->branch_id,
                'customer_id' => $validated['customer_id'],
                'created_by' => auth()->id(),
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'tax_rate' => $this->taxRate,
                'rows' => $this->rows,
                'billing_source' => $subscription ? 'subscription' : 'cash',
                'customer_subscription_id' => $subscription?->id,
                'discount' => $subscription ? $this->total() : 0,
            ]);

            if ($subscription) {
                $subscription->consume($this->subscriptionUsesForRows());
                ActivityLog::record('subscription.usage_consumed', $subscription->fresh(), [
                    'order_number' => $createdOrder->order_number,
                    'uses' => $this->subscriptionUsesForRows(),
                    'remaining' => $subscription->fresh()->remainingUses(),
                ]);
            }

            $this->createRequestedTasks($createdOrder);
        }

        $this->resetOrderForm();

        if ($createdOrder) {
            $this->activeTab = 'queue';
            $this->selectedQueueOrderId = $createdOrder->id;

            if ((float) $createdOrder->balance > 0 && $createdOrder->payment_status !== 'paid') {
                $this->openPaymentModal($createdOrder->id);
            } else {
                $this->createdPreviewOrderId = $createdOrder->id;
                $this->receiptPreviewContext = 'created';
            }
        }

        session()->flash('status', $wasEditing ? 'Order updated.' : 'Order created.');
    }

    public function resetOrderForm(): void
    {
        $this->editingId = null;
        $this->customer_id = '';
        $this->customerSearch = '';
        $this->selectedCustomerLabel = '';
        $this->status = 'received';
        $this->notes = '';
        $this->rows = [];
        $this->useSubscription = false;
        $this->customer_subscription_id = '';
        $this->requestPickup = '0';
        $this->requestDelivery = '0';
        $this->pickup_date = now()->toDateString();
        $this->pickup_time = now()->format('H:i');
        $this->delivery_date = now()->toDateString();
        $this->delivery_time = now()->addHours(2)->format('H:i');
        $this->addRow();
        $this->resetValidation();
        $this->activeTab = 'create';
    }

    public function closeCreatedPreview(): void
    {
        $this->createdPreviewOrderId = null;
        $this->receiptPreviewContext = 'created';
    }

    public function selectQueueOrder(int $orderId): void
    {
        $this->selectedQueueOrderId = $this->orderQuery()->findOrFail($orderId)->id;
    }

    public function edit(int $orderId): void
    {
        $order = $this->orderQuery()
            ->with(['customer', 'items'])
            ->withCount('payments')
            ->findOrFail($orderId);

        if ($this->orderHasPayment($order)) {
            $this->resetOrderForm();
            session()->flash('error', 'This order has a payment and cannot be edited. Use adjustment or refund workflows instead.');
            return;
        }

        $this->createdPreviewOrderId = null;
        $this->receiptPreviewContext = 'created';
        $this->editingId = $order->id;
        $this->customer_id = (string) $order->customer_id;
        $this->selectedCustomerLabel = trim(($order->customer?->customer_code ?? 'Customer').' - '.($order->customer?->name ?? 'Unknown').' - '.($order->customer?->phone ?? ''));
        $this->customerSearch = $this->selectedCustomerLabel;
        $this->status = $order->status;
        $this->notes = (string) $order->notes;
        $this->rows = $order->items->map(fn ($item): array => [
            'id' => $item->id,
            'product_id' => (string) $item->product_id,
            'laundry_service_id' => (string) $item->laundry_service_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'original_unit_price' => $item->original_unit_price !== null ? (float) $item->original_unit_price : (float) $item->unit_price,
            'price_override_enabled' => $item->original_unit_price !== null && (float) $item->original_unit_price !== (float) $item->unit_price,
            'price_override_reason' => (string) $item->price_override_reason,
            'amount' => (float) $item->line_total,
            'status' => $item->status ?: $order->status,
        ])->values()->all();

        if ($this->rows === []) {
            $this->addRow();
        }

        $this->resetValidation();
    }

    public function schedulePickup(int $orderId): void
    {
        $order = $this->orderQuery()->with('customer')->findOrFail($orderId);
        LaundryWorkflow::createTaskForOrder($order, 'door_pickup', now());
        session()->flash('status', 'Pickup task scheduled.');
    }

    public function scheduleDelivery(int $orderId): void
    {
        $order = $this->orderQuery()->with('customer')->findOrFail($orderId);
        LaundryWorkflow::createTaskForOrder($order, 'door_delivery', now()->addHour());
        session()->flash('status', 'Delivery task scheduled.');
    }

    public function showTimeline(int $orderId): void
    {
        $this->timelineOrderId = $this->orderQuery()->findOrFail($orderId)->id;
    }

    public function closeTimeline(): void
    {
        $this->timelineOrderId = null;
    }

    public function openPaymentModal(int $orderId): void
    {
        $order = $this->orderQuery()->findOrFail($orderId);

        if ((float) $order->balance <= 0 || $order->payment_status === 'paid') {
            $this->closeActionModal();
            session()->flash('status', 'This order is already fully paid.');
            return;
        }

        $this->modalOrderId = $order->id;
        $this->activeModal = 'payment';
        $this->payment_notes = '';
        $this->loyaltyRedeemPoints = '0';
        $this->paymentLines = [[
            'amount' => number_format(max((float) $order->balance, 0), 2, '.', ''),
            'method' => 'cash',
            'reference' => '',
        ]];
        $this->resetValidation();
    }

    public function addPaymentLine(): void
    {
        $remaining = $this->modalPaymentRemaining();

        $this->paymentLines[] = [
            'amount' => $remaining > 0 ? number_format($remaining, 2, '.', '') : '',
            'method' => 'cash',
            'reference' => '',
        ];
    }

    public function removePaymentLine(int $index): void
    {
        unset($this->paymentLines[$index]);
        $this->paymentLines = array_values($this->paymentLines);

        if ($this->paymentLines === []) {
            $this->addPaymentLine();
        }
    }

    public function recordModalPayment(LoyaltyService $loyalty): void
    {
        $order = $this->modalOrder();

        if (! $order) {
            return;
        }

        $balance = round((float) $order->balance, 2);

        if ($balance <= 0 || $order->payment_status === 'paid') {
            $this->closeActionModal();
            session()->flash('status', 'This order is already fully paid.');
            return;
        }

        $validated = $this->validate([
            'paymentLines' => ['required', 'array', 'min:1'],
            'paymentLines.*.amount' => ['required', 'numeric', 'min:0'],
            'paymentLines.*.method' => ['required', Rule::in(array_keys(self::PAYMENT_METHODS))],
            'paymentLines.*.reference' => ['nullable', 'string', 'max:255'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
            'loyaltyRedeemPoints' => ['nullable', 'integer', 'min:0'],
        ]);

        $redeemPoints = (int) ($validated['loyaltyRedeemPoints'] ?? 0);
        $redeemValue = 0.0;

        if ($redeemPoints > 0) {
            $maxRedeemable = $loyalty->maxRedeemablePoints($order->customer, $balance, $order->branch_id);

            if ($redeemPoints < $loyalty->minimumRedemptionPoints($order->branch_id)) {
                $this->addError('loyaltyRedeemPoints', 'Minimum redemption is '.$loyalty->minimumRedemptionPoints($order->branch_id).' points.');
                return;
            }

            if ($redeemPoints > $maxRedeemable) {
                $this->addError('loyaltyRedeemPoints', 'Customer can redeem up to '.$maxRedeemable.' points for this order.');
                return;
            }

            $redeemValue = min($loyalty->moneyValueForPoints($redeemPoints, $order->branch_id), $balance);
        }

        $paymentTotal = round(collect($validated['paymentLines'])->sum(fn (array $line): float => (float) $line['amount']) + $redeemValue, 2);

        if ($paymentTotal > $balance) {
            $this->addError('paymentLines', 'Split payment total cannot be greater than the current balance.');
            return;
        }

        if ($paymentTotal <= 0) {
            $this->addError('paymentLines', 'Enter a payment amount or redeem loyalty points.');
            return;
        }

        $payments = DB::transaction(function () use ($order, $validated, $loyalty, $redeemPoints, $redeemValue) {
            $payments = collect();

            if ($redeemPoints > 0 && $redeemValue > 0) {
                $loyaltyPayment = $this->createOrderPayment($order, [
                    'amount' => $redeemValue,
                    'method' => 'loyalty_credit',
                    'reference' => 'LOYALTY-'.$redeemPoints.'PTS',
                ], 'Redeemed '.$redeemPoints.' loyalty points.');

                $loyalty->redeemForOrder($order->customer, $order, $redeemPoints, $loyaltyPayment);
                $payments->push($loyaltyPayment);
            }

            foreach ($validated['paymentLines'] as $line) {
                if ((float) $line['amount'] <= 0) {
                    continue;
                }

                $payment = $this->createOrderPayment($order, $line, $validated['payment_notes'] ?: null);
                $payments->push($payment);
                $loyalty->earnForPayment($payment->fresh(['customer', 'order']));
            }

            $this->syncOrderPaymentSummary($order->fresh());

            return $payments;
        });

        $freshOrder = $order->fresh(['customer']);

        foreach ($payments as $payment) {
            $data = [
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method ?? $payment->method ?? null,
                'reference' => $payment->reference ?? null,
                'amount_paid' => $freshOrder->amount_paid,
                'balance' => $freshOrder->balance,
                'payment_status' => $freshOrder->payment_status,
            ];

            ActivityLog::record('payment.received', $payment, [
                'order_number' => $freshOrder->order_number,
                'customer' => $freshOrder->customer?->name,
            ], [], $data);
        }

        $this->closeActionModal();
        $this->activeTab = 'queue';
        $this->selectedQueueOrderId = $freshOrder->id;
        $this->createdPreviewOrderId = $freshOrder->id;
        $this->receiptPreviewContext = 'payment';
        session()->flash('status', $payments->count() === 1 ? 'Payment recorded.' : 'Split payment recorded.');
    }

    public function openPickupModal(int $orderId): void
    {
        $this->openTaskModal($orderId, 'pickup');
    }

    public function openDeliveryModal(int $orderId): void
    {
        $this->openTaskModal($orderId, 'delivery');
    }

    public function saveModalTask(): void
    {
        $order = $this->modalOrder();

        if (! $order || ! in_array($this->activeModal, ['pickup', 'delivery'], true)) {
            return;
        }

        if ($this->isOrderCompleted($order)) {
            $this->addError('task_date', 'Completed orders can only be previewed.');
            return;
        }

        $validated = $this->validate([
            'task_date' => ['required', 'date'],
            'task_time' => ['required', 'date_format:H:i'],
            'task_address' => ['nullable', 'string', 'max:500'],
            'task_assigned_to' => ['nullable', Rule::exists('users', 'id')->where('branch_id', auth()->user()?->branch_id)],
            'task_delivery_zone_id' => ['nullable', 'exists:delivery_zones,id'],
        ]);

        $type = $this->activeModal === 'delivery' ? 'door_delivery' : 'door_pickup';
        $existingTask = $this->taskFor($order, $this->activeModal);
        $status = $existingTask?->status ?? ($this->activeModal === 'delivery' ? 'pending' : 'scheduled');

        $task = PickupDeliveryTask::updateOrCreate(
            [
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'type' => $type,
            ],
            [
                'customer_id' => $order->customer_id,
                'delivery_zone_id' => $this->activeModal === 'delivery' ? ($validated['task_delivery_zone_id'] ?: null) : null,
                'assigned_to' => $validated['task_assigned_to'] ?: null,
                'status' => $status,
                'scheduled_at' => Carbon::parse($validated['task_date'].' '.$validated['task_time']),
                'address' => $validated['task_address'] ?: null,
            ],
        );

        LaundryWorkflow::syncOrderFromTask($task->fresh());

        ActivityLog::record($existingTask ? 'workflow.task_updated' : 'workflow.task_created', $task, [
            'module' => $this->activeModal === 'delivery' ? 'deliveries' : 'pickups',
            'order_number' => $order->order_number,
            'task_type' => $type,
        ]);

        $message = $this->activeModal === 'delivery'
            ? ($existingTask ? 'Delivery updated.' : 'Delivery scheduled.')
            : ($existingTask ? 'Pickup updated.' : 'Pickup scheduled.');
        $this->closeActionModal();
        session()->flash('status', $message);
    }

    public function openTagsModal(int $orderId): void
    {
        $order = $this->orderQuery()->with(['items.product', 'items.service', 'garmentTags'])->findOrFail($orderId);
        $existingTags = $order->garmentTags->sortBy('id')->values();

        $this->modalOrderId = $order->id;
        $this->activeModal = 'tags';
        $this->tagModalHasExisting = $existingTags->isNotEmpty();
        $this->actionModalReadOnly = $this->isOrderCompleted($order);
        if ($this->tagModalHasExisting) {
            $this->tagRows = $this->tagRowsFromExistingTags($existingTags);
        } elseif ($this->actionModalReadOnly) {
            $this->tagRows = [];
        } else {
            $this->tagRows = $order->items->map(fn ($item): array => [
                'order_item_id' => $item->id,
                'garment_type' => $item->product?->name ?: $item->item_name,
                'quantity' => max(1, (int) ceil((float) $item->quantity)),
                'color' => '',
                'condition' => '',
            ])->values()->all();
        }
        $tagCount = collect($this->tagRows)->sum(fn (array $row): int => (int) $row['quantity']);
        $this->tag_expected_count = (string) ($this->actionModalReadOnly ? max((int) $order->expected_garment_count, $tagCount) : max(1, $tagCount));
        $this->resetValidation();
    }

    public function generateModalTags(): void
    {
        $order = $this->modalOrder();

        if (! $order) {
            return;
        }

        if ($this->isOrderCompleted($order)) {
            $this->addError('tag_expected_count', 'Completed orders can only be previewed.');
            return;
        }

        $validated = $this->validate([
            'tag_expected_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'tagRows' => ['required', 'array', 'min:1'],
            'tagRows.*.order_item_id' => ['nullable', 'exists:order_items,id'],
            'tagRows.*.garment_type' => ['required', 'string', 'max:150'],
            'tagRows.*.quantity' => ['required', 'integer', 'min:1', 'max:250'],
            'tagRows.*.color' => ['nullable', 'string', 'max:80'],
            'tagRows.*.condition' => ['nullable', 'string', 'max:255'],
        ]);

        $rowQuantityTotal = collect($validated['tagRows'])->sum(fn (array $row): int => (int) $row['quantity']);
        $expectedCount = max((int) $validated['tag_expected_count'], $rowQuantityTotal);

        $hadExistingTags = $order->garmentTags()->exists();

        $tagChanges = DB::transaction(function () use ($order, $validated, $expectedCount) {
            $order->update(['expected_garment_count' => $expectedCount]);

            return $this->syncTagRows($order, $validated['tagRows']);
        });

        foreach ($tagChanges['created'] as $tag) {
            ActivityLog::record('created', $tag, [
                'module' => 'garment_tags',
                'order_number' => $order->order_number,
            ]);
        }

        ActivityLog::record($hadExistingTags ? 'garment_tags.updated' : 'garment_tags.generated', $order, [
            'module' => 'garment_tags',
            'order_number' => $order->order_number,
        ], [], [
            'expected_garment_count' => $expectedCount,
            'created' => $tagChanges['created']->count(),
            'updated' => $tagChanges['updated'],
            'removed' => $tagChanges['removed'],
        ]);

        $this->closeActionModal();
        session()->flash('status', $hadExistingTags ? 'Garment tags updated.' : $tagChanges['created']->count().' garment tag'.($tagChanges['created']->count() === 1 ? '' : 's').' generated.');
    }

    public function openDeleteModal(int $orderId): void
    {
        $this->modalOrderId = $this->orderQuery()->findOrFail($orderId)->id;
        $this->activeModal = 'delete';
        $this->resetValidation();
    }

    public function confirmModalDelete(): void
    {
        if (! $this->modalOrderId) {
            return;
        }

        $orderId = $this->modalOrderId;
        $this->closeActionModal();
        $this->delete($orderId);
    }

    public function closeActionModal(): void
    {
        $this->activeModal = null;
        $this->modalOrderId = null;
        $this->paymentLines = [];
        $this->payment_notes = '';
        $this->loyaltyRedeemPoints = '0';
        $this->task_date = now()->toDateString();
        $this->task_time = now()->format('H:i');
        $this->task_address = '';
        $this->task_assigned_to = null;
        $this->task_delivery_zone_id = null;
        $this->tag_expected_count = '0';
        $this->tagRows = [];
        $this->taskModalHasExisting = false;
        $this->tagModalHasExisting = false;
        $this->actionModalReadOnly = false;
        $this->adjustment_type = 'adjustment';
        $this->adjustment_direction = 'reduce';
        $this->adjustment_amount = '';
        $this->adjustment_reason = '';
        $this->resetValidation();
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['create', 'queue'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function advanceOrderStatus(int $orderId, string $status): void
    {
        $order = $this->orderQuery()
            ->withCount(['garmentTags'])
            ->findOrFail($orderId);

        if (! array_key_exists($status, self::STATUSES)) {
            return;
        }

        $error = $this->statusTransitionError($order, $status);

        if ($error) {
            session()->flash('error', $error);
            return;
        }

        $oldStatus = $order->status;
        $order->update([
            'status' => $status,
            'completed_at' => $status === 'delivered' ? now() : $order->completed_at,
        ]);
        $order->items()->update(['status' => $status]);

        ActivityLog::record('order.status_changed', $order, [
            'module' => 'orders',
            'order_number' => $order->order_number,
        ], ['status' => $oldStatus], ['status' => $status]);

        session()->flash('status', 'Order moved to '.(self::STATUSES[$status] ?? $status).'.');
    }

    public function openAdjustmentModal(int $orderId): void
    {
        $order = $this->orderQuery()->withCount('payments')->findOrFail($orderId);

        if (! $this->orderHasPayment($order)) {
            session()->flash('error', 'Adjustments are only needed after payment has been recorded.');
            return;
        }

        $this->modalOrderId = $order->id;
        $this->activeModal = 'adjustment';
        $this->adjustment_type = 'adjustment';
        $this->adjustment_direction = 'reduce';
        $this->adjustment_amount = '';
        $this->adjustment_reason = '';
        $this->resetValidation();
    }

    public function recordPaymentCorrection(): void
    {
        abort_unless(auth()->user()?->can('payments.manage') || auth()->user()?->can('payments.correct'), 403);

        $order = $this->modalOrder();

        if (! $order || $this->activeModal !== 'adjustment') {
            return;
        }

        if (! $this->orderHasPayment($order)) {
            $this->closeActionModal();
            session()->flash('error', 'This order has no payment to adjust.');
            return;
        }

        $validated = $this->validate([
            'adjustment_type' => ['required', Rule::in(['adjustment', 'refund', 'void'])],
            'adjustment_direction' => ['required', Rule::in(['increase', 'reduce'])],
            'adjustment_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'adjustment_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $paid = round((float) $order->payments()->sum('amount'), 2);
        $balance = round(max((float) ($order->total_amount ?: $order->total) - $paid, 0), 2);
        $type = $validated['adjustment_type'];
        $direction = $type === 'adjustment' ? $validated['adjustment_direction'] : 'reduce';
        $amount = $type === 'void' && blank($validated['adjustment_amount'] ?? null)
            ? $paid
            : round((float) $validated['adjustment_amount'], 2);

        if ($amount <= 0) {
            $this->addError('adjustment_amount', 'Enter a correction amount.');
            return;
        }

        if ($direction === 'reduce' && $amount > $paid) {
            $this->addError('adjustment_amount', 'Correction cannot reduce paid amount below zero.');
            return;
        }

        if ($direction === 'increase' && $amount > $balance) {
            $this->addError('adjustment_amount', 'Correction cannot be greater than the current balance.');
            return;
        }

        $payment = DB::transaction(function () use ($order, $type, $direction, $amount, $validated): Payment {
            $signedAmount = $direction === 'reduce' ? -$amount : $amount;
            $payment = $this->createPaymentCorrection($order, $signedAmount, $type, $validated['adjustment_reason']);

            $this->syncOrderPaymentSummary($order->fresh());

            return $payment;
        });

        $freshOrder = $order->fresh(['customer']);

        ActivityLog::record('payment.correction_recorded', $payment, [
            'module' => 'payments',
            'order_number' => $freshOrder->order_number,
            'customer' => $freshOrder->customer?->name,
            'reason' => $validated['adjustment_reason'],
        ], [
            'amount_paid' => $paid,
            'balance' => $balance,
            'payment_status' => $order->payment_status,
        ], [
            'type' => $type,
            'direction' => $direction,
            'correction_amount' => $payment->amount,
            'amount_paid' => $freshOrder->amount_paid,
            'balance' => $freshOrder->balance,
            'payment_status' => $freshOrder->payment_status,
        ]);

        $this->closeActionModal();
        session()->flash('status', 'Payment correction recorded and order balance updated.');
    }

    public function delete(int $orderId): void
    {
        $order = $this->orderQuery()
            ->withCount(['payments', 'garmentTags'])
            ->findOrFail($orderId);

        if ($order->payments_count > 0 || $order->garment_tags_count > 0 || PickupDeliveryTask::where('order_id', $order->id)->exists()) {
            session()->flash('error', 'Order has payments, garment tags, or pickup/delivery tasks and cannot be deleted.');
            return;
        }

        $orderNumber = $order->order_number;

        DB::transaction(function () use ($order, $orderNumber): void {
            $order->items()->delete();
            $order->delete();

            ActivityLog::record('deleted', null, [
                'module' => 'orders',
                'order_number' => $orderNumber,
            ]);
        });

        if ($this->editingId === $orderId) {
            $this->resetOrderForm();
        }

        if ($this->createdPreviewOrderId === $orderId) {
            $this->createdPreviewOrderId = null;
            $this->receiptPreviewContext = 'created';
        }

        if ($this->selectedQueueOrderId === $orderId) {
            $this->selectedQueueOrderId = null;
        }

        session()->flash('status', 'Order deleted.');
    }

    public function updatedSearch(): void
    {
        $this->selectedQueueOrderId = null;
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->selectedQueueOrderId = null;
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        if (! array_key_exists($status, self::ORDER_FILTERS) && ! array_key_exists($status, self::STATUSES)) {
            return;
        }

        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function enablePriceOverride(int $index): void
    {
        if (! $this->canOverridePrices()) {
            session()->flash('error', 'Only users with rate chart permission can override prices.');
            return;
        }

        if (! isset($this->rows[$index])) {
            return;
        }

        $this->rows[$index]['price_override_enabled'] = true;
        $this->rows[$index]['original_unit_price'] = $this->rows[$index]['original_unit_price'] ?? $this->defaultUnitPriceForRow($this->rows[$index]);
    }

    public function clearPriceOverride(int $index): void
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        $defaultPrice = $this->defaultUnitPriceForRow($this->rows[$index]);
        $this->rows[$index]['unit_price'] = $defaultPrice;
        $this->rows[$index]['original_unit_price'] = $defaultPrice;
        $this->rows[$index]['price_override_enabled'] = false;
        $this->rows[$index]['price_override_reason'] = '';
        $this->recalculateRows();
    }

    public function render()
    {
        $customerResults = Customer::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->when($this->customerSearch !== '' && ! $this->customer_id, fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('customer_code', 'like', '%'.$this->customerSearch.'%')
                    ->orWhere('name', 'like', '%'.$this->customerSearch.'%')
                    ->orWhere('phone', 'like', '%'.$this->customerSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->customerSearch.'%');
            }))
            ->orderBy('name')
            ->limit(8)
            ->get();

        $products = Cache::remember(PerformanceCache::key('active-products'), PerformanceCache::LOOKUP_TTL, fn () => Product::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']));

        $services = Cache::remember(PerformanceCache::key('active-services'), PerformanceCache::LOOKUP_TTL, fn () => LaundryService::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']));

        $baseOrderQuery = $this->orderQuery();

        $orderStats = [
            'today' => (clone $baseOrderQuery)->whereDate('created_at', today())->count(),
            'active' => (clone $baseOrderQuery)->whereNotIn('status', ['delivered', 'cancelled'])->count(),
            'ready' => (clone $baseOrderQuery)->whereIn('status', ['ready', 'out_for_delivery'])->count(),
            'unpaid' => (clone $baseOrderQuery)->whereIn('payment_status', ['unpaid', 'part_paid', 'partial'])->count(),
            'exceptions' => (clone $baseOrderQuery)->whereHas('garmentTags', fn (Builder $query) => $query->whereIn('status', ['missing', 'damaged', 'rewash']))->count(),
            'balance' => (float) (clone $baseOrderQuery)->where('balance', '>', 0)->sum('balance'),
        ];

        $orders = (clone $baseOrderQuery)
            ->with(['customer', 'items.product', 'items.service', 'payments'])
            ->withCount([
                'payments',
                'garmentTags',
                'pickupDeliveryTasks as pickup_tasks_count' => fn (Builder $query) => $query->where('type', 'door_pickup'),
                'pickupDeliveryTasks as delivery_tasks_count' => fn (Builder $query) => $query->where('type', 'door_delivery'),
            ])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('order_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $this->applyOrderFilter($query, $this->statusFilter))
            ->latest()
            ->paginate(self::ORDERS_PER_PAGE);

        $selectedQueueOrder = $this->selectedQueueOrderId
            ? $orders->getCollection()->firstWhere('id', $this->selectedQueueOrderId)
                ?? (clone $baseOrderQuery)
                    ->with(['customer', 'items.product', 'items.service', 'payments'])
                    ->withCount([
                        'payments',
                        'garmentTags',
                        'pickupDeliveryTasks as pickup_tasks_count' => fn (Builder $query) => $query->where('type', 'door_pickup'),
                        'pickupDeliveryTasks as delivery_tasks_count' => fn (Builder $query) => $query->where('type', 'door_delivery'),
                    ])
                    ->find($this->selectedQueueOrderId)
            : $orders->getCollection()->first();

        if (! $this->selectedQueueOrderId && $selectedQueueOrder) {
            $this->selectedQueueOrderId = $selectedQueueOrder->id;
        }

        $createdPreviewOrder = $this->createdPreviewOrderId
            ? (clone $baseOrderQuery)->with(['customer', 'items.product', 'items.service', 'payments'])->find($this->createdPreviewOrderId)
            : null;

        return view('livewire.orders-manager', [
            'customerResults' => $customerResults,
            'products' => $products,
            'services' => $services,
            'orders' => $orders,
            'selectedQueueOrder' => $selectedQueueOrder,
            'createdPreviewOrder' => $createdPreviewOrder,
            'receiptPreviewContext' => $this->receiptPreviewContext,
            'timelineOrder' => $this->timelineOrder(),
            'timelineEvents' => $this->timelineEvents(),
            'orderStats' => $orderStats,
            'statuses' => self::STATUSES,
            'orderFilters' => self::ORDER_FILTERS,
            'subtotal' => $this->subtotal(),
            'tax' => $this->tax(),
            'total' => $this->total(),
            'subscriptionDiscount' => $this->useSubscription ? $this->total() : 0,
            'payableTotal' => $this->useSubscription ? 0 : $this->total(),
            'nextOrderNumber' => $this->nextOrderNumber(),
            'canOverridePrices' => $this->canOverridePrices(),
            'activeCustomerSubscriptions' => $this->activeCustomerSubscriptions(),
            'modalOrder' => $this->modalOrder(),
            'modalPaymentTotal' => $this->modalPaymentTotal(),
            'modalPaymentRemaining' => $this->modalPaymentRemaining(),
            'modalLoyaltySummary' => $this->modalLoyaltySummary(),
            'paymentMethods' => self::PAYMENT_METHODS,
            'deliveryStaff' => $this->deliveryStaff(),
            'deliveryZones' => $this->deliveryZones(),
        ])->layout('layouts.app', ['title' => 'Orders']);
    }

    private function recalculateRows(): void
    {
        foreach ($this->rows as $index => $row) {
            $defaultPrice = $this->defaultUnitPriceForRow($row);

            if ($defaultPrice !== null) {
                $this->rows[$index]['original_unit_price'] = $defaultPrice;

                if (empty($this->rows[$index]['price_override_enabled'])) {
                    $this->rows[$index]['unit_price'] = $defaultPrice;
                    $this->rows[$index]['price_override_reason'] = '';
                }
            }

            $quantity = (int) ($this->rows[$index]['quantity'] ?? 0);
            $unitPrice = (float) ($this->rows[$index]['unit_price'] ?? 0);
            $this->rows[$index]['amount'] = round($quantity * $unitPrice, 2);
        }
    }

    private function mergeDuplicateRows(): void
    {
        $mergedRows = [];

        foreach ($this->rows as $row) {
            $productId = $row['product_id'] ?? '';
            $serviceId = $row['laundry_service_id'] ?? '';

            if (! $productId || ! $serviceId) {
                $mergedRows[] = $row;
                continue;
            }

            $key = $productId.'|'.$serviceId;

            if (! isset($mergedRows[$key])) {
                $mergedRows[$key] = $row;
                continue;
            }

            $existing = $mergedRows[$key];
            $mergedRows[$key]['quantity'] = (int) ($existing['quantity'] ?? 0) + (int) ($row['quantity'] ?? 0);

            if (! empty($existing['price_override_enabled']) || ! empty($row['price_override_enabled'])) {
                $mergedRows[$key]['price_override_enabled'] = ! empty($existing['price_override_enabled']);
                $mergedRows[$key]['unit_price'] = $existing['unit_price'] ?? $row['unit_price'] ?? 0;
                $mergedRows[$key]['price_override_reason'] = $existing['price_override_reason'] ?? $row['price_override_reason'] ?? '';
            }

            if (! empty($row['id']) && empty($mergedRows[$key]['id'])) {
                $mergedRows[$key]['id'] = $row['id'];
            }
        }

        $this->rows = array_values($mergedRows);

        if ($this->rows === []) {
            $this->addRow();
        }
    }

    private function syncRowsStatus(): void
    {
        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['status'] = $this->status;
        }
    }

    public function statusProgressActions(Order $order): array
    {
        return collect(self::STATUS_PROGRESS[$order->status] ?? [])
            ->filter(fn (string $status): bool => ! $this->statusTransitionError($order, $status))
            ->mapWithKeys(fn (string $status): array => [$status => $this->statusActionLabel($status)])
            ->all();
    }

    public function orderWarnings(Order $order): array
    {
        $warnings = [];

        if ($order->status === 'cancelled') {
            return $warnings;
        }

        if ((float) $order->balance > 0) {
            $warnings[] = 'Unpaid balance: GHS '.number_format((float) $order->balance, 2);
        }

        if ((int) ($order->garment_tags_count ?? 0) === 0 && ! in_array($order->status, ['received', 'pending_pickup', 'picked_up', 'cancelled'], true)) {
            $warnings[] = 'No garment tags generated.';
        }

        if ((int) ($order->pickup_tasks_count ?? 0) > 0 && ! in_array($order->status, ['received', 'processing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
            $warnings[] = 'Pickup is scheduled but not completed.';
        }

        if ((int) ($order->delivery_tasks_count ?? 0) > 0 && ! in_array($order->status, ['ready', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
            $warnings[] = 'Delivery is scheduled before the order is ready.';
        }

        $exceptions = LaundryWorkflow::exceptionSummary($order);

        if (array_sum($exceptions) > 0) {
            $warnings[] = 'Exceptions: '.$exceptions['missing'].' missing, '.$exceptions['damaged'].' damaged, '.$exceptions['rewash'].' rewash.';
        }

        return $warnings;
    }

    private function subtotal(): float
    {
        return round(collect($this->rows)->sum(fn (array $row) => (float) ($row['amount'] ?? 0)), 2);
    }

    private function tax(): float
    {
        return round($this->subtotal() * ($this->taxRate / 100), 2);
    }

    private function total(): float
    {
        return round($this->subtotal() + $this->tax(), 2);
    }

    private function nextOrderNumber(): string
    {
        $prefix = 'JW-'.now()->format('Ymd').'-';
        $count = Order::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('order_number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function createRequestedTasks(Order $order): void
    {
        $order->loadMissing('customer');

        if ($this->requestPickup === '1') {
            LaundryWorkflow::createTaskForOrder(
                $order,
                'door_pickup',
                Carbon::parse($this->pickup_date.' '.$this->pickup_time),
            );
        }

        if ($this->requestDelivery === '1') {
            LaundryWorkflow::createTaskForOrder(
                $order,
                'door_delivery',
                Carbon::parse($this->delivery_date.' '.$this->delivery_time),
            );
        }
    }

    private function openTaskModal(int $orderId, string $modal): void
    {
        $order = $this->orderQuery()->with('customer')->findOrFail($orderId);
        $task = $this->taskFor($order, $modal);

        $this->modalOrderId = $order->id;
        $this->activeModal = $modal;
        $this->taskModalHasExisting = (bool) $task;
        $this->actionModalReadOnly = $this->isOrderCompleted($order);
        $this->task_date = $task?->scheduled_at?->toDateString() ?? ($this->actionModalReadOnly ? '' : now()->toDateString());
        $this->task_time = $task?->scheduled_at?->format('H:i') ?? ($this->actionModalReadOnly ? '' : ($modal === 'delivery' ? now()->addHour()->format('H:i') : now()->format('H:i')));
        $this->task_address = (string) ($task?->address ?? $order->customer?->address);
        $this->task_assigned_to = $task?->assigned_to;
        $this->task_delivery_zone_id = $task?->delivery_zone_id;
        $this->resetValidation();
    }

    private function modalOrder(): ?Order
    {
        return $this->modalOrderId
            ? $this->orderQuery()->with(['customer', 'items.product', 'items.service', 'payments'])->find($this->modalOrderId)
            : null;
    }

    private function syncOrderPaymentSummary(Order $order): void
    {
        $total = (float) ($order->total_amount ?: $order->total);
        $paid = round((float) Payment::query()->where('order_id', $order->id)->sum('amount'), 2);
        $balance = max($total - $paid, 0);

        $order->update([
            'total_amount' => $total,
            'amount_paid' => $paid,
            'balance' => $balance,
            'payment_status' => $this->paymentStatusFor($total, $paid),
        ]);
    }

    private function createOrderPayment(Order $order, array $line, ?string $notes): Payment
    {
        $paymentNumber = $this->nextPaymentNumber();
        $data = [
            'branch_id' => auth()->user()?->branch_id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'amount' => round((float) $line['amount'], 2),
            'received_by' => auth()->id(),
        ];

        foreach (['payment_number', 'receipt_number', 'receipt_no'] as $column) {
            if (Schema::hasColumn('payments', $column)) {
                $data[$column] = $paymentNumber;
            }
        }

        $method = $line['method'] ?? 'cash';

        if (Schema::hasColumn('payments', 'payment_method')) {
            $data['payment_method'] = $method;
        }

        if (Schema::hasColumn('payments', 'method')) {
            $data['method'] = $method;
        }

        if (Schema::hasColumn('payments', 'reference')) {
            $data['reference'] = $line['reference'] ?: null;
        }

        if (Schema::hasColumn('payments', 'notes')) {
            $data['notes'] = $notes;
        }

        if (Schema::hasColumn('payments', 'status')) {
            $data['status'] = 'settled';
        }

        if (Schema::hasColumn('payments', 'paid_at')) {
            $data['paid_at'] = now();
        }

        return Payment::create($data);
    }

    private function createPaymentCorrection(Order $order, float $amount, string $type, string $reason): Payment
    {
        $label = match ($type) {
            'refund' => 'Refund',
            'void' => 'Payment Void',
            default => 'Payment Correction',
        };

        return $this->createOrderPayment($order, [
            'amount' => $amount,
            'method' => match ($type) {
                'refund' => 'refund',
                'void' => 'payment_void',
                default => 'payment_correction',
            },
            'reference' => strtoupper(str_replace(' ', '-', $label)).'-'.now()->format('YmdHis'),
        ], $label.': '.$reason);
    }

    private function nextPaymentNumber(): string
    {
        $prefix = 'JW-PAY-'.now()->format('Ymd').'-';
        $count = Payment::withoutGlobalScopes()
            ->where(function (Builder $query) use ($prefix): void {
                foreach (['payment_number', 'receipt_number', 'receipt_no'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $query->orWhere($column, 'like', $prefix.'%');
                    }
                }
            })
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function modalPaymentTotal(): float
    {
        return round(
            collect($this->paymentLines)->sum(fn (array $line): float => (float) ($line['amount'] ?? 0))
            + $this->modalLoyaltyRedeemValue(),
            2
        );
    }

    private function modalPaymentRemaining(): float
    {
        $order = $this->modalOrder();
        $balance = $order ? (float) $order->balance : 0;

        return max(round($balance - $this->modalPaymentTotal(), 2), 0);
    }

    private function modalLoyaltyRedeemValue(): float
    {
        $order = $this->modalOrder();

        return $order
            ? min(app(LoyaltyService::class)->moneyValueForPoints((int) $this->loyaltyRedeemPoints, $order->branch_id), (float) $order->balance)
            : 0;
    }

    private function modalLoyaltySummary(): array
    {
        $order = $this->modalOrder();
        $customer = $order?->customer;
        $loyalty = app(LoyaltyService::class);

        return [
            'points' => (int) ($customer?->loyalty_points ?? 0),
            'minimum' => $order ? $loyalty->minimumRedemptionPoints($order->branch_id) : 0,
            'max_redeemable' => $order && $customer ? $loyalty->maxRedeemablePoints($customer, (float) $order->balance, $order->branch_id) : 0,
            'redeem_value' => $this->modalLoyaltyRedeemValue(),
            'enabled' => $order ? $loyalty->loyaltyEnabled($order->branch_id) : false,
        ];
    }

    private function taskFor(Order $order, string $modal): ?PickupDeliveryTask
    {
        $type = $modal === 'delivery' ? 'door_delivery' : 'door_pickup';

        return PickupDeliveryTask::query()
            ->where('branch_id', $order->branch_id)
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->latest('id')
            ->first();
    }

    private function tagRowsFromExistingTags($tags): array
    {
        return $tags
            ->groupBy(fn (GarmentTag $tag): string => implode('|', [
                $tag->order_item_id ?: '',
                $tag->garment_type ?: '',
                $tag->color ?: '',
                $tag->condition ?: '',
            ]))
            ->map(fn ($group): array => [
                'order_item_id' => $group->first()->order_item_id,
                'garment_type' => $group->first()->garment_type,
                'quantity' => $group->count(),
                'color' => (string) $group->first()->color,
                'condition' => (string) $group->first()->condition,
            ])
            ->values()
            ->all();
    }

    private function syncTagRows(Order $order, array $rows): array
    {
        $availableTags = $order->garmentTags()->orderBy('id')->get()->values();
        $created = collect();
        $updated = 0;
        $removed = 0;

        foreach ($rows as $row) {
            for ($i = 0; $i < (int) $row['quantity']; $i++) {
                $tag = $availableTags->shift();
                $data = [
                    'order_item_id' => $row['order_item_id'] ?: null,
                    'garment_type' => $row['garment_type'],
                    'color' => $row['color'] ?: null,
                    'condition' => $row['condition'] ?: null,
                ];

                if ($tag) {
                    $tag->update($data);
                    $updated++;
                    continue;
                }

                $tagCode = $this->nextTagCode();
                $created->push(GarmentTag::create(array_merge($data, [
                    'order_id' => $order->id,
                    'tag_code' => $tagCode,
                    'barcode_payload' => $tagCode,
                    'status' => 'received',
                    'is_scanned' => false,
                ])));
            }
        }

        foreach ($availableTags as $tag) {
            if ((bool) $tag->is_scanned) {
                continue;
            }

            $tag->delete();
            $removed++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
        ];
    }

    private function isOrderCompleted(Order $order): bool
    {
        return $order->status === 'delivered' || (bool) $order->completed_at;
    }

    private function orderHasPayment(Order $order): bool
    {
        $paymentsCount = $order->payments_count ?? null;

        return (float) $order->amount_paid > 0
            || (is_numeric($paymentsCount) && (int) $paymentsCount > 0)
            || $order->payments()->exists();
    }

    private function nextTagCode(): string
    {
        $prefix = 'TAG-'.now()->format('Ymd').'-';
        $count = GarmentTag::where('tag_code', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    private function deliveryStaff()
    {
        return User::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->role('Delivery Staff')
            ->orderBy('name')
            ->get();
    }

    private function deliveryZones()
    {
        return DeliveryZone::query()
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', auth()->user()?->branch_id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function preparePriceOverridesForSave(): bool
    {
        foreach ($this->rows as $index => $row) {
            $defaultPrice = $this->defaultUnitPriceForRow($row);

            if ($defaultPrice === null) {
                continue;
            }

            $this->rows[$index]['original_unit_price'] = $defaultPrice;
            $isOverride = round((float) $row['unit_price'], 2) !== round($defaultPrice, 2);

            if (! $isOverride) {
                $this->rows[$index]['price_override_enabled'] = false;
                $this->rows[$index]['price_override_reason'] = '';
                continue;
            }

            if (! $this->canOverridePrices()) {
                $this->rows[$index]['unit_price'] = $defaultPrice;
                $this->rows[$index]['price_override_enabled'] = false;
                $this->rows[$index]['price_override_reason'] = '';
                session()->flash('error', 'Unit prices were reset because your account cannot override rate chart prices.');
                $this->recalculateRows();
                return false;
            }

            if (blank($row['price_override_reason'] ?? null)) {
                $this->addError("rows.$index.price_override_reason", 'A reason is required when overriding the rate chart price.');
                return false;
            }

            $this->rows[$index]['price_override_enabled'] = true;
        }

        return true;
    }

    private function validatePricedRowsForSave(): bool
    {
        foreach ($this->rows as $index => $row) {
            $defaultPrice = $this->defaultUnitPriceForRow($row);

            if ($defaultPrice === null) {
                $this->addError(
                    "rows.$index.unit_price",
                    'No rate is configured for this product/service combination. Add a rate chart price before saving the order.'
                );

                return false;
            }

            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);

            if ($unitPrice > 0) {
                continue;
            }

            $this->addError(
                "rows.$index.unit_price",
                'Choose a product/service rate with a price greater than zero before saving the order.'
            );

            return false;
        }

        return true;
    }

    private function validatedSubscriptionForSave(): ?CustomerSubscription
    {
        if (! $this->customer_subscription_id) {
            $this->addError('customer_subscription_id', 'Select an active customer subscription.');
            return null;
        }

        $subscription = CustomerSubscription::withoutGlobalScopes()
            ->with('plan')
            ->where('customer_id', $this->customer_id)
            ->where(function (Builder $query): void {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('branch_id', auth()->user()?->branch_id));
            })
            ->find($this->customer_subscription_id);

        if (! $subscription || ! $subscription->isUsableForService()) {
            $this->addError('customer_subscription_id', 'Subscription is not active, has expired, or has no remaining uses.');
            return null;
        }

        $serviceIds = collect($this->rows)
            ->pluck('laundry_service_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($serviceIds->count() !== 1 || (int) $subscription->plan?->laundry_service_id !== $serviceIds->first()) {
            $this->addError('customer_subscription_id', 'Subscription can only cover orders for its package service.');
            return null;
        }

        $uses = $this->subscriptionUsesForRows();

        if ($uses > $subscription->remainingUses()) {
            $this->addError('customer_subscription_id', 'Subscription does not have enough remaining uses for this order.');
            return null;
        }

        return $subscription;
    }

    private function subscriptionUsesForRows(): int
    {
        return (int) collect($this->rows)->sum(fn (array $row): int => max((int) ($row['quantity'] ?? 0), 0));
    }

    private function activeCustomerSubscriptions()
    {
        if (! $this->customer_id) {
            return collect();
        }

        return CustomerSubscription::withoutGlobalScopes()
            ->with('plan.service')
            ->where('customer_id', $this->customer_id)
            ->where('status', 'active')
            ->whereDate('ends_at', '>=', today())
            ->where(function (Builder $query): void {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('branch_id', auth()->user()?->branch_id));
            })
            ->get()
            ->filter(fn (CustomerSubscription $subscription): bool => $subscription->remainingUses() > 0)
            ->values();
    }

    private function defaultUnitPriceForRow(array $row): ?float
    {
        $productId = $row['product_id'] ?? '';
        $serviceId = $row['laundry_service_id'] ?? '';

        if (! $productId || ! $serviceId) {
            return null;
        }

        $rate = RateChart::where('product_id', $productId)
            ->where('laundry_service_id', $serviceId)
            ->where(function (Builder $query): void {
                $query->where('branch_id', auth()->user()?->branch_id)->orWhereNull('branch_id');
            })
            ->first();

        return $rate ? (float) $rate->price : null;
    }

    private function canOverridePrices(): bool
    {
        return (bool) auth()->user()?->can('rate-chart.manage');
    }

    private function applyOrderFilter(Builder $query, string $filter): Builder
    {
        if (array_key_exists($filter, self::STATUSES)) {
            return $query->where('status', $filter);
        }

        return match ($filter) {
            'unpaid' => $query->where('payment_status', 'unpaid'),
            'part_paid' => $query->whereIn('payment_status', ['part_paid', 'partial']),
            'paid' => $query->where('payment_status', 'paid'),
            'needs_pickup' => $query->whereHas('pickupDeliveryTasks', fn (Builder $taskQuery) => $taskQuery
                ->where('type', 'door_pickup')
                ->whereNotIn('status', ['completed', 'cancelled'])),
            'ready_delivery' => $query->whereIn('status', ['ready', 'out_for_delivery']),
            'has_exceptions' => $query->whereHas('garmentTags', fn (Builder $tagQuery) => $tagQuery->whereIn('status', ['missing', 'damaged', 'rewash'])),
            'completed_today' => $query->where('status', 'delivered')->whereDate('completed_at', today()),
            default => $query,
        };
    }

    private function validateStatusForSave(): bool
    {
        if ($this->status === 'cancelled') {
            $this->addError('status', 'Cancelling orders is disabled.');
            return false;
        }

        if (! $this->editingId) {
            if ($this->status === 'delivered' && $this->total() > 0) {
                $this->addError('status', 'New unpaid orders cannot start as delivered.');
                return false;
            }

            return true;
        }

        $order = $this->orderQuery()->find((int) $this->editingId);

        if (! $order) {
            return true;
        }

        $error = $this->statusTransitionError($order, $this->status);

        if ($error) {
            $this->addError('status', $error);
            return false;
        }

        return true;
    }

    private function statusTransitionError(Order $order, string $targetStatus): ?string
    {
        if ($targetStatus === 'cancelled') {
            return 'Cancelling orders is disabled.';
        }

        if ($order->status === $targetStatus) {
            return null;
        }

        if (! in_array($targetStatus, self::STATUS_PROGRESS[$order->status] ?? [], true)) {
            return 'Invalid status move from '.(self::STATUSES[$order->status] ?? $order->status).' to '.(self::STATUSES[$targetStatus] ?? $targetStatus).'.';
        }

        if ($targetStatus === 'delivered' && (float) $order->balance > 0) {
            return 'Cannot mark delivered while a balance remains.';
        }

        if ($targetStatus === 'out_for_delivery' && ! $this->taskFor($order, 'delivery')) {
            return 'Schedule delivery before moving this order out for delivery.';
        }

        return null;
    }

    private function statusActionLabel(string $status): string
    {
        return match ($status) {
            'picked_up' => 'Mark Picked Up',
            'received' => 'Mark Received',
            'processing' => 'Start Processing',
            'ready' => 'Mark Ready',
            'out_for_delivery' => 'Send Out',
            'delivered' => 'Mark Delivered',
            default => self::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function timelineOrder(): ?Order
    {
        return $this->timelineOrderId
            ? $this->orderQuery()->with(['customer', 'garmentTags'])->find($this->timelineOrderId)
            : null;
    }

    private function timelineEvents()
    {
        $order = $this->timelineOrder();

        return $order
            ? LaundryWorkflow::timeline($order)->map(fn (ActivityLog $event): array => [
                'title' => $this->timelineTitle($event->action),
                'actor' => $event->user?->name ?? 'System',
                'time' => $event->created_at->format('M d, Y h:i A'),
                'from' => $this->timelineValuePairs($event->old_values),
                'to' => $this->timelineValuePairs($event->new_values),
            ])
            : collect();
    }

    private function timelineTitle(string $action): string
    {
        return str($action)
            ->replace(['.', '_'], ' ')
            ->headline()
            ->toString();
    }

    private function timelineValuePairs(?array $values): array
    {
        return collect($values ?? [])
            ->map(fn ($value, $key): array => [
                'key' => str((string) $key)->replace('_', ' ')->headline()->toString(),
                'value' => $this->timelineValue($value),
            ])
            ->values()
            ->all();
    }

    private function timelineValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return collect($value)
                    ->map(fn ($item): string => is_array($item)
                        ? collect($item)->map(fn ($itemValue, $itemKey): string => str((string) $itemKey)->replace('_', ' ')->headline().': '.$this->timelineValue($itemValue))->implode(' | ')
                        : $this->timelineValue($item))
                    ->implode('; ');
            }

            return collect($value)
                ->map(fn ($itemValue, $itemKey): string => str((string) $itemKey)->replace('_', ' ')->headline().': '.$this->timelineValue($itemValue))
                ->implode(' | ');
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : json_encode($value);
        }

        return (string) $value;
    }

    private function updateOrder(array $validated): bool
    {
        $order = $this->orderQuery()
            ->with(['items', 'payments'])
            ->findOrFail((int) $this->editingId);

        $existingItemIds = $order->items->pluck('id')->all();
        $submittedItemIds = collect($this->rows)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();
        $removedItemIds = array_values(array_diff($existingItemIds, $submittedItemIds));

        if ($removedItemIds !== [] && GarmentTag::whereIn('order_item_id', $removedItemIds)->exists()) {
            session()->flash('error', 'One or more removed rows already have garment tags. Remove the tags before deleting those rows.');
            return false;
        }

        DB::transaction(function () use ($order, $validated, $removedItemIds): void {
            $oldValues = [
                'customer_id' => $order->customer_id,
                'status' => $order->status,
                'subtotal' => $order->subtotal,
                'total' => $order->total,
                'notes' => $order->notes,
            ];

            if ($removedItemIds !== []) {
                $order->items()->whereIn('id', $removedItemIds)->delete();
            }

            $subtotal = 0.0;

            foreach ($this->rows as $row) {
                $product = Product::find($row['product_id']);
                $service = LaundryService::find($row['laundry_service_id']);
                $rate = RateChart::where('product_id', $row['product_id'])
                    ->where('laundry_service_id', $row['laundry_service_id'])
                    ->where(function (Builder $query): void {
                        $query->where('branch_id', auth()->user()?->branch_id)->orWhereNull('branch_id');
                    })
                    ->first();
                $lineTotal = round(((float) $row['quantity']) * ((float) $row['unit_price']), 2);
                $lineTax = round($lineTotal * ($this->taxRate / 100), 2);
                $originalUnitPrice = $this->defaultUnitPriceForRow($row);
                $isOverride = $originalUnitPrice !== null && round((float) $row['unit_price'], 2) !== round($originalUnitPrice, 2);
                $subtotal += $lineTotal;

                $payload = [
                    'product_id' => $product?->id,
                    'laundry_service_id' => $service?->id,
                    'rate_chart_id' => $rate?->id,
                    'item_name' => trim(($product?->name ?? 'Product').' + '.($service?->name ?? 'Service')),
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'original_unit_price' => $originalUnitPrice,
                    'price_override_reason' => $isOverride ? ($row['price_override_reason'] ?? null) : null,
                    'price_overridden_by' => $isOverride ? auth()->id() : null,
                    'line_total' => $lineTotal,
                    'tax_amount' => $lineTax,
                    'status' => $validated['status'],
                ];

                if (! empty($row['id'])) {
                    $order->items()->whereKey((int) $row['id'])->update($payload);
                } else {
                    $order->items()->create($payload);
                }
            }

            $tax = round($subtotal * ($this->taxRate / 100), 2);
            $total = round($subtotal + $tax, 2);
            $paid = (float) $order->payments()->sum('amount');
            $balance = max($total - $paid, 0);

            $order->update([
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'],
                'payment_status' => $this->paymentStatusFor($total, $paid),
                'subtotal' => $subtotal,
                'total' => $total,
                'total_amount' => $total,
                'amount_paid' => $paid,
                'balance' => $balance,
                'notes' => $validated['notes'] ?? null,
            ]);

            ActivityLog::record('updated', $order, [
                'module' => 'orders',
                'order_number' => $order->order_number,
            ], $oldValues, [
                'customer_id' => $order->customer_id,
                'status' => $order->status,
                'subtotal' => $order->subtotal,
                'total' => $order->total,
                'notes' => $order->notes,
            ]);
        });

        return true;
    }

    private function paymentStatusFor(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'part_paid';
    }

    private function orderQuery(): Builder
    {
        return Order::query()->where('branch_id', auth()->user()?->branch_id);
    }
}
