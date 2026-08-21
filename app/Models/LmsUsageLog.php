<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsUsageLog extends Model
{
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'term_id',
        'week_start_date',
        'actor_type',
        'user_id',
        'learner_id',
        'login_count',
        'activity_count',
        'is_active_weekly',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'is_active_weekly' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
