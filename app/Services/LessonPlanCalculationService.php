<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\LessonPlan;
use App\Models\SchemeOfWork;
use App\Models\Term;
use App\Models\Topic;
use Carbon\Carbon;

class LessonPlanCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function dueAtForTopic(Topic $topic): Carbon
    {
        $topic->loadMissing('schemeOfWork.term');

        $term = $topic->schemeOfWork?->term;

        if (! $term) {
            return now()->startOfWeek(Carbon::MONDAY);
        }

        return $term->lessonPlanDueAt($topic->week_number);
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

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
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
                'period_start' => $term->lessonPlanDueAt($weekNumber)->toDateString(),
                'period_end' => $term->lessonPlanDueAt($weekNumber)->copy()->addDays(6)->toDateString(),
                'metadata' => [
                    'week_number' => $weekNumber,
                    'required' => $required,
                    'approved_on_time' => $approvedOnTime,
                    'approved_total' => $weekTopics->filter(fn (Topic $topic) => $topic->hasApprovedLessonPlan())->count(),
                ],
            ]
        );
    }
}
