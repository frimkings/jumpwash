<?php

namespace App\Livewire;

use App\Models\LaundryService;
use App\Models\Product;
use App\Models\RateChart;
use App\Support\PerformanceCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class RateChartManager extends Component
{
    public ?int $editingId = null;
    public string $product_id = '';
    public string $laundry_service_id = '';
    public string $price = '0';
    public string $search = '';

    public function save(): void
    {
        $validated = $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'laundry_service_id' => ['required', 'exists:laundry_services,id'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        $duplicate = RateChart::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('product_id', $validated['product_id'])
            ->where('laundry_service_id', $validated['laundry_service_id'])
            ->when($this->editingId, fn (Builder $query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('product_id', 'This product and service already has a rate.');
            return;
        }

        RateChart::updateOrCreate(
            ['id' => $this->editingId],
            [
                'branch_id' => auth()->user()?->branch_id,
                'product_id' => $validated['product_id'],
                'laundry_service_id' => $validated['laundry_service_id'],
                'price' => $validated['price'],
            ],
        );

        $this->resetForm();
        PerformanceCache::forgetLookups();
        session()->flash('status', 'Rate saved.');
    }

    public function edit(int $id): void
    {
        $rate = $this->rateQuery()->findOrFail($id);

        $this->editingId = $rate->id;
        $this->product_id = (string) $rate->product_id;
        $this->laundry_service_id = (string) $rate->laundry_service_id;
        $this->price = (string) $rate->price;
    }

    public function delete(int $id): void
    {
        $this->rateQuery()->findOrFail($id)->delete();
        $this->resetForm();
        PerformanceCache::forgetLookups();
        session()->flash('status', 'Rate deleted.');
    }

    public function selectMissingRate(int $productId, int $serviceId): void
    {
        $product = $this->productQuery()->findOrFail($productId);
        $service = $this->serviceQuery()->findOrFail($serviceId);
        $existingRate = $this->rateQuery()
            ->where('product_id', $product->id)
            ->where('laundry_service_id', $service->id)
            ->where('branch_id', auth()->user()?->branch_id)
            ->first();

        $this->editingId = $existingRate?->id;
        $this->product_id = (string) $product->id;
        $this->laundry_service_id = (string) $service->id;
        $this->price = $existingRate ? (string) $existingRate->price : '';
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->product_id = '';
        $this->laundry_service_id = '';
        $this->price = '0';
        $this->resetValidation();
    }

    public function render()
    {
        $products = Cache::remember(PerformanceCache::key('rate-chart-products'), PerformanceCache::LOOKUP_TTL, fn () => $this->productQuery()->get(['id', 'name']));

        $services = Cache::remember(PerformanceCache::key('rate-chart-services'), PerformanceCache::LOOKUP_TTL, fn () => $this->serviceQuery()->get(['id', 'name']));

        $rates = $this->rateQuery()
            ->with(['product', 'service'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->whereHas('product', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('service', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->join('products', 'products.id', '=', 'rate_charts.product_id')
            ->join('laundry_services', 'laundry_services.id', '=', 'rate_charts.laundry_service_id')
            ->orderBy('products.name')
            ->orderBy('laundry_services.name')
            ->select('rate_charts.*')
            ->limit(500)
            ->get();

        $missingRates = $this->missingRates($products, $services);
        $totalCombinations = $products->count() * $services->count();
        $completeCombinations = max($totalCombinations - $missingRates->count(), 0);

        return view('livewire.rate-chart-manager', [
            'products' => $products,
            'services' => $services,
            'rates' => $rates,
            'missingRates' => $missingRates,
            'totalCombinations' => $totalCombinations,
            'completeCombinations' => $completeCombinations,
            'coveragePercent' => $totalCombinations > 0 ? round(($completeCombinations / $totalCombinations) * 100) : 100,
        ])->layout('layouts.app', ['title' => 'Rate Chart']);
    }

    private function missingRates($products, $services)
    {
        $effectiveRates = RateChart::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('branch_id');
            })
            ->whereIn('product_id', $products->pluck('id'))
            ->whereIn('laundry_service_id', $services->pluck('id'))
            ->get()
            ->groupBy(fn (RateChart $rate): string => $rate->product_id.'|'.$rate->laundry_service_id)
            ->map(fn ($rates): RateChart => $rates
                ->sortByDesc(fn (RateChart $rate): int => $rate->branch_id === auth()->user()?->branch_id ? 1 : 0)
                ->first());

        return $products
            ->flatMap(fn (Product $product) => $services->map(function (LaundryService $service) use ($product, $effectiveRates): array {
                $rate = $effectiveRates->get($product->id.'|'.$service->id);

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'price' => $rate ? (float) $rate->price : null,
                    'reason' => ! $rate ? 'Missing rate' : 'Zero price',
                ];
            }))
            ->filter(fn (array $pair): bool => $pair['price'] === null || $pair['price'] <= 0)
            ->values();
    }

    private function productQuery(): Builder
    {
        return Product::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderBy('name');
    }

    private function serviceQuery(): Builder
    {
        return LaundryService::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderBy('name');
    }

    private function rateQuery(): Builder
    {
        return RateChart::query()
            ->where(function (Builder $query) {
                $query->where('rate_charts.branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('rate_charts.branch_id');
            });
    }
}
