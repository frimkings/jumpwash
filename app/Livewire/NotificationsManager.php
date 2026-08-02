<?php

namespace App\Livewire;

use App\Models\AppNotification;
use App\Support\NotificationGenerator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class NotificationsManager extends Component
{
    public string $typeFilter = 'all';
    public string $statusFilter = 'all';
    public string $channelFilter = 'all';

    public function mount(NotificationGenerator $generator): void
    {
        $generator->sync(auth()->user()?->branch_id);
    }

    public function refreshNotifications(NotificationGenerator $generator): void
    {
        $generator->sync(auth()->user()?->branch_id);
        session()->flash('status', 'Offline notifications refreshed.');
    }

    public function markRead(int $id): void
    {
        $this->notificationQuery()->findOrFail($id)->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markUnread(int $id): void
    {
        $this->notificationQuery()->findOrFail($id)->update([
            'status' => 'unread',
            'read_at' => null,
        ]);
    }

    public function render()
    {
        $notifications = $this->notificationQuery()
            ->when($this->typeFilter !== 'all', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->channelFilter !== 'all', fn (Builder $query) => $query->where('channel', $this->channelFilter))
            ->latest()
            ->limit(80)
            ->get();

        return view('livewire.notifications-manager', [
            'notifications' => $notifications,
            'types' => NotificationGenerator::TYPES,
            'channels' => NotificationGenerator::CHANNELS,
            'unreadCount' => $this->notificationQuery()->where('status', 'unread')->count(),
        ])->layout('layouts.app', ['title' => 'Notifications']);
    }

    private function notificationQuery(): Builder
    {
        return AppNotification::query()->where('branch_id', auth()->user()?->branch_id);
    }
}
