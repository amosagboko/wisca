<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\SchemeOfWork;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\TopicCoverageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadershipAccountability
{
    public const PAGE_SIZE = 3;

    public function __construct(
        protected HomeworkCalculationService $homework,
        protected AttendanceCalculationService $attendance,
        protected TeacherTaskFeed $tasks,
    ) {}

    /**
     * @return array{
     *     hods: Collection<int, array<string, mixed>>,
     *     teachers: Collection<int, array<string, mixed>>,
     *     week_number: int
     * }
     */
    public function compose(User $leader, AcademicSession $session, Term $term): array
    {
        $schoolId = (int) $leader->school_id;
        $window = $this->homework->weekWindow($session, $term);
        $weekNumber = (int) $window['week_number'];

        $hodRows = $this->hodRows($schoolId, $session, $term, $window)
            ->sortBy([
                ['group', 'asc'],
                ['urgency', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $teacherRows = $this->teacherRows($schoolId, $session, $term, $weekNumber)
            ->sortBy([
                ['urgency', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        return [
            'hods' => $hodRows,
            'teachers' => $teacherRows,
            'week_number' => $weekNumber,
        ];
    }

    /**
     * @param  array{start: \Carbon\Carbon, end: \Carbon\Carbon}  $window
     * @return Collection<int, array<string, mixed>>
     */
    protected function hodRows(int $schoolId, AcademicSession $session, Term $term, array $window): Collection
    {
        $sections = SchoolSection::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('hod_id')
            ->with('hod')
            ->get();

        if ($sections->isEmpty()) {
            return collect();
        }

        $classes = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->get(['id', 'name', 'school_section_id']);

        $hodClassIds = [];
        $hodSectionNames = [];
        foreach ($sections as $section) {
            $hodId = (int) $section->hod_id;
            $ids = $classes
                ->where('school_section_id', $section->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $hodClassIds[$hodId] = array_values(array_unique(array_merge($hodClassIds[$hodId] ?? [], $ids)));
            $hodSectionNames[$hodId] = array_values(array_unique(array_merge($hodSectionNames[$hodId] ?? [], [$section->name])));
        }

        $allClassIds = $classes->pluck('id')->map(fn ($id) => (int) $id)->all() ?: [0];

        $schemes = SchemeOfWork::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', 'draft')
            ->whereNotNull('submitted_at')
            ->whereIn('school_class_id', $allClassIds)
            ->get(['id', 'school_class_id', 'submitted_at']);

        $plans = LessonPlan::query()
            ->where('status', 'submitted')
            ->whereHas('topic.schemeOfWork', fn ($q) => $q
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id))
            ->whereIn('school_class_id', $allClassIds)
            ->get(['id', 'school_class_id', 'submitted_at']);

        $coverage = TopicCoverageLog::query()
            ->where('status', 'submitted')
            ->whereHas('topic.schemeOfWork', fn ($q) => $q
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id))
            ->whereIn('school_class_id', $allClassIds)
            ->get(['id', 'school_class_id']);

        $homework = HomeworkLog::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', CaptureLogReview::STATUS_SUBMITTED)
            ->whereBetween('given_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->whereIn('school_class_id', $allClassIds)
            ->get(['id', 'school_class_id']);

        $registers = AttendanceLog::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', CaptureLogReview::STATUS_SUBMITTED)
            ->whereBetween('attendance_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->whereIn('school_class_id', $allClassIds)
            ->get(['id', 'school_class_id']);

        return $sections->unique('hod_id')->map(function (SchoolSection $section) use ($hodClassIds, $hodSectionNames, $schemes, $plans, $coverage, $homework, $registers) {
            $hod = $section->hod;
            if (! $hod) {
                return null;
            }

            $classIds = $hodClassIds[(int) $hod->id] ?? [];
            $sectionLabel = implode(', ', $hodSectionNames[(int) $hod->id] ?? [$section->name]);
            $inSection = fn ($row) => in_array((int) $row->school_class_id, $classIds, true);

            $schemeCount = $schemes->filter($inSection)->count();
            $planCount = $plans->filter($inSection)->count();
            $overduePlans = $plans->filter(function ($plan) use ($inSection) {
                return $inSection($plan)
                    && $plan->submitted_at
                    && now()->gt($plan->submitted_at->copy()->addHours(HodApprovalQueue::SLA_HOURS));
            })->count();
            $coverageCount = $coverage->filter($inSection)->count();
            $homeworkCount = $homework->filter($inSection)->count();
            $registerCount = $registers->filter($inSection)->count();
            $total = $schemeCount + $planCount + $coverageCount + $homeworkCount + $registerCount;

            if ($total === 0) {
                return null;
            }

            $parts = array_values(array_filter([
                $schemeCount > 0 ? $schemeCount.' scheme'.($schemeCount === 1 ? '' : 's') : null,
                $planCount > 0 ? $planCount.' lesson plan'.($planCount === 1 ? '' : 's') : null,
                $coverageCount > 0 ? $coverageCount.' coverage log'.($coverageCount === 1 ? '' : 's') : null,
                $homeworkCount > 0 ? $homeworkCount.' homework log'.($homeworkCount === 1 ? '' : 's') : null,
                $registerCount > 0 ? $registerCount.' register'.($registerCount === 1 ? '' : 's') : null,
            ]));

            [$href, $cta] = $this->hodCta($schemeCount, $planCount, $coverageCount, $homeworkCount, $registerCount);

            return $this->row(
                'hod-'.$hod->id,
                $hod->name,
                'HOD',
                $sectionLabel,
                implode(', ', $parts).' awaiting review'
                    .($overduePlans > 0 ? ' · '.$overduePlans.' past 24-hour SLA' : ''),
                $hod->name,
                $href,
                $cta,
                $overduePlans > 0 ? 5 : 10,
                $overduePlans > 0 ? 0 : 1,
                $overduePlans > 0 ? 'Overdue' : 'Waiting on HOD',
            );
        })->filter()->values();
    }

    protected function teacherRows(int $schoolId, AcademicSession $session, Term $term, int $weekNumber): Collection
    {
        $assignments = TeacherAssignment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $schoolId))
            ->with(['teacher', 'schoolClass.schoolSection', 'subject'])
            ->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $pipeline = SchemeOfWork::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where($this->assignmentPairs($assignments))
            ->with(['subject', 'schoolClass'])
            ->get();

        $schemes = SchemeOfWork::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['active', 'approved'])
            ->where($this->assignmentPairs($assignments))
            ->with([
                'subject',
                'schoolClass',
                'topics' => fn ($q) => $q->with(['latestCoverageLog', 'latestLessonPlan', 'lessonPlans', 'catchUpPlan'])
                    ->orderBy('week_number')
                    ->orderBy('display_order'),
            ])
            ->get();

        $homeworkLogs = $this->homework->logsForCurrentWeek($session, $term);
        $classIds = $assignments->pluck('school_class_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        $attendanceLogs = $this->attendance->logsForCurrentWeek($session, $term, $classIds);
        $emptyAtRisk = [
            'identified' => 0,
            'with_plan' => 0,
            'without_plan' => 0,
            'rate' => 0.0,
            'records' => collect(),
            'unflagged' => collect(),
        ];

        return $assignments
            ->groupBy('teacher_id')
            ->map(function (Collection $rows) use ($session, $term, $weekNumber, $pipeline, $schemes, $homeworkLogs, $attendanceLogs, $emptyAtRisk) {
                /** @var TeacherAssignment $first */
                $first = $rows->first();
                $teacher = $first->teacher;
                if (! $teacher || ! $teacher->isTeacher()) {
                    return null;
                }

                $classIds = $rows->pluck('school_class_id')->unique()->map(fn ($id) => (int) $id)->all();
                $pairs = $rows->map(fn (TeacherAssignment $row) => (int) $row->school_class_id.':'.(int) $row->subject_id)->all();
                $matchesPair = function ($model) use ($pairs): bool {
                    return in_array((int) $model->school_class_id.':'.(int) $model->subject_id, $pairs, true);
                };

                $week = [
                    'term' => $term,
                    'week_number' => $weekNumber,
                    'schemes' => $schemes->filter($matchesPair)->values(),
                    'scheme_pipeline' => $pipeline->filter($matchesPair)->values(),
                    'assignments' => $rows->values(),
                    'homework_logs' => $homeworkLogs
                        ->filter(fn (HomeworkLog $log) => (int) $log->teacher_id === (int) $teacher->id)
                        ->values(),
                    'attendance_logs' => $attendanceLogs
                        ->filter(fn (AttendanceLog $log) => in_array((int) $log->school_class_id, $classIds, true))
                        ->values(),
                    'exam_term' => ['sittings' => collect()],
                    'at_risk' => $emptyAtRisk,
                ];

                $actions = $this->tasks->actionable($teacher, $session, $week);
                if ($actions->isEmpty()) {
                    return null;
                }

                $top = $actions->first();
                $extra = $actions->count() - 1;
                $section = $rows->pluck('schoolClass.schoolSection.name')->filter()->unique()->implode(', ') ?: '—';
                [$href, $cta] = $this->teacherCta($top);

                return $this->row(
                    'teacher-'.$teacher->id,
                    $teacher->name,
                    'Teacher',
                    $section,
                    trim(($top['title'] ?? '').' · '.($top['meta'] ?? ''), ' ·')
                        .($extra > 0 ? ' · '.$extra.' more' : ''),
                    $teacher->name,
                    $href,
                    $cta,
                    (int) ($top['urgency'] ?? TeacherTaskFeed::URGENCY_HOMEWORK),
                    2,
                    (string) ($top['badge'] ?? 'Teacher action'),
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, TeacherAssignment>  $assignments
     */
    protected function assignmentPairs(Collection $assignments): \Closure
    {
        return function (Builder $query) use ($assignments): void {
            $query->where(function (Builder $inner) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $inner->orWhere(function (Builder $pair) use ($assignment) {
                        $pair->where('school_class_id', $assignment->school_class_id)
                            ->where('subject_id', $assignment->subject_id);
                    });
                }
            });
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function hodCta(int $schemes, int $plans, int $coverage, int $homework, int $registers): array
    {
        if ($schemes > 0) {
            return [route('schemes.index', ['status' => 'submitted']), 'Open schemes'];
        }
        if ($plans > 0) {
            return [route('lesson-plans.index', ['status' => 'submitted']), 'Open reviews'];
        }
        if ($coverage > 0) {
            return [route('coverage-logs.index', ['status' => 'submitted']), 'Open coverage'];
        }
        if ($homework > 0) {
            return [route('homework.index'), 'Open homework'];
        }
        if ($registers > 0) {
            return [route('attendance.index'), 'Open registers'];
        }

        return [route('lesson-plans.index', ['status' => 'submitted']), 'Open reviews'];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array{0: string, 1: string}
     */
    protected function teacherCta(array $task): array
    {
        return match ($task['type'] ?? '') {
            'scheme' => [route('schemes.index'), 'Open schemes'],
            'plan', 'catch-up' => [route('lesson-plans.index'), 'Open plans'],
            'coverage' => [route('coverage-logs.index'), 'Open coverage'],
            'homework' => [route('homework.index'), 'Open homework'],
            'register' => [route('attendance.index'), 'Open registers'],
            'marks' => [route('exam-results.index'), 'Open marksheets'],
            default => [(string) ($task['href'] ?? route('dashboard')), (string) ($task['cta'] ?? 'Open')],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(
        string $key,
        string $name,
        string $role,
        string $section,
        string $issue,
        string $next,
        string $href,
        string $cta,
        int $urgency,
        int $group,
        string $badge,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'role' => $role,
            'section' => $section,
            'issue' => $issue,
            'next' => $next,
            'href' => $href,
            'cta' => $cta,
            'urgency' => $urgency,
            'group' => $group,
            'badge' => $badge,
        ];
    }
}
