<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TopicCoverageLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'topic_id', 'teacher_id', 'school_class_id', 'subject_id', 'lesson_plan_id',
        'coverage_date', 'workbook_reference', 'notes', 'status',
        'verified_by', 'verified_at', 'rejection_reason', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'coverage_date' => 'date',
            'verified_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function lessonPlan(): BelongsTo
    {
        return $this->belongsTo(LessonPlan::class);
    }
}
