<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'user_id',
        'staff_code',
        'employee_code',
        'title',
        'position',
        'shift',
        'phone',
        'hourly_rate',
        'status',
        'emergency_contact',
        'vehicle',
        'license_number',
        'availability',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'permissions' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
