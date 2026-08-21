<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalCompetencyRating extends Model
{
    protected $fillable = [
        'school_id',
        'digital_competency_area_id',
        'user_id',
        'academic_session_id',
        'term_id',
        'level',
        'assessed_on',
        'notes',
        'assessed_by',
    ];

    protected $casts = [
        'level' => 'integer',
        'assessed_on' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(DigitalCompetencyArea::class, 'digital_competency_area_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function isPassing(): bool
    {
        $passing = $this->area?->passing_level ?? 3;

        return $this->level >= $passing;
    }
}
