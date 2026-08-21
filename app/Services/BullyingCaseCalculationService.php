<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\BullyingCase;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BullyingCaseCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * CE-05: bullying cases closed with safety plan ÷ total reported.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'CE-05')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->monthSummary($session, $term);
        if ($summary['reported'] === 0) {
            return null;
        }

        $achievement = $this->evaluator->achievementRate($summary['rate'], (float) $kpi->default_target);
        $window = $this->monthWindow($term);

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
                'actual_value' => $summary['rate'],
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start' => $window['start']->toDateString(),
                'period_end' => $window['end']->toDateString(),
                'metadata' => [
                    'reported' => $summary['reported'],
                    'closed_with_plan' => $summary['closed_with_plan'],
                    'open_or_incomplete' => $summary['open_or_incomplete'],
                ],
            ]
        );
    }

    public function monthSummary(AcademicSession $session, Term $term): array
    {
        $window = $this->monthWindow($term);

        $cases = BullyingCase::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->whereBetween('reported_on', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->get();

        $reported = $cases->count();
        $closedWithPlan = $cases->filter(fn (BullyingCase $case) => $case->countsForKpi())->count();

        return [
            'reported' => $reported,
            'closed_with_plan' => $closedWithPlan,
            'open_or_incomplete' => max(0, $reported - $closedWithPlan),
            'rate' => $reported > 0 ? round($closedWithPlan / $reported, 4) : 0.0,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function monthWindow(Term $term): array
    {
        $today = now()->startOfDay();
        $termStart = $term->start_date->copy()->startOfDay();
        $termEnd = $term->end_date->copy()->startOfDay();

        if ($today->gt($termEnd)) {
            $cursor = $termEnd;
        } elseif ($today->lt($termStart)) {
            $cursor = $termStart;
        } else {
            $cursor = $today;
        }

        $start = $cursor->copy()->startOfMonth();
        $end = $cursor->copy()->endOfMonth();

        if ($start->lt($termStart)) {
            $start = $termStart->copy();
        }
        if ($end->gt($termEnd)) {
            $end = $termEnd->copy()->endOfDay();
        }

        return compact('start', 'end');
    }

    public function caseRows(
        AcademicSession $session,
        ?Term $term = null,
        ?int $typeId = null,
        string $status = '',
        string $safety = '',
    ): Collection {
        return BullyingCase::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when($typeId, fn ($q) => $q->where('bullying_case_type_id', $typeId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($safety !== '', fn ($q) => $q->where('safety_plan_created', $safety === 'with_plan'))
            ->with(['type', 'targetLearner.schoolClass', 'reportingLearner.schoolClass', 'reporter', 'closer', 'term'])
            ->orderByDesc('reported_on')
            ->orderByDesc('created_at')
            ->get();
    }
}
