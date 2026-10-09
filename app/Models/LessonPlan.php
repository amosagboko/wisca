<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LessonPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'topic_id', 'teacher_id', 'school_class_id', 'subject_id',
        'objectives', 'activities', 'assessment', 'resources', 'file_path',
        'status', 'submitted_at', 'due_at', 'on_time',
        'approved_by', 'approved_at', 'rejection_reason', 'review_checklist',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'due_at' => 'datetime',
            'approved_at' => 'datetime',
            'on_time' => 'boolean',
            'review_checklist' => 'array',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function reviewDeadlineAt(): ?Carbon
    {
        return $this->submitted_at?->copy()->addDay();
    }

    public function isReviewSlaOverdue(): bool
    {
        if ($this->status !== 'submitted' || ! $this->submitted_at) {
            return false;
        }

        return now()->gt($this->reviewDeadlineAt());
    }

    public function fileUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }
}
