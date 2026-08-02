<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PickupDeliveryTask;
use App\Models\User;
use App\Support\LaundryWorkflow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DeliveryManagement extends Component
{
    public const TYPES = [
        'door_delivery' => 'Door Delivery',
        'self_collect' => 'Self Collect',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'out_for_delivery' => 'Out For Delivery',
        'delivered' => 'Delivered',
        'failed' => 'Failed',
    ];

    public ?int $editingId = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';
    public string $type = 'door_delivery';
    public ?int $customer_id = null;
    public ?int $order_id = null;
    public ?int $delivery_zone_id = null;
    public ?int $assigned_to = null;
    public string $delivery_date = '';
    public string $delivery_time = '';
    public string $address = '';
    public string $status = 'pending';
    public string $signature_data = '';
    public string $delivery_signature_data = '';

    public function mount(): void
    {
        $this->delivery_date = now()->toDateString();
        $this->delivery_time = now()->addHour()->format('H:i');
    }

    public function updatedCustomerId(): void
    {
        if (! $this->customer_id) {
            $this->order_id = null;
            return;
        }

        $customer = $this->customerQuery()->find($this->customer_id);
        $this->address = $this->address ?: (string) $customer?->address;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('branch_id', auth()->user()?->branch_id)],
            'order_id' => ['nullable', Rule::exists('orders', 'id')->where('branch_id', auth()->user()?->branch_id)],
            'delivery_zone_id' => ['nullable', Rule::exists('delivery_zones', 'id')],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('branch_id', auth()->user()?->branch_id)],
            'delivery_date' => ['required', 'date'],
            'delivery_time' => ['required', 'date_format:H:i'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'signature_data' => ['nullable', 'string', 'max:2000'],
            'delivery_signature_data' => ['nullable', 'string'],
        ]);

        $signaturePath = $this->storeSignature($validated['delivery_signature_data'] ?? null, 'delivery');
        $existingTask = $this->editingId ? $this->taskQuery()->findOrFail($this->editingId) : null;
        $oldValues = $existingTask ? $this->auditSnapshot($existingTask) : [];

        if ($validated['status'] === 'delivered' && $this->hasLinkedOrderBalance($validated['order_id'] ?: null)) {
            session()->flash('error', 'Delivery cannot be marked delivered while the linked order still has a balance.');
            return;
        }

        $task = PickupDeliveryTask::updateOrCreate(
            ['id' => $this->editingId],
            [
                'branch_id' => auth()->user()?->branch_id,
                'customer_id' => $validated['customer_id'],
                'order_id' => $validated['order_id'] ?: null,
                'delivery_zone_id' => $validated['delivery_zone_id'] ?: null,
                'assigned_to' => $validated['assigned_to'] ?: null,
                'type' => $validated['type'],
                'status' => $validated['status'],
                'scheduled_at' => Carbon::parse($validated['delivery_date'].' '.$validated['delivery_time']),
                'completed_at' => $validated['status'] === 'delivered' ? now() : null,
                'address' => $validated['address'] ?: null,
                'signature_data' => $validated['signature_data'] ?: null,
            ],
        );

        if ($signaturePath) {
            $task->update(['delivery_signature_path' => $signaturePath]);
        }

        if (! LaundryWorkflow::syncOrderFromTask($task->fresh())) {
            $task->update(['status' => $oldValues['status'] ?? 'pending', 'completed_at' => null]);
            session()->flash('error', 'Delivery cannot be marked delivered while the linked order still has a balance.');
            return;
        }

        ActivityLog::record($existingTask ? 'updated' : 'created', $task, [
            'module' => 'deliveries',
            'task_type' => $task->type,
        ], $oldValues, $this->auditSnapshot($task->fresh()));

        $this->resetForm();
        session()->flash('status', 'Delivery task saved.');
    }

    public function edit(int $id): void
    {
        $task = $this->taskQuery()->findOrFail($id);

        $this->editingId = $task->id;
        $this->type = $task->type;
        $this->customer_id = $task->customer_id;
        $this->order_id = $task->order_id;
        $this->delivery_zone_id = $task->delivery_zone_id;
        $this->assigned_to = $task->assigned_to;
        $this->delivery_date = $task->scheduled_at?->toDateString() ?? now()->toDateString();
        $this->delivery_time = $task->scheduled_at?->format('H:i') ?? now()->format('H:i');
        $this->address = (string) $task->address;
        $this->status = $task->status;
        $this->signature_data = (string) $task->signature_data;
        $this->delivery_signature_data = '';
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless(array_key_exists($status, self::STATUSES), 422);

        $task = $this->taskQuery()->findOrFail($id);
        $oldStatus = $task->status;

        if ($status === 'delivered' && $this->hasLinkedOrderBalance($task->order_id)) {
            session()->flash('error', 'Delivery cannot be marked delivered while the linked order still has a balance.');
            return;
        }

        $task->update([
            'status' => $status,
            'completed_at' => $status === 'delivered' ? now() : null,
        ]);

        if (! LaundryWorkflow::syncOrderFromTask($task->fresh())) {
            $task->update(['status' => $oldStatus, 'completed_at' => null]);
            session()->flash('error', 'Delivery cannot be marked delivered while the linked order still has a balance.');
            return;
        }

        ActivityLog::record('status.changed', $task, [
            'module' => 'deliveries',
            'task_type' => $task->type,
        ], ['status' => $oldStatus], ['status' => $status]);
    }

    public function delete(int $id): void
    {
        $task = $this->taskQuery()->findOrFail($id);
        $oldValues = $this->auditSnapshot($task);
        $task->delete();

        ActivityLog::record('deleted', null, [
            'module' => 'deliveries',
            'task_id' => $id,
            'task_type' => $oldValues['type'] ?? null,
        ], $oldValues);

        $this->resetForm();
        session()->flash('status', 'Delivery task deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->type = 'door_delivery';
        $this->customer_id = null;
        $this->order_id = null;
        $this->delivery_zone_id = null;
        $this->assigned_to = null;
        $this->delivery_date = now()->toDateString();
        $this->delivery_time = now()->addHour()->format('H:i');
        $this->address = '';
        $this->status = 'pending';
        $this->signature_data = '';
        $this->delivery_signature_data = '';
        $this->resetValidation();
    }

    public function render()
    {
        $tasks = $this->taskQuery()
            ->with(['customer', 'order', 'assignedStaff.staffProfile', 'deliveryZone'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('address', 'like', '%'.$this->search.'%')
                    ->orWhere('signature_data', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->orderByRaw("case status when 'pending' then 1 when 'assigned' then 2 when 'out_for_delivery' then 3 when 'delivered' then 4 else 5 end")
            ->orderBy('scheduled_at')
            ->limit(250)
            ->get();

        return view('livewire.delivery-management', [
            'tasks' => $tasks,
            'calendarDays' => $tasks->groupBy(fn (PickupDeliveryTask $task) => $task->scheduled_at?->toDateString() ?? 'Unscheduled'),
            'routes' => $tasks->groupBy(fn (PickupDeliveryTask $task) => $task->deliveryZone?->name ?? 'Unzoned Route'),
            'customers' => $this->customerQuery()->where('is_active', true)->orderBy('name')->get(),
            'orders' => Order::query()
                ->where('branch_id', auth()->user()?->branch_id)
                ->when($this->customer_id, fn (Builder $query) => $query->where('customer_id', $this->customer_id))
                ->latest()
                ->limit(50)
                ->get(),
            'staff' => User::query()
                ->where('branch_id', auth()->user()?->branch_id)
                ->where('is_active', true)
                ->role('Delivery Staff')
                ->orderBy('name')
                ->get(),
            'zones' => DeliveryZone::query()
                ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', auth()->user()?->branch_id))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
        ])->layout('layouts.app', ['title' => 'Delivery Management']);
    }

    private function taskQuery(): Builder
    {
        return PickupDeliveryTask::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->whereIn('type', array_keys(self::TYPES));
    }

    private function hasLinkedOrderBalance(?int $orderId): bool
    {
        if (! $orderId) {
            return false;
        }

        $order = Order::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->find($orderId);

        return $order && (float) $order->balance > 0;
    }

    private function customerQuery(): Builder
    {
        return Customer::query()->where('branch_id', auth()->user()?->branch_id);
    }

    private function storeSignature(?string $dataUri, string $prefix): ?string
    {
        if (! $dataUri || ! str_starts_with($dataUri, 'data:image/png;base64,')) {
            return null;
        }

        $image = base64_decode(Str::after($dataUri, 'base64,'), true);

        if ($image === false) {
            return null;
        }

        $path = 'signatures/'.$prefix.'-'.now()->format('YmdHis').'-'.Str::random(8).'.png';
        Storage::disk('public')->put($path, $image);

        return $path;
    }

    private function auditSnapshot(PickupDeliveryTask $task): array
    {
        return [
            'type' => $task->type,
            'status' => $task->status,
            'customer_id' => $task->customer_id,
            'order_id' => $task->order_id,
            'delivery_zone_id' => $task->delivery_zone_id,
            'assigned_to' => $task->assigned_to,
            'scheduled_at' => $task->scheduled_at?->toDateTimeString(),
            'address' => $task->address,
            'signature_data' => $task->signature_data,
        ];
    }
}
