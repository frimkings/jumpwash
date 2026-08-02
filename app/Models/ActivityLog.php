<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public static function record(string $action, ?EloquentModel $subject = null, array $properties = [], array $oldValues = [], array $newValues = []): self
    {
        return self::create([
            'branch_id' => auth()->user()?->branch_id,
            'user_id' => auth()->id(),
            'module' => $properties['module'] ?? (str($action)->contains('.') ? str($action)->before('.')->toString() : 'system'),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
