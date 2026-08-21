<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Learner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'school_class_id', 'name', 'admission_no', 'gender', 'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function examResults(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function atRiskRecords(): HasMany
    {
        return $this->hasMany(AtRiskLearner::class);
    }

    public function isEnrolled(): bool
    {
        return $this->status === 'enrolled';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = strtoupper(substr($parts[0] ?? 'L', 0, 1));
        $last = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : strtoupper(substr($parts[0] ?? 'L', 1, 1));

        return $first.($last ?: $first);
    }
}
