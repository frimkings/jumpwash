<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Customer;
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

class PickupManagement extends Component
{
    public const TYPES = [
        'door_pickup' => 'Door Pickup',
        'self_bring' => 'Self Bring',
    ];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'picked_up' => 'Picked Up',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public ?int $editingId = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';
    public string $type = 'door_pickup';
    public ?int $customer_id = null;
    public ?int $order_id = null;
    public ?int $assigned_to = null;
    public string $pickup_date = '';
    public string $pickup_time = '';
    public string $address = '';
    public string $status = 'scheduled';
    public string $pickup_signature_data = '';

    public function mount(): void
    {
        $this->pickup_date = now()->toDateString();
        $this->pickup_time = now()->format('H:i');
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
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('branch_id', auth()->user()?->branch_id)],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'pickup_signature_data' => ['nullable', 'string'],
        ]);

        $signaturePath = $this->storeSignature($validated['pickup_signature_data'] ?? null, 'pickup');
        $existingTask = $this->editingId ? $this->taskQuery()->findOrFail($this->editingId) : null;
        $oldValues = $existingTask ? $this->auditSnapshot($existingTask) : [];

        $task = PickupDeliveryTask::updateOrCreate(
            ['id' => $this->editingId],
            [
                'branch_id' => auth()->user()?->branch_id,
                'customer_id' => $validated['customer_id'],
                'order_id' => $validated['order_id'] ?: null,
                'assigned_to' => $validated['assigned_to'] ?: null,
                'type' => $validated['type'],
                'status' => $validated['status'],
                'scheduled_at' => Carbon::parse($validated['pickup_date'].' '.$validated['pickup_time']),
                'completed_at' => $validated['status'] === 'completed' ? now() : null,
                'address' => $validated['address'] ?: null,
            ],
        );

        if ($signaturePath) {
            $task->update(['pickup_signature_path' => $signaturePath]);
        }

        LaundryWorkflow::syncOrderFromTask($task->fresh());

        ActivityLog::record($existingTask ? 'updated' : 'created', $task, [
            'module' => 'pickups',
            'task_type' => $task->type,
        ], $oldValues, $this->auditSnapshot($task->fresh()));

        $this->resetForm();
        session()->flash('status', 'Pickup task saved.');
    }

    public function edit(int $id): void
    {
        $task = $this->taskQuery()->findOrFail($id);

        $this->editingId = $task->id;
        $this->type = $task->type;
        $this->customer_id = $task->customer_id;
        $this->order_id = $task->order_id;
        $this->assigned_to = $task->assigned_to;
        $this->pickup_date = $task->scheduled_at?->toDateString() ?? now()->toDateString();
        $this->pickup_time = $task->scheduled_at?->format('H:i') ?? now()->format('H:i');
        $this->address = (string) $task->address;
        $this->status = $task->status;
        $this->pickup_signature_data = '';
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless(array_key_exists($status, self::STATUSES), 422);

        $task = $this->taskQuery()->findOrFail($id);
        $oldStatus = $task->status;

        $task->update([
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        LaundryWorkflow::syncOrderFromTask($task->fresh());

        ActivityLog::record('status.changed', $task, [
            'module' => 'pickups',
            'task_type' => $task->type,
        ], ['status' => $oldStatus], ['status' => $status]);
    }

    public function delete(int $id): void
    {
        $task = $this->taskQuery()->findOrFail($id);
        $oldValues = $this->auditSnapshot($task);
        $task->delete();

        ActivityLog::record('deleted', null, [
            'module' => 'pickups',
            'task_id' => $id,
            'task_type' => $oldValues['type'] ?? null,
        ], $oldValues);

        $this->resetForm();
        session()->flash('status', 'Pickup task deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->type = 'door_pickup';
        $this->customer_id = null;
        $this->order_id = null;
        $this->assigned_to = null;
        $this->pickup_date = now()->toDateString();
        $this->pickup_time = now()->format('H:i');
        $this->address = '';
        $this->status = 'scheduled';
        $this->pickup_signature_data = '';
        $this->resetValidation();
    }

    public function render()
    {
        $tasks = $this->taskQuery()
            ->with(['customer', 'order', 'assignedStaff.staffProfile'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('address', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->orderByRaw("case status when 'scheduled' then 1 when 'picked_up' then 2 when 'completed' then 3 else 4 end")
            ->orderBy('scheduled_at')
            ->limit(250)
            ->get();

        return view('livewire.pickup-management', [
            'tasks' => $tasks,
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
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
        ])->layout('layouts.app', ['title' => 'Pickup Management']);
    }

    private function taskQuery(): Builder
    {
        return PickupDeliveryTask::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->whereIn('type', array_keys(self::TYPES));
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
            'assigned_to' => $task->assigned_to,
            'scheduled_at' => $task->scheduled_at?->toDateTimeString(),
            'address' => $task->address,
        ];
    }
}
