<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'recorded_by', 'school_class_id', 'academic_session_id', 'term_id',
        'attendance_date', 'enrolled_count', 'present_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
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
