<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Observation;
use App\Models\Term;
use App\Support\ObservationRubric;

class ObservationCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-06')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $observations = Observation::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->whereIn('status', ['completed', 'follow_up_required'])
            ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $session->school_id))
            ->get();

        $total = $observations->count();
        if ($total === 0) {
            return null;
        }

        $passMin = ObservationRubric::passMin($kpi);
        $effective = $observations->filter(fn (Observation $row) => ObservationRubric::isEffective($row->overall_score, $kpi))->count();
        $rate = round($effective / $total, 4);
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
                'period_start' => $term->start_date->toDateString(),
                'period_end' => $term->end_date->toDateString(),
                'metadata' => [
                    'observed' => $total,
                    'effective' => $effective,
                    'pass_min' => $passMin,
                ],
            ]
        );
    }

    public function rateForObservations($observations): array
    {
        $observed = $observations->filter(fn (Observation $row) => $row->isCompleted())->count();
        $effective = $observations->filter(fn (Observation $row) => $row->isEffective())->count();
        $rate = $observed > 0 ? round($effective / $observed, 4) : 0.0;

        return compact('observed', 'effective', 'rate');
    }
}
