<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'code',
        'customer_code',
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'alt_phone',
        'address',
        'city',
        'gps_location',
        'loyalty_points',
        'default_pickup_address',
        'notes',
        'photo_path',
        'preferred_contact_channel',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'loyalty_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function history(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
