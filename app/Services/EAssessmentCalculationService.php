<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Subject;
use App\Models\SubjectDigitalAssessment;
use App\Models\Term;
use Illuminate\Support\Collection;

class EAssessmentCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * DI-05: subjects utilizing e-assessment / e-portfolio ÷ total subjects offered.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'DI-05')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['subjects_offered'] === 0) {
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
                    'utilizing' => $summary['utilizing'],
                    'subjects_offered' => $summary['subjects_offered'],
                    'e_assessment' => $summary['e_assessment'],
                    'e_portfolio' => $summary['e_portfolio'],
                ],
            ]
        );
    }

    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $subjectsOffered = Subject::where('school_id', $session->school_id)
            ->where('status', 'active')
            ->count();

        $rows = SubjectDigitalAssessment::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->whereHas('subject', fn ($q) => $q->where('status', 'active'))
            ->get();

        $utilizing = $rows->filter(fn (SubjectDigitalAssessment $row) => $row->countsForKpi())->count();
        $eAssessment = $rows->where('uses_e_assessment', true)->count();
        $ePortfolio = $rows->where('uses_e_portfolio', true)->count();

        return [
            'subjects_offered' => $subjectsOffered,
            'utilizing' => $utilizing,
            'e_assessment' => $eAssessment,
            'e_portfolio' => $ePortfolio,
            'rate' => $subjectsOffered > 0 ? round($utilizing / $subjectsOffered, 4) : 0.0,
        ];
    }

    public function subjectRows(AcademicSession $session, ?Term $term = null): Collection
    {
        $subjects = Subject::where('school_id', $session->school_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $existing = SubjectDigitalAssessment::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->get()
            ->keyBy('subject_id');

        return $subjects->map(fn (Subject $subject) => [
            'subject' => $subject,
            'record' => $existing->get($subject->id),
        ]);
    }
}
