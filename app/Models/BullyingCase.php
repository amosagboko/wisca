<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BullyingCase extends Model
{
    protected $fillable = [
        'school_id',
        'bullying_case_type_id',
        'academic_session_id',
        'term_id',
        'target_learner_id',
        'reported_by_learner_id',
        'reported_on',
        'title',
        'description',
        'severity',
        'status',
        'safety_plan_created',
        'safety_plan',
        'safety_plan_created_at',
        'closed_at',
        'reported_by',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'reported_on' => 'date',
            'safety_plan_created' => 'boolean',
            'safety_plan_created_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BullyingCaseType::class, 'bullying_case_type_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function targetLearner(): BelongsTo
    {
        return $this->belongsTo(Learner::class, 'target_learner_id');
    }

    public function reportingLearner(): BelongsTo
    {
        return $this->belongsTo(Learner::class, 'reported_by_learner_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function countsForKpi(): bool
    {
        return $this->status === 'closed'
            && $this->safety_plan_created
            && $this->safety_plan_created_at !== null;
    }
}
