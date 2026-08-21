<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentPortalEngagement extends Model
{
    protected $fillable = [
        'school_id',
        'parent_id',
        'academic_session_id',
        'term_id',
        'month_start_date',
        'login_count',
        'is_active_monthly',
        'last_login_at',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'month_start_date' => 'date',
        'last_login_at' => 'datetime',
        'is_active_monthly' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'parent_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function countsForKpi(): bool
    {
        return (bool) $this->is_active_monthly;
    }
}
