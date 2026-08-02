<?php

namespace App\Contracts\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function searchForBranch(?int $branchId, string $search = '', string $status = 'all', int $perPage = 50): LengthAwarePaginator;

    public function findForBranch(int $id, ?int $branchId): Order;

    public function nextOrderNumber(?int $branchId): string;
}
