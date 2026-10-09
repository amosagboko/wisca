<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\LessonPlan;
use App\Models\SchemeOfWork;
use App\Models\School;
use App\Models\Term;
use App\Models\Topic;
use Carbon\Carbon;

class LessonPlanCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
        protected AcademicReportingPeriod $periods,
        protected PlanningPolicy $policy,
    ) {}

    public function dueAtForTopic(Topic $topic): Carbon
    {
        $topic->loadMissing('schemeOfWork.term.academicSession.school');

        $term = $topic->schemeOfWork?->term;

        if (! $term) {
            return now()->endOfDay();
        }

        return $this->dueAtForWeek(
            $term,
            (int) $topic->week_number,
            $topic->schemeOfWork?->academicSession?->school,
        );
    }

    public function dueAtForWeek(Term $term, int $weekNumber, ?School $school = null): Carbon
    {
        $term->loadMissing('academicSession.school');
        $school ??= $term->academicSession?->school;

        return $this->periods->dueAtInWeek(
            $term,
            $weekNumber,
            $this->policy->lessonPlanDueWeekday($school),
        );
    }

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-05')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $schemes = SchemeOfWork::where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->whereIn('status', ['active', 'approved'])
            ->with(['topics.lessonPlans'])
            ->get();

        $topics = $schemes->flatMap->topics;
        $maxWeek = max(1, (int) $topics->max('week_number'));
        $weekNumber = $term->schemeWeekNumber($maxWeek);
        $weekTopics = $topics->where('week_number', $weekNumber);

        $required = $weekTopics->count();
        if ($required === 0) {
            return null;
        }

        $approvedOnTime = $weekTopics->filter(function (Topic $topic) {
            return $topic->lessonPlans
                ->where('status', 'approved')
                ->where('on_time', true)
                ->isNotEmpty();
        })->count();

        $rate = round($approvedOnTime / $required, 4);
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);
        $dueAt = $this->dueAtForWeek($term, $weekNumber, $session->school);

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'measure_key' => null,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'school_class_id' => null,
                'subject_id' => null,
            ],
            [
                'target_value' => $kpi->default_target,
                'actual_value' => $rate,
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start' => $term->instructionalWeekStart($weekNumber)->toDateString(),
                'period_end' => $term->instructionalWeekEnd($weekNumber)->toDateString(),
                'metadata' => [
                    'week_number' => $weekNumber,
                    'required' => $required,
                    'approved_on_time' => $approvedOnTime,
                    'approved_total' => $weekTopics->filter(fn (Topic $topic) => $topic->hasApprovedLessonPlan())->count(),
                    'due_weekday' => $this->policy->lessonPlanDueWeekdayName($session->school),
                    'due_at' => $dueAt->toDateTimeString(),
                ],
            ]
        );
    }
}
