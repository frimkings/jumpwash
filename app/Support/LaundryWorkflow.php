<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\GarmentTag;
use App\Models\Order;
use App\Models\PickupDeliveryTask;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LaundryWorkflow
{
    public static function syncOrderFromTask(PickupDeliveryTask $task): bool
    {
        if (! $task->order_id) {
            return true;
        }

        $order = $task->order()->first();

        if (! $order) {
            return true;
        }

        $nextStatus = match ($task->type) {
            'door_pickup', 'self_bring' => match ($task->status) {
                'scheduled' => 'pending_pickup',
                'picked_up' => 'picked_up',
                'completed' => 'received',
                'cancelled' => null,
                default => null,
            },
            'door_delivery', 'self_collect' => match ($task->status) {
                'pending', 'assigned' => 'ready',
                'out_for_delivery' => 'out_for_delivery',
                'delivered' => 'delivered',
                default => null,
            },
            default => null,
        };

        if (! $nextStatus) {
            return true;
        }

        if ($nextStatus === 'delivered' && (float) $order->balance > 0) {
            return false;
        }

        self::setOrderStatus($order, $nextStatus, 'task.status_synced', [
            'task_id' => $task->id,
            'task_type' => $task->type,
            'task_status' => $task->status,
        ]);

        return true;
    }

    public static function syncOrderFromGarments(Order $order): void
    {
        self::setOrderStatus($order, 'ready', 'garments.closed', [
            'order_number' => $order->order_number,
        ]);
    }

    public static function createTaskForOrder(Order $order, string $type, ?Carbon $scheduledAt = null, ?int $assignedTo = null): PickupDeliveryTask
    {
        $status = in_array($type, ['door_delivery', 'self_collect'], true) ? 'pending' : 'scheduled';

        $task = PickupDeliveryTask::firstOrCreate(
            [
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'type' => $type,
            ],
            [
                'customer_id' => $order->customer_id,
                'assigned_to' => $assignedTo,
                'status' => $status,
                'scheduled_at' => $scheduledAt ?: now(),
                'address' => $order->customer?->address,
            ],
        );

        ActivityLog::record('workflow.task_created', $task, [
            'module' => str_contains($type, 'delivery') || $type === 'self_collect' ? 'deliveries' : 'pickups',
            'order_number' => $order->order_number,
            'task_type' => $type,
        ]);

        self::syncOrderFromTask($task);

        return $task;
    }

    public static function timeline(Order $order): Collection
    {
        $orderNumber = $order->order_number;
        $tagIds = $order->garmentTags()->pluck('id');
        $taskIds = PickupDeliveryTask::query()->where('order_id', $order->id)->pluck('id');

        return ActivityLog::query()
            ->with('user')
            ->where(function (Builder $query) use ($order, $orderNumber, $tagIds, $taskIds): void {
                $query->where(function (Builder $query) use ($order): void {
                    $query->where('subject_type', Order::class)->where('subject_id', $order->id);
                })
                    ->orWhere('properties->order_number', $orderNumber)
                    ->when($tagIds->isNotEmpty(), fn (Builder $query) => $query->orWhere(function (Builder $query) use ($tagIds): void {
                        $query->where('subject_type', GarmentTag::class)->whereIn('subject_id', $tagIds);
                    }))
                    ->when($taskIds->isNotEmpty(), fn (Builder $query) => $query->orWhere(function (Builder $query) use ($taskIds): void {
                        $query->where('subject_type', PickupDeliveryTask::class)->whereIn('subject_id', $taskIds);
                    }));
            })
            ->oldest()
            ->limit(100)
            ->get();
    }

    public static function exceptionSummary(Order $order): array
    {
        $counts = $order->garmentTags()
            ->whereIn('status', ['missing', 'damaged', 'rewash'])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'missing' => (int) ($counts['missing'] ?? 0),
            'damaged' => (int) ($counts['damaged'] ?? 0),
            'rewash' => (int) ($counts['rewash'] ?? 0),
        ];
    }

    private static function setOrderStatus(Order $order, string $status, string $action, array $properties = []): void
    {
        if ($order->status === $status) {
            return;
        }

        $oldStatus = $order->status;
        $order->update([
            'status' => $status,
            'completed_at' => $status === 'delivered' ? now() : $order->completed_at,
        ]);

        ActivityLog::record($action, $order, array_merge([
            'module' => 'orders',
            'order_number' => $order->order_number,
        ], $properties), ['status' => $oldStatus], ['status' => $status]);
    }
}
