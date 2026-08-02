<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\CustomerSubscription;
use App\Models\Order;
use App\Models\PickupDeliveryTask;
use Illuminate\Support\Facades\Schema;

class NotificationGenerator
{
    public const TYPES = [
        'new_order' => 'New Order',
        'ready_for_delivery' => 'Ready For Delivery',
        'outstanding_balance' => 'Outstanding Balance',
        'expiring_subscription' => 'Expiring Subscription',
    ];

    public const CHANNELS = [
        'local' => 'Offline Local',
        'sms' => 'SMS Ready',
        'whatsapp' => 'WhatsApp Ready',
    ];

    public function sync(?int $branchId): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Order::query()
            ->with('customer')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('created_at', today())
            ->latest()
            ->limit(30)
            ->get()
            ->each(fn (Order $order) => $this->store(
                'new_order',
                'New Order',
                $order->order_number.' created for '.($order->customer?->name ?? 'customer'),
                $branchId,
                $order,
            ));

        Order::query()
            ->with('customer')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['ready', 'ready_for_delivery'])
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->each(fn (Order $order) => $this->store(
                'ready_for_delivery',
                'Ready For Delivery',
                $order->order_number.' is ready for '.($order->customer?->name ?? 'customer'),
                $branchId,
                $order,
            ));

        Order::query()
            ->with('customer')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where(function ($query) {
                $query->where('balance', '>', 0)->orWhereIn('payment_status', ['unpaid', 'part_paid', 'partial']);
            })
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->each(fn (Order $order) => $this->store(
                'outstanding_balance',
                'Outstanding Balance',
                ($order->customer?->name ?? 'Customer').' owes GHS '.number_format((float) $order->balance, 2).' on '.$order->order_number,
                $branchId,
                $order,
            ));

        CustomerSubscription::withoutGlobalScopes()
            ->with('customer')
            ->where('status', 'active')
            ->whereBetween('ends_at', [today(), today()->addDays(7)])
            ->when($branchId, function ($query) use ($branchId) {
                if (Schema::hasColumn('customer_subscriptions', 'branch_id')) {
                    $query->where('branch_id', $branchId);
                } else {
                    $query->whereHas('customer', fn ($query) => $query->where('branch_id', $branchId));
                }
            })
            ->limit(30)
            ->get()
            ->each(fn (CustomerSubscription $subscription) => $this->store(
                'expiring_subscription',
                'Expiring Subscription',
                ($subscription->customer?->name ?? 'Customer').' subscription expires on '.$subscription->ends_at?->format('M d, Y'),
                $branchId,
                $subscription,
            ));
    }

    private function store(string $type, string $title, string $message, ?int $branchId, object $subject): void
    {
        AppNotification::firstOrCreate(
            [
                'type' => $type,
                'channel' => 'local',
                'notifiable_subject_type' => $subject::class,
                'notifiable_subject_id' => $subject->id,
            ],
            [
                'branch_id' => $branchId,
                'title' => $title,
                'message' => $message,
                'status' => 'unread',
                'payload' => [
                    'future_channels' => ['sms', 'whatsapp'],
                    'integration_status' => 'offline_local_only',
                ],
            ],
        );
    }
}
