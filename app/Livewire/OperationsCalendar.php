<?php

namespace App\Livewire;

use App\Models\CustomerSubscription;
use App\Models\PickupDeliveryTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class OperationsCalendar extends Component
{
    public function render()
    {
        $events = $this->calendarEvents();

        return view('livewire.operations-calendar', [
            'events' => $events,
            'eventJson' => $events->values()->toJson(),
            'counts' => [
                'pickups' => $events->where('extendedProps.category', 'Pickup Schedule')->count(),
                'deliveries' => $events->where('extendedProps.category', 'Delivery Schedule')->count(),
                'assignments' => $events->where('extendedProps.category', 'Staff Assignments')->count(),
                'subscriptions' => $events->where('extendedProps.category', 'Subscription Expiry')->count(),
            ],
        ])->layout('layouts.app', ['title' => 'Operations Calendar']);
    }

    private function calendarEvents(): Collection
    {
        $branchId = auth()->user()?->branch_id;

        $taskEvents = PickupDeliveryTask::query()
            ->with(['customer', 'order', 'assignedStaff'])
            ->where('branch_id', $branchId)
            ->whereNotNull('scheduled_at')
            ->get()
            ->flatMap(function (PickupDeliveryTask $task) {
                $isPickup = in_array($task->type, ['door_pickup', 'self_bring'], true);
                $category = $isPickup ? 'Pickup Schedule' : 'Delivery Schedule';
                $color = $isPickup ? '#2563eb' : '#16a34a';
                $label = $isPickup ? 'Pickup' : 'Delivery';

                $events = [[
                    'id' => 'task-'.$task->id,
                    'title' => $label.': '.($task->customer?->name ?? 'Customer'),
                    'start' => $task->scheduled_at->toIso8601String(),
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'category' => $category,
                        'status' => ucfirst(str_replace('_', ' ', $task->status)),
                        'customer' => $task->customer?->name,
                        'staff' => $task->assignedStaff?->name,
                        'order' => $task->order?->order_number,
                        'address' => $task->address,
                    ],
                ]];

                if ($task->assignedStaff) {
                    $events[] = [
                        'id' => 'assignment-'.$task->id,
                        'title' => 'Staff: '.$task->assignedStaff->name,
                        'start' => $task->scheduled_at->toIso8601String(),
                        'backgroundColor' => '#7c3aed',
                        'borderColor' => '#7c3aed',
                        'extendedProps' => [
                            'category' => 'Staff Assignments',
                            'status' => ucfirst(str_replace('_', ' ', $task->status)),
                            'customer' => $task->customer?->name,
                            'staff' => $task->assignedStaff->name,
                            'order' => $task->order?->order_number,
                            'address' => $task->address,
                        ],
                    ];
                }

                return $events;
            });

        $subscriptionQuery = CustomerSubscription::withoutGlobalScopes()
            ->with(['customer', 'plan'])
            ->whereNotNull('ends_at');

        if (Schema::hasColumn('customer_subscriptions', 'branch_id')) {
            $subscriptionQuery->where('branch_id', $branchId);
        } else {
            $subscriptionQuery->whereHas('customer', fn ($query) => $query->where('branch_id', $branchId));
        }

        $subscriptionEvents = $subscriptionQuery
            ->get()
            ->map(fn (CustomerSubscription $subscription) => [
                'id' => 'subscription-'.$subscription->id,
                'title' => 'Expiry: '.($subscription->customer?->name ?? 'Customer'),
                'start' => $subscription->ends_at->toDateString(),
                'allDay' => true,
                'backgroundColor' => '#dc2626',
                'borderColor' => '#dc2626',
                'extendedProps' => [
                    'category' => 'Subscription Expiry',
                    'status' => ucfirst((string) $subscription->status),
                    'customer' => $subscription->customer?->name,
                    'staff' => null,
                    'order' => $subscription->plan?->name,
                    'address' => null,
                ],
            ]);

        return $taskEvents->concat($subscriptionEvents)->sortBy('start')->values();
    }
}
