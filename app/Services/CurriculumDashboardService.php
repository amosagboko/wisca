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

    public function __construct(
        protected HomeworkCalculationService $homework,
        protected AttendanceCalculationService $attendance,
        protected ObservationCalculationService $observations,
        protected ExamCalculationService $exams,
        protected AtRiskCalculationService $atRisk,
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

        $classIds = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->pluck('school_class_id')
            ->unique()
            ->all();

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

        return [
            'term' => $term,
            'week_number' => $weekNumber,
            'schemes' => $schemes,
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
            'exam_term' => $this->teacherExamSummary($teacher, $session, $term),
            'at_risk' => $this->teacherAtRiskSummary($teacher, $session, $term),
        ];
    }

    public function hodOperations(int $schoolId, AcademicSession $session): array
    {
        $pending = TopicCoverageLog::where('status', 'submitted')
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $schoolId))
            ->with(['topic', 'teacher', 'schoolClass', 'subject'])
            ->latest()
            ->get();

        $pendingPlans = LessonPlan::where('status', 'submitted')
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $schoolId))
            ->with(['topic', 'teacher', 'schoolClass', 'subject'])
            ->latest()
            ->get();

        $schemes = SchemeOfWork::where('academic_session_id', $session->id)
            ->whereIn('status', ['active', 'approved'])
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $schoolId))
            ->with(['topics', 'schoolClass', 'subject'])
            ->get();

        $assignments = TeacherAssignment::where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->with('teacher')
            ->get();

        $coverageRows = $schemes->map(function (SchemeOfWork $scheme) use ($assignments) {
            $coverage = $scheme->coverageFromLoadedTopics();
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
                'total' => $coverage['total'],
                'covered' => $coverage['covered'],
                'rate' => $coverage['rate'],
                'flagged' => $coverage['rate'] < self::RED_FLAG_RATE,
            ];
        })->sortBy('rate')->values();

        $term = Term::currentForSession($session->id);
        $homeworkLogs = $term
            ? $this->homework->logsForCurrentWeek($session, $term)
            : collect();
        $homeworkWeek = $this->homework->rateForLogs($homeworkLogs);

        $attendanceLogs = $term
            ? $this->attendance->logsForCurrentWeek($session, $term)
            : collect();
        $attendanceWeek = $this->attendance->rateForLogs($attendanceLogs);

        $observations = Observation::query()
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->whereHas('schoolClass', fn (Builder $q) => $q->where('school_id', $schoolId))
            ->with(['teacher', 'observer', 'schoolClass', 'subject'])
            ->latest('observation_date')
            ->get();
        $observationTerm = $this->observations->rateForObservations($observations);

        $teachers = collect()
            ->concat($pending->pluck('teacher'))
            ->concat($pendingPlans->pluck('teacher'))
            ->concat($coverageRows->pluck('teacher'))
            ->concat($homeworkLogs->pluck('teacher'))
            ->concat($attendanceLogs->pluck('recorder'))
            ->concat($observations->pluck('teacher'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $classes = collect()
            ->concat($pending->pluck('schoolClass'))
            ->concat($pendingPlans->pluck('schoolClass'))
            ->concat($coverageRows->map(fn ($row) => $row['scheme']->schoolClass))
            ->concat($homeworkLogs->pluck('schoolClass'))
            ->concat($attendanceLogs->pluck('schoolClass'))
            ->concat($observations->pluck('schoolClass'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $subjects = collect()
            ->concat($pending->pluck('subject'))
            ->concat($pendingPlans->pluck('subject'))
            ->concat($coverageRows->map(fn ($row) => $row['scheme']->subject))
            ->concat($homeworkLogs->pluck('subject'))
            ->concat($observations->pluck('subject'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [
            'pending' => $pending,
            'pending_plans' => $pendingPlans,
            'pending_by_teacher' => $pending->groupBy('teacher_id'),
            'pending_by_class' => $pending->sortBy(fn ($log) => $log->schoolClass->name)->groupBy('school_class_id'),
            'plans_by_teacher' => $pendingPlans->groupBy('teacher_id'),
            'plans_by_class' => $pendingPlans->sortBy(fn ($plan) => $plan->schoolClass->name)->groupBy('school_class_id'),
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
            'at_risk' => $term ? $this->atRisk->monthSummary($session, $term) : [
                'identified' => 0, 'with_plan' => 0, 'without_plan' => 0, 'rate' => 0.0, 'records' => collect(),
            ],
            'inbox' => [
                'teachers' => $teachers,
                'classes' => $classes,
                'subjects' => $subjects,
                'teacher_count' => $pending->pluck('teacher_id')->merge($pendingPlans->pluck('teacher_id'))->unique()->count(),
            ],
        ];
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
                'topics' => fn ($q) => $q->with(['latestCoverageLog', 'latestLessonPlan', 'lessonPlans'])
                    ->orderBy('week_number')
                    ->orderBy('display_order'),
            ])
            ->get();
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
