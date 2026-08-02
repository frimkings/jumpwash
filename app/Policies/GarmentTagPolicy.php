<?php

namespace App\Policies;

use App\Models\GarmentTag;
use App\Models\User;

class GarmentTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('garments.scan');
    }

    public function update(User $user, GarmentTag $tag): bool
    {
        return $user->can('garments.scan') && $this->sameBranch($user, $tag);
    }

    public function reprint(User $user, GarmentTag $tag): bool
    {
        return $user->can('garments.scan') && $this->sameBranch($user, $tag);
    }

    private function sameBranch(User $user, GarmentTag $tag): bool
    {
        return $user->branch_id === null || $tag->order?->branch_id === $user->branch_id;
    }
}
