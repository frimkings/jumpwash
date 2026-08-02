<?php

namespace App\Repositories;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function searchForBranch(?int $branchId, string $search = '', string $status = 'all', int $perPage = 50): LengthAwarePaginator
    {
        return Order::query()
            ->with(['customer', 'payments'])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            }))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function findForBranch(int $id, ?int $branchId): Order
    {
        return Order::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->findOrFail($id);
    }

    public function nextOrderNumber(?int $branchId): string
    {
        $prefix = 'JW-'.now()->format('Ymd').'-';
        $count = Order::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('order_number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
