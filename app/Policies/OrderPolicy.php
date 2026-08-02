<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.manage') || $user->can('orders.assigned.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user) && $this->sameBranch($user, $order->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('orders.manage');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.manage') && $this->sameBranch($user, $order->branch_id);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('orders.manage') && $this->sameBranch($user, $order->branch_id);
    }

    private function sameBranch(User $user, ?int $branchId): bool
    {
        return $user->branch_id === null || $branchId === null || $user->branch_id === $branchId;
    }
}
