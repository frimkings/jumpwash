<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\GarmentTag;
use App\Models\Order;
use App\Models\Product;
use App\Support\LaundryWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class GarmentTaggingManager extends Component
{
    public const WORKFLOW = [
        'received' => 'Received',
        'washing' => 'Washing',
        'drying' => 'Drying',
        'ironing' => 'Ironing',
        'packaging' => 'Packaging',
        'ready' => 'Ready',
        'delivered' => 'Delivered',
    ];

    public const EXCEPTION_STATUSES = [
        'missing' => 'Missing',
        'damaged' => 'Damaged',
        'rewash' => 'Needs Rewash',
    ];

    public string $order_id = '';
    public string $expected_garment_count = '0';
    public string $scan_code = '';
    public string $search = '';
    public string $statusFilter = 'all';
    public array $tagRows = [];
    public array $generatedTagIds = [];
    public bool $showGeneratedTagsModal = false;
    public ?int $lastScannedTagId = null;

    public function mount(): void
    {
        $this->addTagRow();

        if (request()->filled('order')) {
            $this->order_id = (string) request('order');
            $this->updatedOrderId();
        }
    }

    public function updatedOrderId(): void
    {
        $order = $this->selectedOrder();
        $expectedFromItems = $order ? (int) ceil((float) $order->items->sum('quantity')) : 0;
        $this->expected_garment_count = (string) ($order?->expected_garment_count ?: $expectedFromItems ?: 0);
        $this->lastScannedTagId = null;
        $this->prefillRowsFromOrder($order);
    }

    public function addTagRow(): void
    {
        $this->tagRows[] = [
            'order_item_id' => null,
            'garment_type' => '',
            'quantity' => 1,
            'color' => '',
            'brand' => '',
            'size' => '',
            'gender' => '',
            'condition' => '',
        ];
    }

    public function removeTagRow(int $index): void
    {
        unset($this->tagRows[$index]);
        $this->tagRows = array_values($this->tagRows);

        if (count($this->tagRows) === 0) {
            $this->addTagRow();
        }
    }

    public function generateTags(): void
    {
        $validated = $this->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'expected_garment_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'tagRows' => ['required', 'array', 'min:1'],
            'tagRows.*.order_item_id' => ['nullable', 'exists:order_items,id'],
            'tagRows.*.garment_type' => ['required', 'string', 'max:150'],
            'tagRows.*.quantity' => ['required', 'integer', 'min:1', 'max:250'],
            'tagRows.*.color' => ['nullable', 'string', 'max:80'],
            'tagRows.*.brand' => ['nullable', 'string', 'max:100'],
            'tagRows.*.size' => ['nullable', 'string', 'max:50'],
            'tagRows.*.gender' => ['nullable', 'string', 'max:50'],
            'tagRows.*.condition' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->orderQuery()->findOrFail($validated['order_id']);
        $rowQuantityTotal = collect($validated['tagRows'])->sum(fn (array $row): int => (int) $row['quantity']);
        $expectedCount = max((int) $validated['expected_garment_count'], $rowQuantityTotal);

        $createdTags = DB::transaction(function () use ($order, $validated, $expectedCount) {
            $order->update(['expected_garment_count' => $expectedCount]);
            $tags = collect();

            foreach ($validated['tagRows'] as $row) {
                for ($i = 0; $i < (int) $row['quantity']; $i++) {
                    $tagCode = $this->nextTagCode();

                    $tags->push(GarmentTag::create([
                        'order_id' => $order->id,
                        'order_item_id' => $row['order_item_id'] ?: null,
                        'tag_code' => $tagCode,
                        'garment_type' => $row['garment_type'],
                        'color' => $row['color'] ?? null,
                        'brand' => $row['brand'] ?? null,
                        'size' => $row['size'] ?? null,
                        'gender' => $row['gender'] ?? null,
                        'condition' => $row['condition'] ?? null,
                        'barcode_payload' => $tagCode,
                        'status' => 'received',
                        'is_scanned' => false,
                    ]));
                }
            }

            return $tags;
        });

        foreach ($createdTags as $tag) {
            ActivityLog::record('created', $tag, [
                'module' => 'garment_tags',
                'order_number' => $order->order_number,
            ], [], $this->tagAuditSnapshot($tag));
        }

        $this->expected_garment_count = (string) $expectedCount;
        $this->generatedTagIds = $createdTags->pluck('id')->all();
        $this->showGeneratedTagsModal = true;
        $this->tagRows = [];
        $this->addTagRow();
        session()->flash('status', $createdTags->count().' garment tag'.($createdTags->count() === 1 ? '' : 's').' generated.');
    }

    public function scanTag(): void
    {
        $validated = $this->validate([
            'scan_code' => ['required', 'string', 'max:80'],
        ]);

        $tag = GarmentTag::query()
            ->with('order.customer')
            ->where('tag_code', $validated['scan_code'])
            ->whereHas('order', fn (Builder $query) => $query->where('branch_id', auth()->user()?->branch_id))
            ->first();

        if (! $tag) {
            session()->flash('error', 'Tag not found.');
            return;
        }

        $oldValues = [
            'is_scanned' => $tag->is_scanned,
            'last_scanned_at' => $tag->last_scanned_at?->toDateTimeString(),
        ];

        $tag->update([
            'is_scanned' => true,
            'last_scanned_at' => now(),
        ]);

        ActivityLog::record('status.changed', $tag, [
            'module' => 'garment_tags',
            'scan_code' => $tag->tag_code,
        ], $oldValues, [
            'is_scanned' => true,
            'last_scanned_at' => $tag->fresh()->last_scanned_at?->toDateTimeString(),
        ]);

        $this->order_id = (string) $tag->order_id;
        $this->lastScannedTagId = $tag->id;
        $this->scan_code = '';
        session()->flash('status', 'Tag scanned.');
    }

    public function updateStatus(int $tagId, string $status): void
    {
        abort_unless($this->isKnownStatus($status), 422);

        $tag = GarmentTag::query()
            ->whereHas('order', fn (Builder $query) => $query->where('branch_id', auth()->user()?->branch_id))
            ->findOrFail($tagId);

        if (! $this->canMoveToStatus($tag->status, $status)) {
            session()->flash('error', 'Invalid status jump. Move garments through the workflow in order, or use an exception status.');
            return;
        }

        $oldStatus = $tag->status;

        $tag->update([
            'status' => $status,
            'is_scanned' => true,
            'last_scanned_at' => now(),
        ]);

        $this->recordStatusHistory($tag, $oldStatus, $status);

        ActivityLog::record('status.changed', $tag, [
            'module' => 'garment_tags',
            'tag_code' => $tag->tag_code,
        ], ['status' => $oldStatus], ['status' => $status]);
    }

    public function updateScannedStatus(string $status): void
    {
        if (! $this->lastScannedTagId) {
            session()->flash('error', 'Scan a tag first.');
            return;
        }

        $this->updateStatus($this->lastScannedTagId, $status);
    }

    public function reprintTag(int $tagId): void
    {
        $tag = GarmentTag::query()
            ->with('order')
            ->whereHas('order', fn (Builder $query) => $query->where('branch_id', auth()->user()?->branch_id))
            ->findOrFail($tagId);

        ActivityLog::record('tag.reprinted', $tag, [
            'module' => 'garment_tags',
            'tag_code' => $tag->tag_code,
            'order_number' => $tag->order?->order_number,
        ], [], $this->tagAuditSnapshot($tag));

        session()->flash('status', 'Tag reprint logged.');
    }

    public function closeGeneratedTagsModal(): void
    {
        $this->showGeneratedTagsModal = false;
    }

    public function closeOrder(): void
    {
        $order = $this->selectedOrder();

        if (! $order) {
            session()->flash('error', 'Select an order first.');
            return;
        }

        $expected = (int) $order->expected_garment_count;
        $scanned = $order->garmentTags()->where('is_scanned', true)->count();
        $missing = max(0, $expected - $scanned);

        if ($expected <= 0 || $missing > 0) {
            session()->flash('error', "Expected: {$expected} Items. Scanned: {$scanned} Items. Alert: {$missing} Missing Item".($missing === 1 ? '' : 's').'. Closure prevented.');
            return;
        }

        $order->update(['garment_closed_at' => now()]);
        LaundryWorkflow::syncOrderFromGarments($order);

        session()->flash('status', 'Garment workflow closed. No missing items detected.');
    }

    public function render()
    {
        $orders = $this->orderQuery()
            ->with('customer')
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('order_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->latest()
            ->limit(50)
            ->get();

        $selectedOrder = $this->selectedOrder();
        $tags = GarmentTag::query()
            ->with('order.customer')
            ->when($this->order_id !== '', fn (Builder $query) => $query->where('order_id', $this->order_id))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->whereHas('order', fn (Builder $query) => $query->where('branch_id', auth()->user()?->branch_id))
            ->latest()
            ->limit(250)
            ->get();

        $expected = (int) ($selectedOrder?->expected_garment_count ?? 0);
        $scanned = $selectedOrder ? $selectedOrder->garmentTags()->where('is_scanned', true)->count() : 0;
        $lastScannedTag = $this->lastScannedTagId
            ? GarmentTag::query()->with('order.customer')->find($this->lastScannedTagId)
            : null;
        $generatedTags = $this->generatedTagIds === []
            ? collect()
            : GarmentTag::query()->with('order.customer')->whereIn('id', $this->generatedTagIds)->orderBy('tag_code')->get();
        $recentActivity = ActivityLog::query()
            ->with('user')
            ->where('module', 'garment_tags')
            ->latest()
            ->limit(6)
            ->get();

        return view('livewire.garment-tagging-manager', [
            'orders' => $orders,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(250)->get(),
            'tags' => $tags,
            'generatedTags' => $generatedTags,
            'lastScannedTag' => $lastScannedTag,
            'recentActivity' => $recentActivity,
            'selectedOrder' => $selectedOrder,
            'workflow' => self::WORKFLOW,
            'exceptionStatuses' => self::EXCEPTION_STATUSES,
            'allStatuses' => self::WORKFLOW + self::EXCEPTION_STATUSES,
            'expected' => $expected,
            'scanned' => $scanned,
            'missing' => max(0, $expected - $scanned),
            'nextTagCode' => $this->nextTagCode(),
        ])->layout('layouts.app', ['title' => 'Garment Tagging']);
    }

    private function selectedOrder(): ?Order
    {
        if ($this->order_id === '') {
            return null;
        }

        return $this->orderQuery()->with(['customer', 'garmentTags', 'items.product', 'items.service'])->find($this->order_id);
    }

    private function orderQuery(): Builder
    {
        return Order::query()->where('branch_id', auth()->user()?->branch_id);
    }

    private function nextTagCode(): string
    {
        $prefix = 'TAG-'.now()->format('Ymd').'-';
        $count = GarmentTag::where('tag_code', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    private function prefillRowsFromOrder(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $rows = $order->items->map(function ($item): array {
            return [
                'order_item_id' => $item->id,
                'garment_type' => $item->product?->name ?: $item->item_name,
                'quantity' => max(1, (int) ceil((float) $item->quantity)),
                'color' => '',
                'brand' => '',
                'size' => '',
                'gender' => '',
                'condition' => '',
            ];
        })->values()->all();

        if ($rows !== []) {
            $this->tagRows = $rows;
        }
    }

    private function isKnownStatus(string $status): bool
    {
        return array_key_exists($status, self::WORKFLOW) || array_key_exists($status, self::EXCEPTION_STATUSES);
    }

    private function canMoveToStatus(?string $currentStatus, string $targetStatus): bool
    {
        if (array_key_exists($targetStatus, self::EXCEPTION_STATUSES) || $currentStatus === $targetStatus) {
            return true;
        }

        $workflow = array_keys(self::WORKFLOW);
        $currentIndex = array_search($currentStatus, $workflow, true);
        $targetIndex = array_search($targetStatus, $workflow, true);

        if ($currentIndex === false || $targetIndex === false) {
            return true;
        }

        return $targetIndex <= $currentIndex + 1;
    }

    private function recordStatusHistory(GarmentTag $tag, ?string $oldStatus, string $newStatus): void
    {
        if (! Schema::hasTable('garment_status_history')) {
            return;
        }

        DB::table('garment_status_history')->insert([
            'garment_tag_id' => $tag->id,
            'order_id' => $tag->order_id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tagAuditSnapshot(GarmentTag $tag): array
    {
        return [
            'tag_code' => $tag->tag_code,
            'order_id' => $tag->order_id,
            'garment_type' => $tag->garment_type,
            'color' => $tag->color,
            'brand' => $tag->brand,
            'size' => $tag->size,
            'gender' => $tag->gender,
            'condition' => $tag->condition,
            'status' => $tag->status,
            'is_scanned' => $tag->is_scanned,
        ];
    }
}
