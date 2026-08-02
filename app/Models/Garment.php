<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Garment extends Model
{
    use HasFactory, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'laundry_order_id',
        'garment_code',
        'barcode_value',
        'category',
        'name',
        'color',
        'fabric',
        'size',
        'condition_notes',
        'stain_notes',
        'status',
        'current_location',
        'received_at',
        'ready_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }
}
