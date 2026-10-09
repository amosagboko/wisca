<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $attributes = [
        'status' => 'submitted',
    ];

    protected $fillable = [
        'recorded_by', 'school_class_id', 'academic_session_id', 'term_id',
        'attendance_date', 'enrolled_count', 'present_count', 'notes',
        'status', 'verified_by', 'verified_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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

    public function attendanceRate(): float
    {
        if ($this->enrolled_count <= 0) {
            return 0.0;
        }

        return round($this->present_count / $this->enrolled_count, 4);
    }

    public function absentCount(): int
    {
        return max(0, $this->enrolled_count - $this->present_count);
    }
}
