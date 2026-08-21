<?php

namespace App\Models;

use App\Support\ReadingGrowthThreshold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingAssessment extends Model
{
    protected $fillable = [
        'learner_id', 'school_class_id', 'academic_session_id', 'term_id',
        'recorded_by', 'baseline_level', 'followup_level',
        'baseline_checkpoint', 'followup_checkpoint',
        'baseline_date', 'followup_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'baseline_level'  => 'float',
            'followup_level'  => 'float',
            'baseline_date'   => 'date',
            'followup_date'   => 'date',
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

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isComplete(): bool
    {
        return $this->baseline_level !== null && $this->followup_level !== null;
    }

    public function growth(): ?float
    {
        if (! $this->isComplete()) {
            return null;
        }

        return ReadingGrowthThreshold::growth($this->baseline_level, $this->followup_level);
    }

    public function hasAchievedGrowth(?Kpi $kpi = null): bool
    {
        if (! $this->isComplete()) {
            return false;
        }

        return ReadingGrowthThreshold::achieved($this->baseline_level, $this->followup_level, $kpi);
    }
}
