<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\SchemeOfWork;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TeacherTaskFeed
{
    public const URGENCY_REJECTED = 10;

    public const URGENCY_CATCH_UP = 20;

    public const URGENCY_PLAN_OVERDUE = 30;

    public const URGENCY_PLAN = 40;

    public const URGENCY_COVERAGE = 50;

    public const URGENCY_REGISTER = 60;

    public const URGENCY_HOMEWORK = 70;

    public const URGENCY_MARKS = 80;

    public const URGENCY_AT_RISK_PLAN = 82;

    public const URGENCY_AT_RISK_FLAG = 85;

    public const URGENCY_WAITING = 90;

    public function __construct(
        protected AcademicPeriodService $periods,
        protected AcademicReportingPeriod $reporting,
        protected PlanningPolicy $planning,
    ) {}

    /**
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    public function compose(User $teacher, AcademicSession $session, array $week): Collection
    {
        /** @var Term|null $term */
        $term = $week['term'] ?? null;
        $blocked = ! $this->periods->acceptsNewActivity($session, $term);
        $tasks = collect();

        if ($term) {
            $tasks = $tasks
                ->concat($this->curriculumTasks($week, $term, $teacher, $blocked))
                ->concat($this->registerTasks($week, $blocked))
                ->concat($this->homeworkTasks($week, $blocked))
                ->concat($this->marksheetTasks($week, $blocked))
                ->concat($this->atRiskTasks($week, $teacher, $blocked));
        }

        return $tasks
            ->sortBy([
                ['urgency', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    protected function curriculumTasks(array $week, Term $term, User $teacher, bool $blocked): Collection
    {
        $weekNumber = (int) ($week['week_number'] ?? 1);
        $dueWeekday = $this->planning->lessonPlanDueWeekday($teacher->school);
        $dueAt = $this->reporting->dueAtInWeek($term, $weekNumber, $dueWeekday);
        $overdue = now()->gt($dueAt);
        $dueLabel = $this->planning->lessonPlanDueWeekdayName($teacher->school);

        /** @var Collection<int, SchemeOfWork> $schemes */
        $schemes = collect($week['schemes'] ?? [])->filter(fn (SchemeOfWork $scheme) => $scheme->isActive());

        $topics = $schemes->flatMap(function (SchemeOfWork $scheme) use ($weekNumber) {
            return $scheme->topics
                ->filter(function (Topic $topic) use ($weekNumber) {
                    return (int) $topic->week_number === $weekNumber
                        || $topic->catchUpPlan?->isOpen()
                        || $topic->latestLessonPlan?->status === 'rejected'
                        || $topic->latestCoverageLog?->status === 'rejected';
                })
                ->map(function (Topic $topic) use ($scheme) {
                    $topic->setRelation('schemeOfWork', $scheme);

                    return $topic;
                });
        })->unique('id')->values();

        return $topics->map(function (Topic $topic) use ($blocked, $overdue, $dueLabel) {
            $scheme = $topic->schemeOfWork;
            $plan = $topic->latestLessonPlan;
            $log = $topic->latestCoverageLog;
            $catchUp = $topic->catchUpPlan?->isOpen() ?? false;
            $context = $scheme->schoolClass->name.' · '.$scheme->subject->name;
            $label = $catchUp
                ? 'Catch-up · Wk '.$topic->week_number.' '.$topic->title
                : 'Wk '.$topic->week_number.' '.$topic->title;

            if ($plan?->status === 'submitted') {
                return $this->task(
                    'waiting-plan-'.$topic->id,
                    'waiting',
                    self::URGENCY_WAITING,
                    $label,
                    $context.' · Lesson plan awaiting HOD approval.',
                    null,
                    null,
                    'Waiting on HOD',
                    $blocked,
                );
            }

            if ($log?->status === 'submitted') {
                return $this->task(
                    'waiting-coverage-'.$topic->id,
                    'waiting',
                    self::URGENCY_WAITING,
                    $label,
                    $context.' · Coverage awaiting HOD verification.',
                    null,
                    null,
                    'Waiting on HOD',
                    $blocked,
                );
            }

            if (! $topic->hasApprovedLessonPlan()) {
                $rejected = $plan?->status === 'rejected';
                $href = $blocked ? null : ($plan?->isEditable()
                    ? route('lesson-plans.edit', $plan)
                    : route('lesson-plans.create', ['topic' => $topic->id]));
                $urgency = $rejected
                    ? self::URGENCY_REJECTED
                    : ($catchUp ? self::URGENCY_CATCH_UP : ($overdue ? self::URGENCY_PLAN_OVERDUE : self::URGENCY_PLAN));

                return $this->task(
                    'plan-'.$topic->id,
                    'plan',
                    $urgency,
                    $label,
                    $context.($rejected
                        ? ' · Revise the lesson plan.'
                        : ' · Submit the lesson plan by '.$dueLabel.'.'),
                    $href,
                    $blocked ? null : ($rejected ? 'Revise lesson plan' : 'Submit lesson plan'),
                    $rejected ? 'Rejected' : ($catchUp ? 'Catch-up' : ($overdue ? 'Overdue' : 'Due')),
                    $blocked,
                );
            }

            if ($topic->isLoggable()) {
                $rejected = $log?->status === 'rejected';
                $href = $blocked ? null : route('coverage-logs.create', ['topic' => $topic->id]);

                return $this->task(
                    'coverage-'.$topic->id,
                    'coverage',
                    $rejected || $catchUp ? ($rejected ? self::URGENCY_REJECTED : self::URGENCY_CATCH_UP) : self::URGENCY_COVERAGE,
                    $label,
                    $context.($rejected
                        ? ' · Resubmit workbook evidence.'
                        : ' · Record delivery against the approved plan.'),
                    $href,
                    $blocked ? null : ($rejected ? 'Resubmit coverage' : 'Log coverage'),
                    $rejected ? 'Rejected' : ($catchUp ? 'Catch-up' : 'Deliver'),
                    $blocked,
                );
            }

            return null;
        })->filter()->values();
    }

    /**
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    protected function registerTasks(array $week, bool $blocked): Collection
    {
        /** @var Collection<int, TeacherAssignment> $assignments */
        $assignments = collect($week['assignments'] ?? []);
        /** @var Collection<int, mixed> $logs */
        $logs = collect($week['attendance_logs'] ?? []);
        $today = now()->toDateString();
        $weekday = now()->isWeekday();

        return $assignments
            ->unique('school_class_id')
            ->values()
            ->map(function (TeacherAssignment $assignment) use ($logs, $today, $weekday, $blocked) {
                $classId = (int) $assignment->school_class_id;
                $classLogs = $logs->filter(fn ($log) => (int) $log->school_class_id === $classId);
                $todayLog = $classLogs->first(function ($log) use ($today) {
                    $date = $log->attendance_date instanceof Carbon
                        ? $log->attendance_date->toDateString()
                        : (string) $log->attendance_date;

                    return $date === $today;
                });

                $relevant = $weekday
                    ? $todayLog
                    : $classLogs->sortByDesc(function ($log) {
                        return $log->attendance_date instanceof Carbon
                            ? $log->attendance_date->toDateString()
                            : (string) $log->attendance_date;
                    })->first();

                if ($relevant) {
                    return $this->captureFollowUp(
                        $relevant,
                        'register-'.$classId,
                        'register',
                        'Register',
                        $assignment->schoolClass->name,
                        $blocked ? null : route('attendance.edit', $relevant),
                        $blocked,
                        'Revise register',
                        'Register awaiting HOD verification.',
                    );
                }

                $meta = ! $weekday
                    ? $assignment->schoolClass->name.' · No register this instructional week.'
                    : $assignment->schoolClass->name.' · No register for today.';

                return $this->task(
                    'register-'.$classId,
                    'register',
                    self::URGENCY_REGISTER,
                    'Take register',
                    $meta,
                    $blocked ? null : route('attendance.create', ['class' => $classId]),
                    $blocked ? null : 'Take register',
                    'Today',
                    $blocked,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    protected function homeworkTasks(array $week, bool $blocked): Collection
    {
        /** @var Collection<int, TeacherAssignment> $assignments */
        $assignments = collect($week['assignments'] ?? []);
        /** @var Collection<int, HomeworkLog> $logs */
        $logs = collect($week['homework_logs'] ?? []);

        return $assignments->map(function (TeacherAssignment $assignment) use ($logs, $blocked) {
            $pairLogs = $logs->filter(
                fn (HomeworkLog $log) => (int) $log->school_class_id === (int) $assignment->school_class_id
                    && (int) $log->subject_id === (int) $assignment->subject_id
            );
            $latest = $pairLogs->sortByDesc(function (HomeworkLog $log) {
                return $log->given_date instanceof Carbon
                    ? $log->given_date->toDateString()
                    : (string) $log->given_date;
            })->first();
            $pair = $assignment->school_class_id.':'.$assignment->subject_id;
            $context = $assignment->schoolClass->name.' · '.$assignment->subject->name;

            if ($latest) {
                return $this->captureFollowUp(
                    $latest,
                    'homework-'.$pair,
                    'homework',
                    'Homework log',
                    $context,
                    $blocked ? null : route('homework.edit', $latest),
                    $blocked,
                    'Revise homework',
                    'Homework awaiting HOD verification.',
                );
            }

            return $this->task(
                'homework-'.$pair,
                'homework',
                self::URGENCY_HOMEWORK,
                'Log homework',
                $context.' · No homework logged this instructional week.',
                $blocked ? null : route('homework.create', ['assignment' => $pair]),
                $blocked ? null : 'Log homework',
                'This week',
                $blocked,
            );
        })->filter()->values();
    }

    /**
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    protected function marksheetTasks(array $week, bool $blocked): Collection
    {
        $sittings = collect($week['exam_term']['sittings'] ?? []);

        return $sittings->map(function (array $sitting) use ($blocked) {
            $enrolled = (int) ($sitting['enrolled'] ?? 0);
            $recorded = (int) ($sitting['recorded'] ?? 0);
            if ($enrolled <= 0) {
                return null;
            }

            /** @var TeacherAssignment $assignment */
            $assignment = $sitting['assignment'];
            $query = ['assignment' => $assignment->school_class_id.':'.$assignment->subject_id];
            $href = $blocked ? null : route('exam-results.edit', $query);
            $context = $assignment->schoolClass->name.' · '.$assignment->subject->name;
            $status = (string) ($sitting['review_status'] ?? 'incomplete');

            if ($recorded < $enrolled) {
                return $this->task(
                    'marks-'.$assignment->id,
                    'marks',
                    self::URGENCY_MARKS,
                    'Complete marksheet',
                    $context.' · '.($enrolled - $recorded).' of '.$enrolled.' learners without a score.',
                    $href,
                    $blocked ? null : 'Open marksheet',
                    'Incomplete',
                    $blocked,
                );
            }

            if ($status === 'rejected') {
                return $this->task(
                    'marks-'.$assignment->id,
                    'marks',
                    self::URGENCY_REJECTED,
                    'Marksheet',
                    $context.' · '.($sitting['rejection_reason'] ?: 'Returned for revision.'),
                    $href,
                    $blocked ? null : 'Revise marksheet',
                    'Rejected',
                    $blocked,
                );
            }

            if ($status === 'submitted') {
                return $this->task(
                    'marks-'.$assignment->id,
                    'waiting',
                    self::URGENCY_WAITING,
                    'Marksheet',
                    $context.' · Marksheet awaiting HOD verification.',
                    null,
                    null,
                    'Waiting on HOD',
                    $blocked,
                );
            }

            return null;
        })->filter()->values();
    }

    /**
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    protected function atRiskTasks(array $week, User $teacher, bool $blocked): Collection
    {
        $atRisk = $week['at_risk'] ?? [];
        $unflagged = collect($atRisk['unflagged'] ?? []);
        $records = collect($atRisk['records'] ?? []);

        $flagTasks = $unflagged->map(function (array $row) use ($blocked) {
            $learner = $row['learner'];

            return $this->task(
                'at-risk-flag-'.$learner->id,
                'at-risk-flag',
                self::URGENCY_AT_RISK_FLAG,
                $learner->name,
                ($learner->schoolClass->name ?? 'Class')
                    .' · Verified score '.number_format((float) $row['lowest'], 0).'% is below pass mark. Flag so a Tier 2/3 plan can be added.',
                $blocked ? null : route('at-risk.index'),
                $blocked ? null : 'Open caseload',
                'Not flagged',
                $blocked,
            );
        });

        $planTasks = $records
            ->filter(fn ($record) => $record instanceof AtRiskLearner && ! $record->hasActivePlan())
            ->map(function (AtRiskLearner $record) use ($teacher, $blocked) {
                $href = $blocked ? null : (
                    $teacher->canManageInterventionPlans()
                        ? route('at-risk.plans.create', $record)
                        : route('at-risk.show', $record)
                );

                return $this->task(
                    'at-risk-plan-'.$record->id,
                    'at-risk-plan',
                    self::URGENCY_AT_RISK_PLAN,
                    $record->learner->name,
                    ($record->schoolClass->name ?? 'Class').' · Identified without an active plan. Learning Support or HOD adds the IIP.',
                    $href,
                    $blocked ? null : ($teacher->canManageInterventionPlans() ? 'Add intervention plan' : 'Open caseload'),
                    'No plan',
                    $blocked,
                );
            });

        return $flagTasks->concat($planTasks)->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function captureFollowUp(
        HomeworkLog|AttendanceLog $log,
        string $key,
        string $type,
        string $title,
        string $context,
        ?string $editHref,
        bool $blocked,
        string $reviseCta,
        string $waitingMeta,
    ): ?array {
        if ($log->isRejected()) {
            return $this->task(
                $key,
                $type,
                self::URGENCY_REJECTED,
                $title,
                $context.' · '.($log->rejection_reason ?: 'Returned for revision.'),
                $editHref,
                $blocked ? null : $reviseCta,
                'Rejected',
                $blocked,
            );
        }

        if ($log->isSubmitted()) {
            return $this->task(
                $key,
                'waiting',
                self::URGENCY_WAITING,
                $title,
                $context.' · '.$waitingMeta,
                null,
                null,
                'Waiting on HOD',
                $blocked,
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function task(
        string $key,
        string $type,
        int $urgency,
        string $title,
        string $meta,
        ?string $href,
        ?string $cta,
        string $badge,
        bool $blocked,
    ): array {
        if ($blocked) {
            $href = null;
            $cta = null;
            $badge = 'Term closed';
            $meta .= ' New records are blocked until the term is open.';
        }

        return compact('key', 'type', 'urgency', 'title', 'meta', 'href', 'cta', 'badge', 'blocked');
    }
}
