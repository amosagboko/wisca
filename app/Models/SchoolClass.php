<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use SoftDeletes;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id', 'name', 'level', 'display_order', 'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (SchoolClass $class) {
            if ((int) $class->display_order > 0) {
                return;
            }

            $class->display_order = static::nextDisplayOrderForSchool((int) $class->school_id);
        });
    }

    public static function nextDisplayOrderForSchool(int $schoolId): int
    {
        return (int) static::query()->where('school_id', $schoolId)->max('display_order') + 1;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function offeredSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects')->withTimestamps();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher')
            ->withPivot('teacher_id', 'academic_session_id', 'status')
            ->withTimestamps();
    }

    public function learners(): HasMany
    {
        return $this->hasMany(Learner::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(\App\Models\TeacherAssignment::class);
    }
}
