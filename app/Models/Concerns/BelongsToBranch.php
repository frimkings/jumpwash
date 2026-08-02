<?php

namespace App\Models\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::creating(function ($model): void {
            if (! Schema::hasColumn($model->getTable(), 'branch_id')) {
                return;
            }

            if (blank($model->branch_id)) {
                $model->branch_id = app(BranchContext::class)->branchId();
            }
        });

        static::addGlobalScope('branch', function (Builder $builder): void {
            $branchId = app(BranchContext::class)->branchId();

            if ($branchId && Schema::hasColumn($builder->getModel()->getTable(), 'branch_id')) {
                $builder->where($builder->getModel()->getTable().'.branch_id', $branchId);
            }
        });
    }
}
