<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicCatchUpPlan extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ADDRESSED = 'addressed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'topic_id', 'opened_by', 'opened_at', 'notes', 'target_week_number',
        'status', 'addressed_at', 'addressed_by', 'coverage_log_id',
        'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'addressed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function addressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressed_by');
    }

    public function coverageLog(): BelongsTo
    {
        return $this->belongsTo(TopicCoverageLog::class, 'coverage_log_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isAddressed(): bool
    {
        return $this->status === self::STATUS_ADDRESSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function scopeIdentified(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ADDRESSED]);
    }
}
