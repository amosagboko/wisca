<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\ScriptureAssessment;
use App\Models\ScripturePassage;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Support\Collection;

class ScriptureCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * CE-06: students reciting and contextually explaining verses ÷ total assessed.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'CE-06')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['assessed'] === 0) {
            return null;
        }

        $achievement = $this->evaluator->achievementRate($summary['rate'], (float) $kpi->default_target);

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
                'period_start' => $term->start_date->toDateString(),
                'period_end' => $term->end_date->toDateString(),
                'metadata' => [
                    'assessed' => $summary['assessed'],
                    'mastered' => $summary['mastered'],
                    'not_yet' => $summary['not_yet'],
                ],
            ]
        );
    }

    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $rows = ScriptureAssessment::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->get();

        $assessed = $rows->count();
        $mastered = $rows->filter(fn (ScriptureAssessment $row) => $row->countsForKpi())->count();

        return [
            'assessed' => $assessed,
            'mastered' => $mastered,
            'not_yet' => max(0, $assessed - $mastered),
            'rate' => $assessed > 0 ? round($mastered / $assessed, 4) : 0.0,
        ];
    }

    public function classSummaries(AcademicSession $session, ?Term $term): Collection
    {
        return SchoolClass::where('school_id', $session->school_id)
            ->orderBy('name')
            ->get()
            ->map(function (SchoolClass $class) use ($session, $term) {
                $rows = ScriptureAssessment::query()
                    ->where('school_id', $session->school_id)
                    ->where('academic_session_id', $session->id)
                    ->where('school_class_id', $class->id)
                    ->when($term, fn ($q) => $q->where('term_id', $term->id))
                    ->get();

                $assessed = $rows->count();
                $mastered = $rows->filter(fn (ScriptureAssessment $row) => $row->countsForKpi())->count();

                return [
                    'class' => $class,
                    'assessed' => $assessed,
                    'mastered' => $mastered,
                    'rate' => $assessed > 0 ? round($mastered / $assessed, 4) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['assessed'] > 0)
            ->values();
    }

    public function assessmentRows(AcademicSession $session, ?Term $term, ?int $passageId = null): Collection
    {
        return ScriptureAssessment::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when($passageId, fn ($q) => $q->where('scripture_passage_id', $passageId))
            ->with(['passage', 'learner.schoolClass', 'assessor'])
            ->orderByDesc('assessed_on')
            ->orderByDesc('created_at')
            ->get();
    }

    public function activePassagesForSchool(int $schoolId): Collection
    {
        return ScripturePassage::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('reference')
            ->get();
    }
}
