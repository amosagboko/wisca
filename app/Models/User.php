<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'passport_path', 'password', 'school_id', 'department_id', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher')
            ->withPivot('school_class_id', 'academic_session_id', 'status')
            ->withTimestamps();
    }

    public function coverageLogs(): HasMany
    {
        return $this->hasMany(TopicCoverageLog::class, 'teacher_id');
    }

    public function homeworkLogs(): HasMany
    {
        return $this->hasMany(HomeworkLog::class, 'teacher_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'teacher_id');
    }

    public function isBoard(): bool
    {
        return $this->hasRole('board');
    }

    public function isHoS(): bool
    {
        return $this->hasRole('head_of_school');
    }

    public function isLeadership(): bool
    {
        return $this->isHoS() || $this->isAssistantHead();
    }

    public function isHoD(): bool
    {
        return $this->hasRole('head_of_department');
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canManageAcademicPeriod(): bool
    {
        return $this->isAdmin() || $this->isHoS() || $this->isBoard() || $this->isAssistantHead();
    }

    public function canActivateAcademicPeriod(): bool
    {
        return $this->canManageAcademicPeriod();
    }

    public function isAdminOfficer(): bool
    {
        return $this->hasRole('admin_officer');
    }

    public function canManageAttendance(): bool
    {
        return $this->isAdmin() || $this->isHoS() || $this->isAdminOfficer();
    }

    public function canRecordAttendance(): bool
    {
        return $this->canManageAttendance() || $this->isTeacher();
    }

    public function canViewAttendance(): bool
    {
        return $this->canRecordAttendance() || $this->isHoD() || $this->isAssistantHead();
    }

    public function canConductObservation(): bool
    {
        return $this->isHoD() || $this->isLeadership() || $this->isAdmin();
    }

    public function canViewObservations(): bool
    {
        return $this->canConductObservation() || $this->isTeacher();
    }

    public function canManageLearners(): bool
    {
        return $this->isAdmin() || $this->isHoS() || $this->isAdminOfficer();
    }

    public function canViewLearners(): bool
    {
        return $this->canManageLearners()
            || $this->isHoD()
            || $this->isTeacher()
            || $this->isLearningSupport()
            || $this->isLiteracyCoordinator()
            || $this->isAssistantHead();
    }

    public function canEnterExamResults(): bool
    {
        return $this->isTeacher() || $this->isHoD() || $this->isLeadership() || $this->isAdmin();
    }

    public function canViewExamResults(): bool
    {
        return $this->canEnterExamResults() || $this->isLearningSupport();
    }

    public function isLearningSupport(): bool
    {
        return $this->hasRole('learning_support_coordinator');
    }

    public function canIdentifyAtRisk(): bool
    {
        return $this->isLearningSupport() || $this->isHoD() || $this->isLeadership() || $this->isAdmin() || $this->isTeacher();
    }

    public function canManageInterventionPlans(): bool
    {
        return $this->isLearningSupport() || $this->isHoD() || $this->isLeadership() || $this->isAdmin();
    }

    public function canViewAtRisk(): bool
    {
        return $this->canIdentifyAtRisk();
    }

    public function isLiteracyCoordinator(): bool
    {
        return $this->hasRole('literacy_coordinator');
    }

    public function isStudentLifeCoordinator(): bool
    {
        return $this->hasRole('student_life_coordinator');
    }

    public function isParentRelationsLead(): bool
    {
        return $this->hasRole('parent_relations_lead');
    }

    public function isAssistantHead(): bool
    {
        return $this->hasRole('assistant_head_secondary');
    }

    public function isChaplain(): bool
    {
        return $this->hasRole('chaplain');
    }

    public function isSubjectLead(): bool
    {
        return $this->hasRole('subject_lead');
    }

    public function isIctCoordinator(): bool
    {
        return $this->hasRole(['ict_coordinator', 'it_consultant']);
    }

    public function isItConsultant(): bool
    {
        return $this->isIctCoordinator();
    }

    public function isAdminManager(): bool
    {
        return $this->hasRole('admin_manager');
    }

    public function isStemCoordinator(): bool
    {
        return $this->hasRole('stem_coordinator');
    }

    public function canRecordReading(): bool
    {
        return $this->isLiteracyCoordinator() || $this->isTeacher() || $this->isHoD() || $this->isLeadership() || $this->isAdmin();
    }

    public function canViewReading(): bool
    {
        return $this->canRecordReading();
    }

    public function passportUrl(): ?string
    {
        if (! $this->passport_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->passport_path);
    }

    public function storePassport(UploadedFile $file): void
    {
        if ($this->passport_path) {
            Storage::disk('public')->delete($this->passport_path);
        }

        $this->forceFill([
            'passport_path' => $file->store('passports', 'public'),
        ])->save();
    }

    public function deletePassportFile(): void
    {
        if ($this->passport_path) {
            Storage::disk('public')->delete($this->passport_path);
            $this->forceFill(['passport_path' => null])->save();
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (self $user) {
            if ($user->passport_path) {
                Storage::disk('public')->delete($user->passport_path);
            }
        });
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
        $last = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : strtoupper(substr($parts[0] ?? 'U', 1, 1));

        return $first.($last ?: $first);
    }

    public function avatarClass(): string
    {
        $colors = [
            'bg-sky-700', 'bg-teal-700', 'bg-indigo-700', 'bg-violet-700',
            'bg-rose-700', 'bg-amber-700', 'bg-emerald-800', 'bg-cyan-800',
        ];

        return $colors[$this->id % count($colors)];
    }
}
