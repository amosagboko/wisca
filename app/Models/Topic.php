<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Topic extends Model
{
    protected $fillable = [
        'scheme_of_work_id', 'week_number', 'title', 'description',
        'learning_objectives', 'expected_duration_minutes', 'display_order', 'status',
    ];

    protected function casts(): array
    {
        return ['learning_objectives' => 'array'];
    }

    public function schemeOfWork(): BelongsTo
    {
        return $this->belongsTo(SchemeOfWork::class);
    }

    public function coverageLogs(): HasMany
    {
        return $this->hasMany(TopicCoverageLog::class);
    }

    public function lessonPlans(): HasMany
    {
        return $this->hasMany(LessonPlan::class);
    }

    public function latestLessonPlan(): HasOne
    {
        return $this->hasOne(LessonPlan::class)->latestOfMany();
    }

    public function latestCoverageLog(): HasOne
    {
        return $this->hasOne(TopicCoverageLog::class)->latestOfMany();
    }

    public function catchUpPlan(): HasOne
    {
        return $this->hasOne(TopicCatchUpPlan::class);
    }

    public function hasApprovedLessonPlan(): bool
    {
        if ($this->relationLoaded('lessonPlans')) {
            return $this->lessonPlans->contains(fn (LessonPlan $plan) => $plan->status === 'approved');
        }

        if ($this->relationLoaded('latestLessonPlan') && $this->latestLessonPlan?->status === 'approved') {
            return true;
        }

        return $this->lessonPlans()->where('status', 'approved')->exists();
    }

    public function approvedLessonPlan(): ?LessonPlan
    {
        if ($this->relationLoaded('lessonPlans')) {
            return $this->lessonPlans->where('status', 'approved')->sortByDesc('id')->first();
        }

        return $this->lessonPlans()->where('status', 'approved')->latest('id')->first();
    }

    public function isLoggable(): bool
    {
        $log = $this->relationLoaded('latestCoverageLog')
            ? $this->latestCoverageLog
            : $this->coverageLogs()->latest('id')->first();

        if (in_array($this->status, ['covered', 'skipped'], true)) {
            return false;
        }

        return ! $log || in_array($log->status, ['draft', 'rejected'], true);
    }

    public function approvedLearningObjectives(): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            is_array($this->learning_objectives) ? $this->learning_objectives : [],
        )));
    }
}
