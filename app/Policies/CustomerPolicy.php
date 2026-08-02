<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage') && $this->sameBranch($user, $customer->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage') && $this->sameBranch($user, $customer->branch_id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage') && $this->sameBranch($user, $customer->branch_id);
    }

    private function sameBranch(User $user, ?int $branchId): bool
    {
        return $user->branch_id === null || $branchId === null || $user->branch_id === $branchId;
    }
}
