<?php

namespace App\Services;

use App\Models\AtRiskLearner;
use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\LessonPlan;
use App\Models\TeacherAssignment;
use App\Models\Topic;
use App\Models\TopicCoverageLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HodReviewFeed
{
    public const URGENCY_PLAN_SLA = 8;

    public const URGENCY_PLAN = 10;

    public const URGENCY_COVERAGE = 20;

    public const URGENCY_CATCH_UP = 22;

    public const URGENCY_HOMEWORK_REVIEW = 30;

    public const URGENCY_REGISTER_REVIEW = 40;

    public const URGENCY_EXAM_REVIEW = 50;

    public const URGENCY_AT_RISK_PLAN = 55;

    public const URGENCY_REGISTER = 60;

    public const URGENCY_HOMEWORK = 70;

    public const URGENCY_EXAM = 80;

    public const URGENCY_AT_RISK_FLAG = 85;

    public const INBOX_LIMIT = WorkInbox::PAGE_SIZE;

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'plan' => 'Lesson plans',
        'coverage' => 'Coverage logs',
        'catch-up' => 'Catch-up needed',
        'homework-review' => 'Homework reviews',
        'register-review' => 'Register reviews',
        'exam-review' => 'Marksheet reviews',
        'at-risk-plan' => 'Intervention plans needed',
        'exam' => 'Incomplete marksheets',
        'register' => 'Missing registers',
        'homework' => 'Missing homework',
        'at-risk-flag' => 'Unflagged learners',
    ];

    /**
     * @param  array<string, mixed>  $ops
     * @return Collection<int, array<string, mixed>>
     */
    public function compose(array $ops, ?int $limit = self::INBOX_LIMIT): Collection
    {
        $sessionId = (int) ($ops['session_id'] ?? 0);

        $items = collect()
            ->concat($this->planReviews($ops['pending_plan_items'] ?? $ops['pending_plans'] ?? collect()))
            ->concat($this->coverageReviews($ops['pending_coverage_items'] ?? $ops['pending'] ?? collect()))
            ->concat($this->catchUpNeeded($ops['catch_up_needed'] ?? collect()))
            ->concat($this->homeworkReviews($ops['homework_logs'] ?? collect()))
            ->concat($this->registerReviews($ops['attendance_logs'] ?? collect()))
            ->concat($this->examReviews($ops['exam_sittings'] ?? collect()))
            ->concat($this->atRiskPlanGaps($ops['at_risk_without_plan'] ?? collect()))
            ->concat($this->registerGaps($ops['assignments'] ?? collect(), $ops['attendance_logs'] ?? collect(), $sessionId))
            ->concat($this->homeworkGaps($ops['assignments'] ?? collect(), $ops['homework_logs'] ?? collect(), $sessionId))
            ->concat($this->examGaps($ops['exam_sittings'] ?? collect()))
            ->concat($this->atRiskFlagGaps($ops['at_risk_unflagged'] ?? collect()))
            ->sortBy([
                ['urgency', 'asc'],
                ['title', 'asc'],
            ])
            ->values();

        if ($limit !== null) {
            return $items->take($limit)->values();
        }

        return $items;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array{type: string, label: string, count: int, overdue: int, urgency: int}>
     */
    public function typeCounts(Collection $items): Collection
    {
        return WorkInbox::typeCounts($items, self::TYPE_LABELS);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public function grouped(Collection $items, string $groupBy = 'teacher'): Collection
    {
        return WorkInbox::grouped($items, $groupBy, self::TYPE_LABELS);
    }

    /**
     * @param  Collection<int, LessonPlan>|mixed  $plans
     * @return Collection<int, array<string, mixed>>
     */
    protected function planReviews(mixed $plans): Collection
    {
        return collect($plans)->filter(fn ($plan) => $plan instanceof LessonPlan)->map(function (LessonPlan $plan) {
            $overdue = $plan->isReviewSlaOverdue();

            return $this->item(
                'review-plan-'.$plan->id,
                'plan',
                $overdue ? self::URGENCY_PLAN_SLA : self::URGENCY_PLAN,
                $plan->topic?->title ?? 'Lesson plan',
                ($plan->teacher?->name ?? 'Teacher').' · '.$plan->schoolClass?->name.' · '.$plan->subject?->name
                    .($overdue
                        ? ' · 24-hour HOD review SLA has passed. Decide so coverage can be logged.'
                        : ' · Approve or return within 24 hours of submission.'),
                route('dashboard', request()->query()).'#review-plan-'.$plan->id,
                'Review plan',
                $overdue ? 'Overdue' : 'Review',
                [
                    'teacher' => $plan->teacher?->name,
                    'class' => $plan->schoolClass?->name,
                    'subject' => $plan->subject?->name,
                ],
            );
        })->values();
    }

    /**
     * @param  Collection<int, Topic>|mixed  $topics
     * @return Collection<int, array<string, mixed>>
     */
    protected function catchUpNeeded(mixed $topics): Collection
    {
        return collect($topics)->filter(fn ($topic) => $topic instanceof Topic)->map(function (Topic $topic) {
            $scheme = $topic->schemeOfWork;

            return $this->item(
                'catch-up-needed-'.$topic->id,
                'catch-up',
                self::URGENCY_CATCH_UP,
                'Wk '.$topic->week_number.' '.$topic->title,
                ($scheme?->schoolClass?->name ?? 'Class').' · '.($scheme?->subject?->name ?? 'Subject')
                    .' · Behind the instructional week without verified coverage. Open catch-up so the teacher can recover this Active SoW topic. This is not an IIP.',
                route('dashboard', request()->query()).'#catch-up-needed-'.$topic->id,
                'Open catch-up',
                'Catch-up',
                [
                    'teacher' => null,
                    'class' => $scheme?->schoolClass?->name,
                    'subject' => $scheme?->subject?->name,
                ],
            );
        })->values();
    }

    /**
     * @param  Collection<int, TopicCoverageLog>|mixed  $logs
     * @return Collection<int, array<string, mixed>>
     */
    protected function coverageReviews(mixed $logs): Collection
    {
        return collect($logs)->map(function (TopicCoverageLog $log) {
            return $this->item(
                'review-coverage-'.$log->id,
                'coverage',
                self::URGENCY_COVERAGE,
                $log->topic?->title ?? 'Coverage log',
                ($log->teacher?->name ?? 'Teacher').' · '.$log->schoolClass?->name.' · '.$log->subject?->name
                    .' · Verify workbook evidence.',
                route('dashboard', request()->query()).'#review-coverage-'.$log->id,
                'Review coverage',
                'Review',
                [
                    'teacher' => $log->teacher?->name,
                    'class' => $log->schoolClass?->name,
                    'subject' => $log->subject?->name,
                ],
            );
        })->values();
    }

    /**
     * @param  Collection<int, HomeworkLog>|mixed  $logs
     * @return Collection<int, array<string, mixed>>
     */
    protected function homeworkReviews(mixed $logs): Collection
    {
        return collect($logs)
            ->filter(fn ($log) => $log instanceof HomeworkLog && $log->isSubmitted())
            ->map(function (HomeworkLog $log) {
                return $this->item(
                    'review-homework-'.$log->id,
                    'homework-review',
                    self::URGENCY_HOMEWORK_REVIEW,
                    $log->title ?: 'Homework log',
                    ($log->teacher?->name ?? 'Teacher').' · '.$log->schoolClass?->name.' · '.$log->subject?->name
                        .' · Verify given vs on-time evidence.',
                    route('dashboard', request()->query()).'#review-homework-'.$log->id,
                    'Review homework',
                    'Review',
                    [
                        'teacher' => $log->teacher?->name,
                        'class' => $log->schoolClass?->name,
                        'subject' => $log->subject?->name,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, AttendanceLog>|mixed  $logs
     * @return Collection<int, array<string, mixed>>
     */
    protected function registerReviews(mixed $logs): Collection
    {
        return collect($logs)
            ->filter(fn ($log) => $log instanceof AttendanceLog && $log->isSubmitted())
            ->map(function (AttendanceLog $log) {
                $date = $log->attendance_date instanceof Carbon
                    ? $log->attendance_date->format('d M Y')
                    : (string) $log->attendance_date;

                return $this->item(
                    'review-register-'.$log->id,
                    'register-review',
                    self::URGENCY_REGISTER_REVIEW,
                    'Register · '.$date,
                    ($log->recorder?->name ?? 'Teacher').' · '.$log->schoolClass?->name
                        .' · Verify present vs enrolled.',
                    route('dashboard', request()->query()).'#review-register-'.$log->id,
                    'Review register',
                    'Review',
                    [
                        'teacher' => $log->recorder?->name,
                        'class' => $log->schoolClass?->name,
                        'subject' => null,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|mixed  $sittings
     * @return Collection<int, array<string, mixed>>
     */
    protected function examReviews(mixed $sittings): Collection
    {
        return collect($sittings)
            ->filter(fn ($row) => is_array($row) && ($row['review_status'] ?? null) === 'submitted')
            ->map(function (array $row) {
                /** @var TeacherAssignment $assignment */
                $assignment = $row['assignment'];
                $anchor = $assignment->school_class_id.'-'.$assignment->subject_id;

                return $this->item(
                    'review-exam-'.$anchor,
                    'exam-review',
                    self::URGENCY_EXAM_REVIEW,
                    'Marksheet · '.$assignment->schoolClass?->name.' · '.$assignment->subject?->name,
                    ($assignment->teacher?->name ?? 'Teacher')
                        .' · '.$row['passed'].' passed of '.$row['enrolled'].' enrolled · Verify scores vs the roll.',
                    route('dashboard', request()->query()).'#review-exam-'.$anchor,
                    'Review marksheet',
                    'Review',
                    [
                        'teacher' => $assignment->teacher?->name,
                        'class' => $assignment->schoolClass?->name,
                        'subject' => $assignment->subject?->name,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|mixed  $sittings
     * @return Collection<int, array<string, mixed>>
     */
    protected function examGaps(mixed $sittings): Collection
    {
        return collect($sittings)
            ->filter(function ($row) {
                if (! is_array($row)) {
                    return false;
                }

                $enrolled = (int) ($row['enrolled'] ?? 0);
                $recorded = (int) ($row['recorded'] ?? 0);

                return $enrolled > 0 && $recorded < $enrolled;
            })
            ->map(function (array $row) {
                /** @var TeacherAssignment $assignment */
                $assignment = $row['assignment'];
                $missing = (int) $row['enrolled'] - (int) $row['recorded'];

                return $this->item(
                    'exam-gap-'.$assignment->id,
                    'exam',
                    self::URGENCY_EXAM,
                    'Marksheet incomplete',
                    ($assignment->teacher?->name ?? 'Teacher').' · '.$assignment->schoolClass?->name
                        .' · '.$assignment->subject?->name
                        .' · '.$missing.' of '.$row['enrolled'].' learners without a score. View only — teachers capture scores.',
                    route('exam-results.index', array_filter([
                        'class_id' => $assignment->school_class_id,
                        'subject_id' => $assignment->subject_id,
                    ])),
                    'View marksheet',
                    'Not logged',
                    [
                        'teacher' => $assignment->teacher?->name,
                        'class' => $assignment->schoolClass?->name,
                        'subject' => $assignment->subject?->name,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, AtRiskLearner>|mixed  $records
     * @return Collection<int, array<string, mixed>>
     */
    protected function atRiskPlanGaps(mixed $records): Collection
    {
        return collect($records)
            ->filter(fn ($record) => $record instanceof AtRiskLearner && ! $record->hasActivePlan())
            ->map(function (AtRiskLearner $record) {
                return $this->item(
                    'at-risk-plan-'.$record->id,
                    'at-risk-plan',
                    self::URGENCY_AT_RISK_PLAN,
                    $record->learner?->name ?? 'Learner',
                    ($record->schoolClass?->name ?? 'Class').' · Identified without an active Tier 2/3 plan. AE-07 still uses with-plan ÷ identified.',
                    route('at-risk.plans.create', $record),
                    'Add intervention plan',
                    'No plan',
                    [
                        'teacher' => null,
                        'class' => $record->schoolClass?->name,
                        'subject' => null,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|mixed  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function atRiskFlagGaps(mixed $rows): Collection
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row) && isset($row['learner']))
            ->map(function (array $row) {
                $learner = $row['learner'];

                return $this->item(
                    'at-risk-flag-'.$learner->id,
                    'at-risk-flag',
                    self::URGENCY_AT_RISK_FLAG,
                    $learner->name,
                    ($learner->schoolClass?->name ?? 'Class')
                        .' · Verified score '.number_format((float) $row['lowest'], 0).'% is below pass mark and not on the register.',
                    route('at-risk.index'),
                    'Open caseload',
                    'Not flagged',
                    [
                        'teacher' => null,
                        'class' => $learner->schoolClass?->name,
                        'subject' => null,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, TeacherAssignment>|mixed  $assignments
     * @param  Collection<int, AttendanceLog>|mixed  $logs
     * @return Collection<int, array<string, mixed>>
     */
    protected function registerGaps(mixed $assignments, mixed $logs, int $sessionId): Collection
    {
        $logs = collect($logs);
        $today = now()->toDateString();
        $weekday = now()->isWeekday();

        return collect($assignments)
            ->unique('school_class_id')
            ->values()
            ->map(function (TeacherAssignment $assignment) use ($logs, $today, $weekday, $sessionId) {
                $classId = (int) $assignment->school_class_id;
                $classLogs = $logs->filter(fn ($log) => (int) $log->school_class_id === $classId);
                $hasToday = $classLogs->contains(function ($log) use ($today) {
                    $date = $log->attendance_date instanceof Carbon
                        ? $log->attendance_date->toDateString()
                        : (string) $log->attendance_date;

                    return $date === $today;
                });

                if ($weekday && $hasToday) {
                    return null;
                }

                if (! $weekday && $classLogs->isNotEmpty()) {
                    return null;
                }

                $meta = ! $weekday && $classLogs->isEmpty()
                    ? ($assignment->schoolClass?->name ?? 'Class').' · No register this instructional week. View only — teachers capture the log.'
                    : ($assignment->schoolClass?->name ?? 'Class').' · No register for today. View only — teachers capture the log.';

                return $this->item(
                    'register-gap-'.$classId,
                    'register',
                    self::URGENCY_REGISTER,
                    'Register not taken',
                    ($assignment->teacher?->name ? $assignment->teacher->name.' · ' : '').$meta,
                    route('attendance.index', array_filter([
                        'session_id' => $sessionId ?: null,
                        'class_id' => $classId,
                    ])),
                    'View registers',
                    'Not logged',
                    [
                        'teacher' => $assignment->teacher?->name,
                        'class' => $assignment->schoolClass?->name,
                        'subject' => null,
                    ],
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, TeacherAssignment>|mixed  $assignments
     * @param  Collection<int, HomeworkLog>|mixed  $logs
     * @return Collection<int, array<string, mixed>>
     */
    protected function homeworkGaps(mixed $assignments, mixed $logs, int $sessionId): Collection
    {
        $logs = collect($logs);

        return collect($assignments)->map(function (TeacherAssignment $assignment) use ($logs, $sessionId) {
            $logged = $logs->contains(
                fn (HomeworkLog $log) => (int) $log->school_class_id === (int) $assignment->school_class_id
                    && (int) $log->subject_id === (int) $assignment->subject_id
            );

            if ($logged) {
                return null;
            }

            return $this->item(
                'homework-gap-'.$assignment->id,
                'homework',
                self::URGENCY_HOMEWORK,
                'Homework not logged',
                ($assignment->teacher?->name ?? 'Teacher').' · '.$assignment->schoolClass?->name
                    .' · '.$assignment->subject?->name
                    .' · No homework this instructional week. View only — teachers capture the log.',
                route('homework.index', array_filter([
                    'session_id' => $sessionId ?: null,
                    'teacher_id' => $assignment->teacher_id,
                    'class_id' => $assignment->school_class_id,
                    'subject_id' => $assignment->subject_id,
                ])),
                    'View homework',
                    'Not logged',
                    [
                        'teacher' => $assignment->teacher?->name,
                        'class' => $assignment->schoolClass?->name,
                        'subject' => $assignment->subject?->name,
                    ],
                );
        })->filter()->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(
        string $key,
        string $type,
        int $urgency,
        string $title,
        string $meta,
        ?string $href,
        ?string $cta,
        string $badge,
        array $scope = [],
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'type_label' => self::TYPE_LABELS[$type] ?? $type,
            'urgency' => $urgency,
            'title' => $title,
            'meta' => $meta,
            'href' => $href,
            'cta' => $cta,
            'badge' => $badge,
            'blocked' => false,
            'teacher' => $scope['teacher'] ?? null,
            'class' => $scope['class'] ?? null,
            'subject' => $scope['subject'] ?? null,
        ];
    }
}
