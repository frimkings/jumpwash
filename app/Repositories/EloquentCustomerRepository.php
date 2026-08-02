<?php

namespace App\Repositories;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    public function searchForBranch(?int $branchId, string $search = '', string $status = 'all', int $perPage = 50): LengthAwarePaginator
    {
        return Customer::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('customer_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($status !== 'all', fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->latest()
            ->paginate($perPage);
    }

    public function findForBranch(int $id, ?int $branchId): Customer
    {
        return Customer::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->findOrFail($id);
    }

    public function nextCustomerNumber(?int $branchId): string
    {
        $prefix = 'CUS-'.now()->format('Ymd').'-';
        $count = Customer::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('customer_code', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
