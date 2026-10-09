<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeworkLog extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'status' => 'submitted',
    ];

    protected $fillable = [
        'teacher_id', 'school_class_id', 'subject_id',
        'academic_session_id', 'term_id', 'title',
        'given_date', 'due_date', 'given_count', 'completed_on_time_count', 'notes',
        'status', 'verified_by', 'verified_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'given_date' => 'date',
            'due_date' => 'date',
            'verified_at' => 'datetime',
        ];
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

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isEditableByTeacher(): bool
    {
        return ! $this->isVerified();
    }

    public function completionRate(): float
    {
        if ($this->given_count <= 0) {
            return 0.0;
        }

        return round($this->completed_on_time_count / $this->given_count, 4);
    }
}
