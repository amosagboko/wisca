<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\ReadingAssessment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\ReadingGrowthThreshold;
use Illuminate\Support\Collection;

class ReadingCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-08')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $kpi);
        if ($summary['assessed'] === 0) {
            return null;
        }

        $rate = $summary['rate'];
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id'              => $kpi->id,
                'academic_session_id' => $session->id,
                'term_id'             => $term->id,
                'school_class_id'     => null,
                'subject_id'          => null,
            ],
            [
                'target_value'    => $kpi->default_target,
                'actual_value'    => $rate,
                'achievement_rate'=> $achievement,
                'status'          => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start'    => $term->start_date->toDateString(),
                'period_end'      => $term->end_date->toDateString(),
                'metadata'        => [
                    'achieved'    => $summary['achieved'],
                    'assessed'    => $summary['assessed'],
                    'growth_min'  => $summary['growth_min'],
                ],
            ]
        );
    }

    /**
     * Summary scoped to a session (and optionally a specific term).
     */
    public function sessionSummary(AcademicSession $session, ?Kpi $kpi = null, ?Term $term = null): array
    {
        $kpi ??= Kpi::where('code', 'AE-08')->first();
        $growthMin = ReadingGrowthThreshold::min($kpi);

        $complete = ReadingAssessment::where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->whereNotNull('baseline_level')
            ->whereNotNull('followup_level')
            ->get();

        $assessed  = $complete->count();
        $achieved  = $complete->filter(fn ($a) => ($a->followup_level - $a->baseline_level) >= $growthMin)->count();

        return [
            'assessed'   => $assessed,
            'achieved'   => $achieved,
            'rate'       => $assessed > 0 ? round($achieved / $assessed, 4) : 0.0,
            'growth_min' => $growthMin,
        ];
    }

    /**
     * Per-class breakdown, optionally filtered by term.
     *
     * @return Collection<int, array{class: SchoolClass, assessed: int, achieved: int, rate: float, rows: Collection}>
     */
    public function classSummaries(AcademicSession $session, array $classIds, ?Kpi $kpi = null, ?Term $term = null): Collection
    {
        $kpi ??= Kpi::where('code', 'AE-08')->first();
        $growthMin = ReadingGrowthThreshold::min($kpi);

        $assessments = ReadingAssessment::where('academic_session_id', $session->id)
            ->whereIn('school_class_id', $classIds)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->with(['learner', 'schoolClass'])
            ->get()
            ->groupBy('school_class_id');

        $classes = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get()->keyBy('id');

        return collect($classIds)->map(function (int $id) use ($assessments, $classes, $growthMin) {
            $rows = $assessments->get($id, collect());
            $complete = $rows->filter(fn ($a) => $a->baseline_level !== null && $a->followup_level !== null);
            $assessed = $complete->count();
            $achieved = $complete->filter(fn ($a) => ($a->followup_level - $a->baseline_level) >= $growthMin)->count();

            return [
                'class'    => $classes->get($id),
                'assessed' => $assessed,
                'achieved' => $achieved,
                'rate'     => $assessed > 0 ? round($achieved / $assessed, 4) : 0.0,
                'rows'     => $rows,
            ];
        })->filter(fn ($item) => $item['class'] !== null)->values();
    }

    /**
     * Learners in $classId with no reading assessment record yet this session.
     */
    public function unassessedLearners(AcademicSession $session, int $classId): Collection
    {
        $assessed = ReadingAssessment::where('academic_session_id', $session->id)
            ->where('school_class_id', $classId)
            ->pluck('learner_id');

        return Learner::where('school_class_id', $classId)
            ->whereNotIn('id', $assessed)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();
    }
}
