<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\StemProjectCompletion;
use App\Models\StemProjectType;
use App\Models\Term;
use Illuminate\Support\Collection;

class StemProjectCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * DI-02: students completing approved STEM project ÷ total enrolled.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'DI-02')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['enrolled'] === 0) {
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
                    'completed' => $summary['completed'],
                    'enrolled' => $summary['enrolled'],
                    'in_progress' => $summary['in_progress'],
                ],
            ]
        );
    }

    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $enrolled = Learner::where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->count();

        $completedLearnerIds = StemProjectCompletion::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->where('status', 'completed')
            ->whereHas('projectType', fn ($q) => $q->where('is_active', true))
            ->distinct()
            ->pluck('learner_id');

        $inProgressLearnerIds = StemProjectCompletion::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->where('status', 'in_progress')
            ->whereNotIn('learner_id', $completedLearnerIds)
            ->distinct()
            ->pluck('learner_id');

        $completed = $completedLearnerIds->count();
        $inProgress = $inProgressLearnerIds->count();

        return [
            'enrolled' => $enrolled,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'rate' => $enrolled > 0 ? round($completed / $enrolled, 4) : 0.0,
        ];
    }

    public function classSummaries(AcademicSession $session, ?Term $term): Collection
    {
        return SchoolClass::where('school_id', $session->school_id)
            ->orderBy('name')
            ->get()
            ->map(function (SchoolClass $class) use ($session, $term) {
                $enrolled = Learner::where('school_class_id', $class->id)
                    ->where('status', 'enrolled')
                    ->count();

                $completed = StemProjectCompletion::query()
                    ->where('school_id', $session->school_id)
                    ->where('academic_session_id', $session->id)
                    ->where('school_class_id', $class->id)
                    ->when($term, fn ($q) => $q->where('term_id', $term->id))
                    ->where('status', 'completed')
                    ->whereHas('projectType', fn ($q) => $q->where('is_active', true))
                    ->distinct()
                    ->count('learner_id');

                return [
                    'class' => $class,
                    'enrolled' => $enrolled,
                    'completed' => $completed,
                    'rate' => $enrolled > 0 ? round($completed / $enrolled, 4) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['enrolled'] > 0)
            ->values();
    }

    public function completionRows(
        AcademicSession $session,
        ?Term $term = null,
        ?int $typeId = null,
        string $status = '',
    ): Collection {
        return StemProjectCompletion::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when($typeId, fn ($q) => $q->where('stem_project_type_id', $typeId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->with(['projectType', 'learner.schoolClass', 'assessor'])
            ->orderByDesc('completed_on')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function activeTypesForSchool(int $schoolId): Collection
    {
        return StemProjectType::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }
}
