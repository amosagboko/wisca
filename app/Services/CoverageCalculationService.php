<?php

namespace App\Services;

use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\SchemeOfWork;

class CoverageCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForScheme(SchemeOfWork $scheme): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-01')->first();
        if (! $kpi) {
            return null;
        }

        $rate = $scheme->coverageRate();
        $totalTopics = $scheme->topics()->count();
        $coveredTopics = $scheme->topics()->where('status', 'covered')->count();
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);

        $row = KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'measure_key' => null,
                'academic_session_id' => $scheme->academic_session_id,
                'term_id' => $scheme->term_id,
                'school_class_id' => $scheme->school_class_id,
                'subject_id' => $scheme->subject_id,
            ],
            [
                'target_value' => $kpi->default_target,
                'actual_value' => $rate,
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'metadata' => [
                    'total_topics' => $totalTopics,
                    'covered_topics' => $coveredTopics,
                    'scheme_id' => $scheme->id,
                ],
            ]
        );

        $this->recalculateSchoolWide($kpi, (int) $scheme->academic_session_id, (int) $scheme->term_id);

        return $row;
    }

    /**
     * School-wide AE-01: unweighted average of scheme coverage rates for the term.
     */
    public function recalculateSchoolWide(Kpi $kpi, int $sessionId, int $termId): ?KpiPeriodicData
    {
        $schemes = SchemeOfWork::where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->whereIn('status', ['active', 'approved'])
            ->withCount([
                'topics',
                'topics as covered_topics_count' => fn ($q) => $q->where('status', 'covered'),
            ])
            ->get();

        if ($schemes->isEmpty()) {
            return null;
        }

        $rates = $schemes->map(function (SchemeOfWork $scheme) {
            if ($scheme->topics_count === 0) {
                return null;
            }

            return round($scheme->covered_topics_count / $scheme->topics_count, 4);
        })->filter(fn ($v) => $v !== null);

        if ($rates->isEmpty()) {
            return null;
        }

        $rate = round((float) $rates->avg(), 4);
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'measure_key' => null,
                'academic_session_id' => $sessionId,
                'term_id' => $termId,
                'school_class_id' => null,
                'subject_id' => null,
            ],
            [
                'target_value' => $kpi->default_target,
                'actual_value' => $rate,
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'metadata' => [
                    'schemes' => $schemes->count(),
                    'total_topics' => (int) $schemes->sum('topics_count'),
                    'covered_topics' => (int) $schemes->sum('covered_topics_count'),
                ],
            ]
        );
    }
}
