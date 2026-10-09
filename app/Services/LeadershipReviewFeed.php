<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\LeadershipWeekReview;
use App\Models\LessonPlan;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\TopicCoverageLog;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadershipReviewFeed
{
    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'hod-review' => 'Waiting on HOD',
        'overdue' => 'Overdue HOD reviews',
        'at-risk' => 'Intervention gaps',
        'catch-up' => 'Catch-up gaps',
    ];

    public function __construct(
        protected HomeworkCalculationService $homework,
        protected AtRiskCalculationService $atRisk,
        protected ExamCalculationService $exams,
        protected TopicCatchUpService $catchUps,
    ) {}

    /**
     * @return array{
     *     items: Collection<int, array<string, mixed>>,
     *     counts: array<string, int>,
     *     week_number: int,
     *     review: ?LeadershipWeekReview
     * }
     */
    public function compose(User $leader, AcademicSession $session, Term $term): array
    {
        $schoolId = (int) $leader->school_id;
        $window = $this->homework->weekWindow($session, $term);
        $weekNumber = (int) $window['week_number'];

        $plans = $this->pendingPlanCount($schoolId, $session, $term);
        $planSla = $this->overduePlanSlaCount($schoolId, $session, $term);
        $coverage = $this->pendingCoverageCount($schoolId, $session, $term);
        $homework = $this->pendingHomeworkCount($session, $term, $window);
        $registers = $this->pendingRegisterCount($session, $term, $window);
        $exams = $this->pendingExamCount($session, $term);
        $withoutPlan = (int) ($this->atRisk->monthSummary($session, $term, null, false)['without_plan'] ?? 0);
        $catchUp = $this->pendingCatchUpCount($session, $term);

        $counts = [
            'plans' => $plans,
            'plan_sla_overdue' => $planSla,
            'coverage' => $coverage,
            'homework' => $homework,
            'registers' => $registers,
            'exams' => $exams,
            'at_risk_without_plan' => $withoutPlan,
            'catch_up_needed' => $catchUp,
        ];

        $items = collect([
            $this->countItem(
                'lead-plans',
                'hod-review',
                'Lesson plans awaiting HOD',
                $plans.' submitted plan'.($plans === 1 ? '' : 's').' still need department approval.',
                route('lesson-plans.index', ['session_id' => $session->id, 'term_id' => $term->id, 'status' => 'submitted']),
                'Open plans',
                $plans,
            ),
            $this->countItem(
                'lead-plan-sla',
                'overdue',
                'Lesson plans past 24-hour SLA',
                $planSla.' submitted plan'.($planSla === 1 ? '' : 's').' have waited more than 24 hours for HOD approve or return. Executive AE-05 still uses teacher on-time vs the planning-policy due day.',
                route('lesson-plans.index', ['session_id' => $session->id, 'term_id' => $term->id, 'status' => 'submitted']),
                'Open overdue plans',
                $planSla,
                'Overdue',
            ),
            $this->countItem(
                'lead-coverage',
                'hod-review',
                'Coverage awaiting HOD',
                $coverage.' delivery log'.($coverage === 1 ? '' : 's').' still need verification.',
                route('coverage-logs.index', ['session_id' => $session->id]),
                'Open coverage',
                $coverage,
            ),
            $this->countItem(
                'lead-homework',
                'hod-review',
                'Homework awaiting HOD',
                $homework.' homework log'.($homework === 1 ? '' : 's').' this instructional week still need verification.',
                route('homework.index', ['session_id' => $session->id]),
                'Open homework',
                $homework,
            ),
            $this->countItem(
                'lead-registers',
                'hod-review',
                'Registers awaiting HOD',
                $registers.' register'.($registers === 1 ? '' : 's').' this instructional week still need verification.',
                route('attendance.index', ['session_id' => $session->id]),
                'Open registers',
                $registers,
            ),
            $this->countItem(
                'lead-exams',
                'hod-review',
                'Marksheets awaiting HOD',
                $exams.' complete sitting'.($exams === 1 ? '' : 's').' still need verification.',
                route('exam-results.index'),
                'Open marksheets',
                $exams,
            ),
            $this->countItem(
                'lead-iip',
                'at-risk',
                'Identified learners without a plan',
                $withoutPlan.' at-risk learner'.($withoutPlan === 1 ? '' : 's').' still need an active Tier 2/3 plan. AE-07 is unchanged until HOD writes the IIP.',
                route('at-risk.index', ['session_id' => $session->id, 'plan' => 'without_plan']),
                'Open caseload',
                $withoutPlan,
                'No plan',
            ),
            $this->countItem(
                'lead-catch-up',
                'catch-up',
                'Behind topics without catch-up',
                $catchUp.' Active SoW topic'.($catchUp === 1 ? '' : 's').' are behind the instructional week without HOD-identified catch-up. AE-01.4 stays 0 until HOD opens catch-up and later verifies delivery.',
                route('curriculum-coverage.report', ['session_id' => $session->id]),
                'Open coverage report',
                $catchUp,
                'Catch-up',
            ),
        ])->filter()->values();

        $review = LeadershipWeekReview::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('week_number', $weekNumber)
            ->with('reviewer')
            ->first();

        return [
            'items' => $items,
            'counts' => $counts,
            'week_number' => $weekNumber,
            'review' => $review,
        ];
    }

    protected function pendingPlanCount(int $schoolId, AcademicSession $session, Term $term): int
    {
        return LessonPlan::query()
            ->where('status', 'submitted')
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $schoolId))
            ->whereHas('topic.schemeOfWork', fn ($q) => $q
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id))
            ->count();
    }

    protected function overduePlanSlaCount(int $schoolId, AcademicSession $session, Term $term): int
    {
        return LessonPlan::query()
            ->where('status', 'submitted')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', now()->subDay())
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $schoolId))
            ->whereHas('topic.schemeOfWork', fn ($q) => $q
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id))
            ->count();
    }

    protected function pendingCoverageCount(int $schoolId, AcademicSession $session, Term $term): int
    {
        return TopicCoverageLog::query()
            ->where('status', 'submitted')
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $schoolId))
            ->whereHas('topic.schemeOfWork', fn ($q) => $q
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id))
            ->count();
    }

    /**
     * @param  array{start: \Carbon\Carbon, end: \Carbon\Carbon}  $window
     */
    protected function pendingHomeworkCount(AcademicSession $session, Term $term, array $window): int
    {
        return HomeworkLog::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', CaptureLogReview::STATUS_SUBMITTED)
            ->whereBetween('given_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $session->school_id))
            ->count();
    }

    /**
     * @param  array{start: \Carbon\Carbon, end: \Carbon\Carbon}  $window
     */
    protected function pendingRegisterCount(AcademicSession $session, Term $term, array $window): int
    {
        return AttendanceLog::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', CaptureLogReview::STATUS_SUBMITTED)
            ->whereBetween('attendance_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $session->school_id))
            ->count();
    }

    protected function pendingExamCount(AcademicSession $session, Term $term): int
    {
        $assignments = TeacherAssignment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $session->school_id))
            ->with(['teacher', 'schoolClass', 'subject'])
            ->get();

        return $this->exams->sittingOverviews($session, $term, $assignments)
            ->filter(fn (array $row) => ($row['review_status'] ?? null) === 'submitted')
            ->count();
    }

    protected function pendingCatchUpCount(AcademicSession $session, Term $term): int
    {
        return $this->catchUps->neededQuery($session, $term, $this->catchUps->behindWeek($term))->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function countItem(
        string $key,
        string $type,
        string $title,
        string $meta,
        string $href,
        string $cta,
        int $count,
        string $badge = 'Waiting on HOD',
    ): ?array {
        if ($count <= 0) {
            return null;
        }

        return [
            'key' => $key,
            'type' => $type,
            'type_label' => self::TYPE_LABELS[$type] ?? $type,
            'urgency' => $type === 'overdue' ? 5 : 10,
            'title' => $title,
            'meta' => $meta,
            'href' => $href,
            'cta' => $cta,
            'badge' => $badge,
            'blocked' => false,
            'teacher' => null,
            'class' => null,
            'subject' => null,
        ];
    }
}
