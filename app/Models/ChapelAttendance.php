<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapelAttendance extends Model
{
    protected $fillable = [
        'chapel_session_id',
        'learner_id',
        'status',
        'participation_level',
        'notes',
        'recorded_by',
    ];

    public function chapelSession(): BelongsTo
    {
        return $this->belongsTo(ChapelSession::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isPresent(): bool
    {
        return $this->status !== 'absent';
    }

    public function countsForKpi(): bool
    {
        $levels = $this->chapelSession?->activityType?->countingLevels() ?? ['active', 'leading'];

        return $this->isPresent() && in_array($this->participation_level, $levels, true);
    }
}
