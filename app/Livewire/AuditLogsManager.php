<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class AuditLogsManager extends Component
{
    public string $search = '';
    public string $actionFilter = 'all';
    public string $start_date = '';
    public string $end_date = '';

    public function mount(): void
    {
        $this->start_date = today()->subDays(7)->toDateString();
        $this->end_date = today()->toDateString();
    }

    public function render()
    {
        $query = ActivityLog::query()
            ->with('user')
            ->when(auth()->user()?->branch_id, fn (Builder $query) => $query->where('branch_id', auth()->user()->branch_id))
            ->when($this->actionFilter !== 'all', fn (Builder $query) => $query->where('action', $this->actionFilter))
            ->when($this->start_date !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->start_date))
            ->when($this->end_date !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->end_date))
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('action', 'like', '%'.$this->search.'%')
                    ->orWhere('module', 'like', '%'.$this->search.'%')
                    ->orWhere('properties', 'like', '%'.$this->search.'%')
                    ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->latest();

        return view('livewire.audit-logs-manager', [
            'logs' => $query->limit(100)->get(),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ])->layout('layouts.app', ['title' => 'Audit Logs']);
    }
}
