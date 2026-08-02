<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'laundry_service_id',
        'billing_cycle',
        'price',
        'validity_months',
        'usage_limit',
        'pickup_included',
        'amount',
        'wash_limit',
        'validity_days',
        'pickup_limit',
        'discount_percent',
        'turnaround_hours',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'features' => 'array',
            'pickup_included' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(LaundryService::class, 'laundry_service_id');
    }
}
