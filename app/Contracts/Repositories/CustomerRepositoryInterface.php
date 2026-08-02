<?php

namespace App\Contracts\Repositories;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function searchForBranch(?int $branchId, string $search = '', string $status = 'all', int $perPage = 50): LengthAwarePaginator;

    public function findForBranch(int $id, ?int $branchId): Customer;

    public function nextCustomerNumber(?int $branchId): string;
}
