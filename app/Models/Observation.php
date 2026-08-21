<?php

namespace App\Models;

use App\Support\ObservationRubric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Observation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'teacher_id', 'observer_id', 'school_class_id', 'subject_id',
        'academic_session_id', 'term_id', 'observation_date',
        'rubric_scores', 'overall_score', 'strengths',
        'areas_for_improvement', 'action_plan', 'status',
    ];

    protected function casts(): array
    {
        return [
            'observation_date' => 'date',
            'rubric_scores' => 'array',
            'overall_score' => 'float',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function observer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'observer_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
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

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'follow_up_required'], true);
    }

    public function isEffective(): bool
    {
        return ObservationRubric::isEffective($this->overall_score);
    }

    public function scoreLabel(): string
    {
        if ($this->overall_score === null) {
            return 'Not scored';
        }

        return number_format($this->overall_score, 2).' · '.ObservationRubric::scaleLabel($this->overall_score);
    }
}
