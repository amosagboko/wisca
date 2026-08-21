<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StemProjectCompletion extends Model
{
    protected $fillable = [
        'school_id',
        'stem_project_type_id',
        'learner_id',
        'school_class_id',
        'academic_session_id',
        'term_id',
        'status',
        'completed_on',
        'score',
        'notes',
        'assessed_by',
    ];

    protected $casts = [
        'completed_on' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(StemProjectType::class, 'stem_project_type_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
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

    public function countsForKpi(): bool
    {
        return $this->status === 'completed';
    }
}
