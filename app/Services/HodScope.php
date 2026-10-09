<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\LessonPlan;
use App\Models\Subject;
use App\Models\TopicCoverageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HodScope
{
    /**
     * Subject IDs this user may review. Null means unrestricted (school-wide).
     *
     * @return list<int>|null
     */
    public function subjectIds(User $user): ?array
    {
        if ($this->isUnrestricted($user)) {
            return null;
        }

        if (! $user->isHoD() || ! $user->department_id) {
            return null;
        }

        return Subject::query()
            ->where('school_id', $user->school_id)
            ->where('department_id', $user->department_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Class IDs that offer at least one subject in the HOD's department. Null means unrestricted.
     *
     * @return list<int>|null
     */
    public function classIds(User $user): ?array
    {
        $subjectIds = $this->subjectIds($user);
        if ($subjectIds === null) {
            return null;
        }

        if ($subjectIds === []) {
            return [];
        }

        return DB::table('class_subjects')
            ->whereIn('subject_id', $subjectIds)
            ->pluck('school_class_id')
            ->unique()
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

        if ($user->isHoS()) {
            return true;
        }

        if (! $user->isHoD()) {
            return false;
        }

        return $this->canReviewSubject($user, (int) $plan->subject_id);
    }

    public function canReviewCoverage(User $user, TopicCoverageLog $log): bool
    {
        $log->loadMissing('schoolClass');

        if ((int) $log->schoolClass?->school_id !== (int) $user->school_id) {
            return false;
        }

        return $user->isHoD() && $this->canReviewSubject($user, (int) $log->subject_id);
    }

    public function canReviewHomework(User $user, HomeworkLog $log): bool
    {
        $log->loadMissing('schoolClass');

        if ((int) $log->schoolClass?->school_id !== (int) $user->school_id) {
            return false;
        }

        return $user->isHoD() && $this->canReviewSubject($user, (int) $log->subject_id);
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

    public function assertCanReviewLessonPlan(User $user, LessonPlan $plan): void
    {
        abort_unless($this->canReviewLessonPlan($user, $plan), 403, 'You can only review lesson plans in your school and department.');
    }

    public function assertCanReviewCoverage(User $user, TopicCoverageLog $log): void
    {
        abort_unless($this->canReviewCoverage($user, $log), 403, 'You can only verify coverage in your department.');
    }
}
