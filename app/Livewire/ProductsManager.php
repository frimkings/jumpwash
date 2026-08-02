<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\RateChart;
use App\Support\PerformanceCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ProductsManager extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $name = '';
    public string $description = '';
    public ?string $image_path = null;
    public ?TemporaryUploadedFile $image = null;
    public bool $is_active = true;

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        unset($validated['image']);

        if ($this->image) {
            if ($this->image_path) {
                Storage::disk('public')->delete($this->image_path);
            }

            $this->image_path = $this->image->store('products', 'public');
            $this->image = null;
        }

        Product::updateOrCreate(
            ['id' => $this->editingId],
            [
                'branch_id' => auth()->user()?->branch_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'image_path' => $this->image_path,
                'is_active' => $validated['is_active'],
            ],
        );

        $this->resetForm();
        PerformanceCache::forgetLookups();
        session()->flash('status', 'Product saved.');
    }

    public function edit(int $id): void
    {
        $product = $this->productQuery()->findOrFail($id);

        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->image_path = $product->image_path;
        $this->is_active = (bool) $product->is_active;
    }

    public function toggleStatus(int $id): void
    {
        $product = $this->productQuery()->findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);
        PerformanceCache::forgetLookups();
    }

    public function delete(int $id): void
    {
        $product = $this->productQuery()->findOrFail($id);

        if (RateChart::where('product_id', $product->id)->exists()) {
            session()->flash('error', 'Product is used in the rate chart and cannot be deleted.');
            return;
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();
        $this->resetForm();
        PerformanceCache::forgetLookups();
        session()->flash('status', 'Product deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->image_path = null;
        $this->image = null;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $products = $this->productQuery()
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('name')
            ->limit(250)
            ->get();

        return view('livewire.products-manager', ['products' => $products])
            ->layout('layouts.app', ['title' => 'Products']);
    }

    private function productQuery(): Builder
    {
        return Product::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('branch_id');
            });
    }
}
