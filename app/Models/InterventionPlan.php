<?php

namespace App\Models;

use App\Support\AtRiskCriteria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionPlan extends Model
{
    protected $fillable = [
        'at_risk_learner_id', 'coordinator_id', 'plan_type', 'objectives',
        'strategies', 'start_date', 'review_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'review_date' => 'date',
        ];
    }

    public function atRiskLearner(): BelongsTo
    {
        return $this->belongsTo(AtRiskLearner::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function countsTowardKpi(): bool
    {
        return $this->status === 'active' && AtRiskCriteria::isSupportPlan($this->plan_type);
    }

    public function typeLabel(): string
    {
        return AtRiskCriteria::planTypeLabel($this->plan_type);
    }

    public function statusLabel(): string
    {
        return AtRiskCriteria::planStatuses()[$this->status] ?? ucfirst($this->status);
    }
}
