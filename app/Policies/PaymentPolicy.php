<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.manage');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payments.manage') && $this->sameBranch($user, $payment->branch_id);
    }

    private function sameBranch(User $user, ?int $branchId): bool
    {
        return $user->branch_id === null || $branchId === null || $user->branch_id === $branchId;
    }
}
