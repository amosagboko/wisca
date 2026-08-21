<?php

namespace App\Models;

use App\Support\ExamPassMark;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    protected $fillable = [
        'learner_id', 'subject_id', 'school_class_id', 'academic_session_id',
        'term_id', 'recorded_by', 'assessment_key', 'assessment_name', 'score',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function passed(?float $passMark = null): bool
    {
        return $this->score >= ($passMark ?? ExamPassMark::percent());
    }
}
