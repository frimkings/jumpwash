<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;

class BranchContext
{
    public function branchId(): ?int
    {
        return session('active_branch_id')
            ?? auth()->user()?->branch_id
            ?? Branch::query()->where('is_primary', true)->value('id');
    }

    public function branch(): ?Branch
    {
        $branchId = $this->branchId();

        return $branchId ? Branch::query()->find($branchId) : null;
    }

    public function syncFromUser(?User $user): void
    {
        if (! $user) {
            return;
        }

        session(['active_branch_id' => $user->branch_id]);
    }

    public function setBranchId(?int $branchId): void
    {
        session(['active_branch_id' => $branchId]);
    }
}