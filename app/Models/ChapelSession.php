<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChapelSession extends Model
{
    protected $fillable = [
        'school_id',
        'chapel_activity_type_id',
        'academic_session_id',
        'term_id',
        'session_date',
        'theme',
        'scripture_reference',
        'led_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ChapelActivityType::class, 'chapel_activity_type_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'led_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ChapelAttendance::class);
    }

    public function isHeld(): bool
    {
        return $this->status === 'held';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function rollTaken(): bool
    {
        return $this->attendances()->exists();
    }

    /**
     * Count of present + participating attendances that count toward CE-01.
     */
    public function countingAttendances(): int
    {
        $levels = $this->activityType?->countingLevels() ?? ['active', 'leading'];

        return $this->attendances()
            ->where('status', '!=', 'absent')
            ->whereIn('participation_level', $levels)
            ->count();
    }
}
