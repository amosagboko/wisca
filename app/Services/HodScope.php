<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\TopicCoverageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HodScope
{
    /**
     * Subject IDs this user may review. Null means unrestricted (school-wide read).
     *
     * @return list<int>|null
     */
    public function subjectIds(User $user): ?array
    {
        if ($this->isUnrestricted($user)) {
            return null;
        }

        $classIds = $this->classIds($user);
        if ($classIds === null) {
            return null;
        }

        if ($classIds === []) {
            return [];
        }

        return DB::table('class_subjects')
            ->whereIn('school_class_id', $classIds)
            ->pluck('subject_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Class IDs in sections this HOD leads. Null means unrestricted. Empty means no section assignment.
     *
     * @return list<int>|null
     */
    public function classIds(User $user): ?array
    {
        if ($this->isUnrestricted($user)) {
            return null;
        }

        if (! $user->isHoD()) {
            return [];
        }

        return SchoolClass::query()
            ->where('school_id', $user->school_id)
            ->whereHas('schoolSection', fn ($query) => $query->where('hod_id', $user->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function isUnrestricted(User $user): bool
    {
        return $user->isHoS() || $user->isAdmin() || $user->isBoard() || $user->isAssistantHead();
    }

    public function canReviewSubject(User $user, int $subjectId): bool
    {
        $ids = $this->subjectIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($subjectId, $ids, true);
    }

    public function canReviewClass(User $user, int $classId): bool
    {
        $ids = $this->classIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($classId, $ids, true);
    }

    public function constrainBySubject(Builder $query, User $user, string $column = 'subject_id'): Builder
    {
        $ids = $this->subjectIds($user);
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    public function constrainByClass(Builder $query, User $user, string $column = 'school_class_id'): Builder
    {
        $ids = $this->classIds($user);
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    public function canReviewLessonPlan(User $user, LessonPlan $plan): bool
    {
        $plan->loadMissing('schoolClass');

        if ((int) $plan->schoolClass?->school_id !== (int) $user->school_id) {
            return false;
        }

        if (! $user->isHoD()) {
            return false;
        }

        return $this->canReviewClass($user, (int) $plan->school_class_id);
    }

    public function canReviewCoverage(User $user, TopicCoverageLog $log): bool
    {
        $log->loadMissing('schoolClass');

        if ((int) $log->schoolClass?->school_id !== (int) $user->school_id) {
            return false;
        }

        return $user->isHoD() && $this->canReviewClass($user, (int) $log->school_class_id);
    }

    public function canReviewHomework(User $user, HomeworkLog $log): bool
    {
        $log->loadMissing('schoolClass');

        if ((int) $log->schoolClass?->school_id !== (int) $user->school_id) {
            return false;
        }

        return $user->isHoD() && $this->canReviewClass($user, (int) $log->school_class_id);
    }

    public function canReviewAttendance(User $user, AttendanceLog $log): bool
    {
        $log->loadMissing('schoolClass');

        if ((int) $log->schoolClass?->school_id !== (int) $user->school_id) {
            return false;
        }

        return $user->isHoD() && $this->canReviewClass($user, (int) $log->school_class_id);
    }

    public function canReviewExamSitting(User $user, int $classId, int $subjectId): bool
    {
        return $user->isHoD()
            && $this->canReviewClass($user, $classId)
            && $this->canReviewSubject($user, $subjectId);
    }

    public function canReviewSchemeClass(User $user, int $classId): bool
    {
        if (! $user->isHoD()) {
            return false;
        }

        return $this->canReviewClass($user, $classId);
    }

    public function assertCanReviewLessonPlan(User $user, LessonPlan $plan): void
    {
        abort_unless($this->canReviewLessonPlan($user, $plan), 403, 'You can only review lesson plans for classes in your school section.');
    }

    public function assertCanReviewCoverage(User $user, TopicCoverageLog $log): void
    {
        abort_unless($this->canReviewCoverage($user, $log), 403, 'You can only verify coverage for classes in your school section.');
    }

    public function assertCanReviewSchemeClass(User $user, int $classId): void
    {
        abort_unless($this->canReviewSchemeClass($user, $classId), 403, 'You can only review schemes of work for classes in your school section.');
    }
}
