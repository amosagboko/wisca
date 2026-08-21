<?php

namespace App\Models;

use App\Support\AtRiskCriteria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtRiskLearner extends Model
{
    protected $fillable = [
        'learner_id', 'school_class_id', 'academic_session_id', 'term_id',
        'identified_by', 'identification_date', 'risk_factors', 'concern_note',
        'risk_level', 'status', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'identification_date' => 'date',
            'resolved_at' => 'datetime',
            'risk_factors' => 'array',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function identifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identified_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(InterventionPlan::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'active';
    }

    public function activePlan(): ?InterventionPlan
    {
        $this->loadMissing('plans');

        return $this->plans->first(
            fn (InterventionPlan $plan) => $plan->countsTowardKpi()
        );
    }

    public function hasActivePlan(): bool
    {
        return $this->activePlan() !== null;
    }

    /** @return array<int, string> */
    public function factorLabels(): array
    {
        return collect($this->risk_factors ?? [])
            ->map(fn ($key) => AtRiskCriteria::factorLabel((string) $key))
            ->values()
            ->all();
    }

    public function levelLabel(): string
    {
        return AtRiskCriteria::levels()[$this->risk_level] ?? ucfirst($this->risk_level);
    }
}
