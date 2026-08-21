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

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
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
