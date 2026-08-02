<?php

namespace App\Livewire;

use App\Models\LaundryService;
use App\Models\OrderItem;
use App\Support\PerformanceCache;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ServicesManager extends Component
{
    public ?int $editingId = null;
    public bool $showFormModal = false;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $name = '';
    public string $description = '';
    public string $price = '0';
    public string $tax_percentage = '0';
    public string $unit = 'kg';
    public string $turnaround_hours = '24';
    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'tax_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'turnaround_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'is_active' => ['boolean'],
        ]);

        $code = str($validated['name'])->slug('-')->upper()->toString();

        LaundryService::updateOrCreate(
            ['id' => $this->editingId],
            [
                'branch_id' => auth()->user()?->branch_id,
                'name' => $validated['name'],
                'code' => $code,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'tax_percentage' => $validated['tax_percentage'],
                'unit' => $validated['unit'],
                'turnaround_hours' => $validated['turnaround_hours'],
                'is_active' => $validated['is_active'],
            ],
        );

        $this->resetForm();
        PerformanceCache::forgetLookups();
        session()->flash('status', 'Service saved.');
    }

    public function edit(int $id): void
    {
        $service = $this->serviceQuery()->findOrFail($id);

        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->description = (string) $service->description;
        $this->price = (string) $service->price;
        $this->tax_percentage = (string) $service->tax_percentage;
        $this->unit = $service->unit;
        $this->turnaround_hours = (string) $service->turnaround_hours;
        $this->is_active = (bool) $service->is_active;
        $this->showFormModal = true;
    }

    public function toggleStatus(int $id): void
    {
        $service = $this->serviceQuery()->findOrFail($id);
        $service->update(['is_active' => ! $service->is_active]);
        PerformanceCache::forgetLookups();
    }

    public function delete(int $id): void
    {
        $service = $this->serviceQuery()->findOrFail($id);

        if (OrderItem::where('laundry_service_id', $service->id)->exists()) {
            session()->flash('error', 'Service is already used by orders and cannot be deleted.');
            return;
        }

        $service->delete();
        $this->resetForm();
        PerformanceCache::forgetLookups();
        session()->flash('status', 'Service deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->price = '0';
        $this->tax_percentage = '0';
        $this->unit = 'kg';
        $this->turnaround_hours = '24';
        $this->is_active = true;
        $this->showFormModal = false;
        $this->resetValidation();
    }

    public function render()
    {
        $services = $this->serviceQuery()
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('name')
            ->limit(250)
            ->get();

        return view('livewire.services-manager', ['services' => $services])
            ->layout('layouts.app', ['title' => 'Services']);
    }

    private function serviceQuery(): Builder
    {
        return LaundryService::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('branch_id');
            });
    }
}
