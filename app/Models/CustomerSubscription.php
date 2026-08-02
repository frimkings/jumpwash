<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CustomerSubscription extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'subscription_plan_id',
        'subscription_no',
        'starts_at',
        'ends_at',
        'status',
        'auto_renew',
        'allowance',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'auto_renew' => 'boolean',
            'allowance' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function usageLimit(): int
    {
        $allowance = $this->allowance ?? [];

        return (int) ($allowance['limit'] ?? $this->plan?->usage_limit ?? $this->plan?->wash_limit ?? 0);
    }

    public function usedUses(): int
    {
        $allowance = $this->allowance ?? [];

        return (int) ($allowance['used'] ?? max($this->usageLimit() - $this->remainingUses(), 0));
    }

    public function remainingUses(): int
    {
        if (Schema::hasColumn('customer_subscriptions', 'washes_remaining')) {
            return (int) ($this->getAttribute('washes_remaining') ?? 0);
        }

        return (int) (($this->allowance ?? [])['remaining'] ?? $this->usageLimit());
    }

    public function isUsableForService(?int $serviceId = null): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->remainingUses() <= 0) {
            return false;
        }

        return ! $serviceId || (int) $this->plan?->laundry_service_id === (int) $serviceId;
    }

    public function consume(int $uses): void
    {
        $uses = max($uses, 0);
        $remaining = max($this->remainingUses() - $uses, 0);
        $used = $this->usedUses() + $uses;
        $updates = [
            'allowance' => [
                'limit' => $this->usageLimit(),
                'used' => $used,
                'remaining' => $remaining,
            ],
            'status' => $remaining <= 0 ? 'exhausted' : $this->status,
        ];

        if (Schema::hasColumn('customer_subscriptions', 'washes_remaining')) {
            $updates['washes_remaining'] = $remaining;
        }

        $this->update($updates);
    }

    public function renew(?string $startDate = null): void
    {
        $start = $startDate ? \Carbon\Carbon::parse($startDate) : now();
        $months = max((int) ($this->plan?->validity_months ?: ceil(((int) $this->plan?->validity_days) / 30)), 1);
        $limit = max((int) ($this->plan?->usage_limit ?: $this->plan?->wash_limit), 1);
        $updates = [
            'starts_at' => $start->toDateString(),
            'ends_at' => $start->copy()->addMonths($months)->toDateString(),
            'status' => 'active',
            'allowance' => [
                'limit' => $limit,
                'used' => 0,
                'remaining' => $limit,
            ],
        ];

        if (Schema::hasColumn('customer_subscriptions', 'washes_remaining')) {
            $updates['washes_remaining'] = $limit;
        }

        $this->update($updates);
    }
}
