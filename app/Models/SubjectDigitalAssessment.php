<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectDigitalAssessment extends Model
{
    protected $fillable = [
        'school_id',
        'subject_id',
        'academic_session_id',
        'term_id',
        'uses_e_assessment',
        'uses_e_portfolio',
        'primary_tool',
        'evidence_notes',
        'verified_on',
        'verified_by',
    ];

    protected $casts = [
        'uses_e_assessment' => 'boolean',
        'uses_e_portfolio' => 'boolean',
        'verified_on' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function countsForKpi(): bool
    {
        return $this->uses_e_assessment || $this->uses_e_portfolio;
    }
}
