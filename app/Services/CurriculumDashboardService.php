<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\LessonPlan;
use App\Models\Observation;
use App\Models\SchemeOfWork;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\TopicCoverageLog;
use App\Models\User;
use App\Support\ExamPassMark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CurriculumDashboardService
{
    public const RED_FLAG_RATE = 0.95;

    public const PENDING_PAGE_SIZE = 10;

    public const REVIEW_FEED_CAP = 25;

    public function __construct(
        protected HomeworkCalculationService $homework,
        protected AttendanceCalculationService $attendance,
        protected ObservationCalculationService $observations,
        protected ExamCalculationService $exams,
        protected AtRiskCalculationService $atRisk,
        protected TeacherTaskFeed $tasks,
        protected HodScope $scope,
        protected AcademicReportingPeriod $period,
        protected TopicCatchUpService $catchUps,
    ) {}

    public function teacherWeek(User $teacher, AcademicSession $session): array
    {
        $term = Term::currentForSession($session->id);
        $schemes = $this->schemesForTeacher($teacher, $session->id);
        $maxWeek = max(1, (int) $schemes->flatMap->topics->max('week_number'));
        $weekNumber = $term?->schemeWeekNumber($maxWeek) ?? 1;

        $thisWeekTopics = $schemes->flatMap(function (SchemeOfWork $scheme) use ($weekNumber) {
            return $scheme->topics
                ->where('week_number', $weekNumber)
                ->map(function (Topic $topic) use ($scheme) {
                    $topic->setRelation('schemeOfWork', $scheme);

                    return $topic;
                });
        })->values();

        $totals = $schemes->reduce(function (array $carry, SchemeOfWork $scheme) {
            $coverage = $scheme->coverageFromLoadedTopics();
            $carry['total'] += $coverage['total'];
            $carry['covered'] += $coverage['covered'];

            return $carry;
        }, ['total' => 0, 'covered' => 0]);

        $rate = $totals['total'] === 0 ? 0.0 : round($totals['covered'] / $totals['total'], 4);

        $logs = TopicCoverageLog::where('teacher_id', $teacher->id)
            ->with(['topic', 'schoolClass', 'subject'])
            ->latest()
            ->limit(12)
            ->get();

        $homeworkWeekLogs = $term
            ? $this->homework->logsForCurrentWeek($session, $term, $teacher->id)
            : collect();
        $homeworkWeek = $this->homework->rateForLogs($homeworkWeekLogs);

        $assignments = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->with(['schoolClass', 'subject'])
            ->get();

        $classIds = $assignments->pluck('school_class_id')->unique()->all();

        $attendanceWeekLogs = $term
            ? $this->attendance->logsForCurrentWeek($session, $term, $classIds)
            : collect();
        $attendanceWeek = $this->attendance->rateForLogs($attendanceWeekLogs);

        $teacherObservations = Observation::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $session->id)
            ->with(['observer', 'schoolClass', 'subject'])
            ->latest('observation_date')
            ->limit(6)
            ->get();

        $examTerm = $this->teacherExamSummary($teacher, $session, $term);
        $week = [
            'term' => $term,
            'week_number' => $weekNumber,
            'schemes' => $schemes,
            'assignments' => $assignments,
            'homework_logs' => $homeworkWeekLogs,
            'attendance_logs' => $attendanceWeekLogs,
            'exam_term' => $examTerm,
            'at_risk' => $this->teacherAtRiskSummary($teacher, $session, $term),
        ];

        return [
            'term' => $term,
            'week_number' => $weekNumber,
            'schemes' => $schemes,
            'tasks' => $this->tasks->compose($teacher, $session, $week),
            'this_week_topics' => $thisWeekTopics,
            'remaining_topics' => $schemes->flatMap(function (SchemeOfWork $scheme) {
                return $scheme->topics
                    ->filter(fn (Topic $topic) => in_array($topic->status, ['planned', 'in_progress'], true))
                    ->map(function (Topic $topic) use ($scheme) {
                        $topic->setRelation('schemeOfWork', $scheme);

                        return $topic;
                    });
            })->sortBy([
                ['week_number', 'asc'],
                ['display_order', 'asc'],
            ])->values(),
            'coverage' => [
                'total' => $totals['total'],
                'covered' => $totals['covered'],
                'rate' => $rate,
            ],
            'behind' => $rate < self::RED_FLAG_RATE,
            'logs' => $logs,
            'homework_logs' => $homeworkWeekLogs,
            'homework_week' => $homeworkWeek,
            'attendance_logs' => $attendanceWeekLogs,
            'attendance_week' => $attendanceWeek,
            'observations' => $teacherObservations,
            'exam_term' => $examTerm,
            'at_risk' => $week['at_risk'],
        ];
    }

    /**
     * @param  array{
     *     term_id?: int,
     *     week?: int,
     *     teacher_id?: int,
     *     class_id?: int,
     *     subject_id?: int,
     *     group?: string
     * }  $filters
     * @return array<string, mixed>
     */
    public function hodOperations(User $hod, AcademicSession $session, array $filters = []): array
    {
        $schoolId = (int) $hod->school_id;
        $termId = (int) ($filters['term_id'] ?? 0);
        $week = (int) ($filters['week'] ?? 0);
        $teacherId = (int) ($filters['teacher_id'] ?? 0);
        $classId = (int) ($filters['class_id'] ?? 0);
        $subjectId = (int) ($filters['subject_id'] ?? 0);
        $group = in_array($filters['group'] ?? 'teacher', ['teacher', 'class', 'none'], true)
            ? ($filters['group'] ?? 'teacher')
            : 'teacher';

        $currentTerm = Term::currentForSession($session->id);
        $term = $termId
            ? Term::where('academic_session_id', $session->id)->whereKey($termId)->first()
            : $currentTerm;

        $subjectIds = $this->scope->subjectIds($hod);
        $classIds = $this->scope->classIds($hod);
        $maxWeek = $term ? $this->period->maxWeekForTerm($term) : 1;

        $planQuery = $this->pendingLessonPlanQuery($hod, $session, $termId, $week, $teacherId, $classId, $subjectId);
        $coverageQuery = $this->pendingCoverageQuery($hod, $session, $termId, $week, $teacherId, $classId, $subjectId);

        $pendingPlanTotal = (clone $planQuery)->count();
        $pendingCoverageTotal = (clone $coverageQuery)->count();

        $pendingPlans = (clone $planQuery)
            ->with(['topic', 'teacher', 'schoolClass', 'subject'])
            ->latest()
            ->paginate(self::PENDING_PAGE_SIZE, ['*'], 'plans_page')
            ->withQueryString();

        $pending = (clone $coverageQuery)
            ->with(['topic', 'teacher', 'schoolClass', 'subject'])
            ->latest()
            ->paginate(self::PENDING_PAGE_SIZE, ['*'], 'coverage_page')
            ->withQueryString();

        $pendingPlanItems = (clone $planQuery)
            ->with(['topic', 'teacher', 'schoolClass', 'subject'])
            ->latest()
            ->limit(self::REVIEW_FEED_CAP)
            ->get();

        $pendingCoverageItems = (clone $coverageQuery)
            ->with(['topic', 'teacher', 'schoolClass', 'subject'])
            ->latest()
            ->limit(self::REVIEW_FEED_CAP)
            ->get();

        $schemes = SchemeOfWork::where('academic_session_id', $session->id)
            ->whereIn('status', ['active', 'approved'])
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $schoolId))
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->tap(fn (Builder $q) => $this->scope->constrainBySubject($q, $hod))
            ->with(['schoolClass', 'subject'])
            ->withCount([
                'topics',
                'topics as covered_topics_count' => fn ($q) => $q->where('status', 'covered'),
            ])
            ->get();

        $assignments = TeacherAssignment::where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $schoolId))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->tap(fn (Builder $q) => $this->scope->constrainBySubject($q, $hod))
            ->with(['teacher', 'schoolClass', 'subject'])
            ->get();

        $coverageRows = $schemes->map(function (SchemeOfWork $scheme) use ($assignments) {
            $total = (int) $scheme->topics_count;
            $covered = (int) $scheme->covered_topics_count;
            $rate = $total === 0 ? 0.0 : round($covered / $total, 4);
            $assignment = $assignments->first(
                fn (TeacherAssignment $row) => $row->school_class_id === $scheme->school_class_id
                    && $row->subject_id === $scheme->subject_id
            );

            return [
                'scheme' => $scheme,
                'teacher' => $assignment?->teacher,
                'teacher_id' => $assignment?->teacher_id,
                'class_id' => $scheme->school_class_id,
                'subject_id' => $scheme->subject_id,
                'total' => $total,
                'covered' => $covered,
                'rate' => $rate,
                'flagged' => $rate < self::RED_FLAG_RATE,
            ];
        })->sortBy('rate')->values();

        $homeworkLogs = $term
            ? $this->homework->logsForCurrentWeek($session, $term, $teacherId ?: null, $subjectIds)
            : collect();
        if ($classId) {
            $homeworkLogs = $homeworkLogs->where('school_class_id', $classId)->values();
        }
        $homeworkWeek = $this->homework->rateForLogs($homeworkLogs);

        $attendanceClassIds = $classId ? [$classId] : $classIds;
        $attendanceLogs = $term
            ? $this->attendance->logsForCurrentWeek($session, $term, $attendanceClassIds)
            : collect();
        if ($teacherId) {
            $attendanceLogs = $attendanceLogs->where('recorded_by', $teacherId)->values();
        }
        $attendanceWeek = $this->attendance->rateForLogs($attendanceLogs);

        $observations = Observation::query()
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $schoolId))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->tap(fn (Builder $q) => $this->scope->constrainBySubject($q, $hod))
            ->with(['teacher', 'observer', 'schoolClass', 'subject'])
            ->latest('observation_date')
            ->limit(50)
            ->get();
        $observationTerm = $this->observations->rateForObservations($observations);

        $examSittings = $term
            ? $this->exams->sittingOverviews($session, $term, $assignments)
            : collect();

        $atRiskClassIds = $classId ? [$classId] : $classIds;
        $atRisk = $term ? $this->atRisk->monthSummary($session, $term, $atRiskClassIds, false) : [
            'identified' => 0, 'with_plan' => 0, 'without_plan' => 0, 'rate' => 0.0, 'records' => collect(),
        ];
        $atRiskUnflagged = $term ? $this->atRisk->belowPassUnflagged($session, $term, $atRiskClassIds) : collect();
        $atRiskWithoutPlan = $term
            ? $this->atRisk->withoutPlanRecords($session, $atRiskClassIds, self::REVIEW_FEED_CAP)
            : collect();

        $behindWeek = $term ? $this->catchUps->behindWeek($term, $week) : 1;
        $catchUpNeededQuery = $term
            ? $this->catchUps->neededQuery($session, $term, $behindWeek, $hod)
                ->when($classId, fn ($q) => $q->whereHas('schemeOfWork', fn ($s) => $s->where('school_class_id', $classId)))
                ->when($subjectId, fn ($q) => $q->whereHas('schemeOfWork', fn ($s) => $s->where('subject_id', $subjectId)))
            : Topic::query()->whereRaw('1 = 0');
        $catchUpNeededTotal = (clone $catchUpNeededQuery)->count();
        $catchUpNeeded = (clone $catchUpNeededQuery)
            ->with(['schemeOfWork.schoolClass', 'schemeOfWork.subject', 'catchUpPlan'])
            ->orderBy('week_number')
            ->limit(self::REVIEW_FEED_CAP)
            ->get();

        $teachers = $assignments->pluck('teacher')->filter()->unique('id')->sortBy('name')->values();
        $classes = $assignments->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();
        $subjects = $assignments->pluck('subject')->filter()->unique('id')->sortBy('name')->values();

        $planPage = collect($pendingPlans->items());
        $coveragePage = collect($pending->items());

        return [
            'pending' => $pending,
            'pending_plans' => $pendingPlans,
            'pending_plan_total' => $pendingPlanTotal,
            'pending_coverage_total' => $pendingCoverageTotal,
            'pending_plan_items' => $pendingPlanItems,
            'pending_coverage_items' => $pendingCoverageItems,
            'pending_by_teacher' => $coveragePage->groupBy('teacher_id'),
            'pending_by_class' => $coveragePage->sortBy(fn ($log) => $log->schoolClass->name)->groupBy('school_class_id'),
            'plans_by_teacher' => $planPage->groupBy('teacher_id'),
            'plans_by_class' => $planPage->sortBy(fn ($plan) => $plan->schoolClass->name)->groupBy('school_class_id'),
            'queue_group' => $group,
            'coverage_rows' => $coverageRows,
            'red_flags' => $coverageRows->where('flagged', true)->values(),
            'homework_logs' => $homeworkLogs,
            'homework_week' => $homeworkWeek,
            'attendance_logs' => $attendanceLogs,
            'attendance_week' => $attendanceWeek,
            'observations' => $observations,
            'observation_term' => $observationTerm,
            'exam_term' => $term ? $this->exams->termSummary($session, $term) : [
                'enrolled' => 0, 'passed' => 0, 'rate' => 0.0, 'pass_mark' => ExamPassMark::percent(), 'sittings' => 0,
            ],
            'at_risk' => $atRisk,
            'at_risk_unflagged' => $atRiskUnflagged,
            'at_risk_without_plan' => $atRiskWithoutPlan,
            'catch_up_needed' => $catchUpNeeded,
            'catch_up_needed_total' => $catchUpNeededTotal,
            'assignments' => $assignments,
            'exam_sittings' => $examSittings,
            'session_id' => $session->id,
            'hod_department' => $hod->department,
            'max_week' => $maxWeek,
            'inbox' => [
                'teachers' => $teachers,
                'classes' => $classes,
                'subjects' => $subjects,
                'teacher_count' => $assignments->pluck('teacher_id')->unique()->count(),
            ],
        ];
    }

    protected function pendingLessonPlanQuery(
        User $hod,
        AcademicSession $session,
        int $termId,
        int $week,
        int $teacherId,
        int $classId,
        int $subjectId,
    ): Builder {
        return LessonPlan::query()
            ->where('status', 'submitted')
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $hod->school_id))
            ->whereHas('topic.schemeOfWork', function (Builder $q) use ($session, $termId) {
                $q->where('academic_session_id', $session->id);
                if ($termId) {
                    $q->where('term_id', $termId);
                }
            })
            ->when($week, fn ($q) => $q->whereHas('topic', fn ($topic) => $topic->where('week_number', $week)))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->tap(fn (Builder $q) => $this->scope->constrainBySubject($q, $hod));
    }

    protected function pendingCoverageQuery(
        User $hod,
        AcademicSession $session,
        int $termId,
        int $week,
        int $teacherId,
        int $classId,
        int $subjectId,
    ): Builder {
        return TopicCoverageLog::query()
            ->where('status', 'submitted')
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $hod->school_id))
            ->whereHas('topic.schemeOfWork', function (Builder $q) use ($session, $termId) {
                $q->where('academic_session_id', $session->id);
                if ($termId) {
                    $q->where('term_id', $termId);
                }
            })
            ->when($week, fn ($q) => $q->whereHas('topic', fn ($topic) => $topic->where('week_number', $week)))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->tap(fn (Builder $q) => $this->scope->constrainBySubject($q, $hod));
    }

    public function teacherAtRiskSummary(User $teacher, AcademicSession $session, ?Term $term): array
    {
        $empty = [
            'identified' => 0,
            'with_plan' => 0,
            'without_plan' => 0,
            'rate' => 0.0,
            'records' => collect(),
            'unflagged' => collect(),
        ];

        if (! $term) {
            return $empty;
        }

        $classIds = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->pluck('school_class_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $records = $this->atRisk->openRecords($session, $term)
            ->whereIn('school_class_id', $classIds)
            ->values();
        $identified = $records->count();
        $withPlan = $records->filter->hasActivePlan()->count();

        return [
            'identified' => $identified,
            'with_plan' => $withPlan,
            'without_plan' => max(0, $identified - $withPlan),
            'rate' => $identified > 0 ? round($withPlan / $identified, 4) : 0.0,
            'records' => $records,
            'unflagged' => $this->atRisk->belowPassUnflagged($session, $term, $classIds),
        ];
    }

    public function teacherExamSummary(User $teacher, AcademicSession $session, ?Term $term): array
    {
        $empty = [
            'enrolled' => 0,
            'passed' => 0,
            'recorded' => 0,
            'rate' => 0.0,
            'pass_mark' => ExamPassMark::percent(),
            'sittings' => [],
        ];

        if (! $term) {
            return $empty;
        }

        $assignments = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->with(['schoolClass', 'subject'])
            ->get();

        $sittings = $assignments->map(function (TeacherAssignment $assignment) use ($session, $term) {
            $summary = $this->exams->sittingSummary(
                $session,
                $term,
                $assignment->school_class_id,
                $assignment->subject_id
            );

            return [
                'assignment' => $assignment,
                'enrolled' => $summary['enrolled'],
                'passed' => $summary['passed'],
                'recorded' => $summary['recorded'],
                'rate' => $summary['rate'],
                'pass_mark' => $summary['pass_mark'],
                'review_status' => $summary['review_status'],
                'rejection_reason' => $summary['rejection_reason'],
                'locked' => $summary['locked'],
            ];
        });

        $enrolled = (int) $sittings->sum('enrolled');
        $passed = (int) $sittings->sum('passed');
        $recorded = (int) $sittings->sum('recorded');

        return [
            'enrolled' => $enrolled,
            'passed' => $passed,
            'recorded' => $recorded,
            'rate' => $enrolled > 0 ? round($passed / $enrolled, 4) : 0.0,
            'pass_mark' => ExamPassMark::percent(),
            'sittings' => $sittings,
        ];
    }

    public function schemesForTeacher(User $teacher, int $sessionId): Collection
    {
        $assignments = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        return SchemeOfWork::query()
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', ['active', 'approved'])
            ->where(function (Builder $query) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $query->orWhere(function (Builder $inner) use ($assignment) {
                        $inner->where('school_class_id', $assignment->school_class_id)
                            ->where('subject_id', $assignment->subject_id);
                    });
                }
            })
            ->with([
                'subject',
                'schoolClass',
                'topics' => fn ($q) => $q->with(['latestCoverageLog', 'latestLessonPlan', 'lessonPlans', 'catchUpPlan'])
                    ->orderBy('week_number')
                    ->orderBy('display_order'),
            ])
            ->get();
    }

    public function schemesForTeacherPlanning(User $teacher, int $sessionId): Collection
    {
        return $this->schemesForTeacher($teacher, $sessionId)
            ->filter(fn (SchemeOfWork $scheme) => $scheme->isActive())
            ->values();
    }

    public function teacherOwnsTopic(User $teacher, Topic $topic, int $sessionId): bool
    {
        $scheme = $topic->schemeOfWork;

        if (! $scheme || (int) $scheme->academic_session_id !== $sessionId) {
            return false;
        }

        return TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $sessionId)
            ->where('school_class_id', $scheme->school_class_id)
            ->where('subject_id', $scheme->subject_id)
            ->where('status', 'active')
            ->exists();
    }
}
