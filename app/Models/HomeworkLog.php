<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeworkLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'teacher_id', 'school_class_id', 'subject_id',
        'academic_session_id', 'term_id', 'title',
        'given_date', 'due_date', 'given_count', 'completed_on_time_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'given_date' => 'date',
            'due_date' => 'date',
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

    public function completionRate(): float
    {
        if ($this->given_count <= 0) {
            return 0.0;
        }

        return round($this->completed_on_time_count / $this->given_count, 4);
    }
}
