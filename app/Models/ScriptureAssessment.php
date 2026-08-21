<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptureAssessment extends Model
{
    protected $fillable = [
        'school_id',
        'scripture_passage_id',
        'learner_id',
        'school_class_id',
        'academic_session_id',
        'term_id',
        'assessed_on',
        'recites_correctly',
        'explains_contextually',
        'notes',
        'assessed_by',
    ];

    protected function casts(): array
    {
        return [
            'assessed_on' => 'date',
            'recites_correctly' => 'boolean',
            'explains_contextually' => 'boolean',
        ];
    }

    public function passage(): BelongsTo
    {
        return $this->belongsTo(ScripturePassage::class, 'scripture_passage_id');
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
        return $this->recites_correctly && $this->explains_contextually;
    }
}
