<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\ExamResult;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\Term;
use App\Support\AtRiskCriteria;
use App\Support\ExamPassMark;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AtRiskCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-07')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->monthSummary($session, $term);
        if ($summary['identified'] === 0) {
            return null;
        }

        $rate = $summary['rate'];
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);
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
                'actual_value' => $rate,
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start' => $window['start']->toDateString(),
                'period_end' => $window['end']->toDateString(),
                'metadata' => [
                    'identified' => $summary['identified'],
                    'with_plan' => $summary['with_plan'],
                    'without_plan' => $summary['without_plan'],
                ],
            ]
        );
    }

    public function monthSummary(AcademicSession $session, Term $term): array
    {
        $records = $this->openRecords($session, $term);
        $identified = $records->count();
        $withPlan = $records->filter(fn (AtRiskLearner $row) => $row->hasActivePlan())->count();

        return [
            'identified' => $identified,
            'with_plan' => $withPlan,
            'without_plan' => max(0, $identified - $withPlan),
            'rate' => $identified > 0 ? round($withPlan / $identified, 4) : 0.0,
            'records' => $records,
        ];
    }

    public function openRecords(AcademicSession $session, ?Term $term = null): Collection
    {
        return AtRiskLearner::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereHas('learner', fn ($query) => $query->where('school_id', $session->school_id))
            ->with(['learner', 'schoolClass', 'identifier', 'plans.coordinator'])
            ->latest('identification_date')
            ->get();
    }

    public function belowPassUnflagged(AcademicSession $session, Term $term, ?array $classIds = null): Collection
    {
        $passMark = ExamPassMark::percent();
        $flaggedIds = AtRiskLearner::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->pluck('learner_id');

        $results = ExamResult::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', ExamCalculationService::ASSESSMENT_KEY)
            ->where('score', '<', $passMark)
            ->when($classIds !== null, fn ($query) => $query->whereIn('school_class_id', $classIds ?: [0]))
            ->whereNotIn('learner_id', $flaggedIds->all() ?: [0])
            ->whereHas('learner', fn ($query) => $query
                ->where('school_id', $session->school_id)
                ->where('status', 'enrolled'))
            ->with(['learner.schoolClass', 'subject'])
            ->orderBy('score')
            ->get();

        return $results->groupBy('learner_id')->map(function (Collection $rows) use ($passMark) {
            $first = $rows->first();

            return [
                'learner' => $first->learner,
                'pass_mark' => $passMark,
                'sittings' => $rows->map(fn (ExamResult $row) => [
                    'subject' => $row->subject?->name,
                    'score' => $row->score,
                ])->values(),
                'lowest' => (float) $rows->min('score'),
            ];
        })->values();
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

    public function eligibleLearners(AcademicSession $session, ?array $classIds = null): Collection
    {
        $flaggedIds = AtRiskLearner::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->pluck('learner_id');

        return Learner::query()
            ->where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->when($classIds !== null, fn ($query) => $query->whereIn('school_class_id', $classIds ?: [0]))
            ->whereNotIn('id', $flaggedIds->all() ?: [0])
            ->with('schoolClass')
            ->orderBy('name')
            ->get();
    }
}
