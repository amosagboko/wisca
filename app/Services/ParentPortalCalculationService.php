<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\ParentPortalEngagement;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ParentPortalCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * DI-06: unique active parent portal logins ÷ total enrolled families.
     */
    public function recalculateForMonth(
        AcademicSession $session,
        string $monthStartDate,
        ?Term $term = null,
    ): ?KpiPeriodicData {
        $kpi = Kpi::where('code', 'DI-06')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->monthSummary($session, $monthStartDate);
        if ($summary['enrolled_families'] === 0) {
            return null;
        }

        $achievement = $this->evaluator->achievementRate($summary['rate'], (float) $kpi->default_target);
        $start = Carbon::parse($monthStartDate)->startOfMonth();
        $end = $start->copy()->endOfMonth();

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
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'metadata' => [
                    'active_logins' => $summary['active_logins'],
                    'enrolled_families' => $summary['enrolled_families'],
                    'inactive' => $summary['inactive'],
                ],
            ]
        );
    }

    public function monthSummary(AcademicSession $session, string $monthStartDate): array
    {
        $families = $this->enrolledFamiliesQuery($session->school_id)->get();
        $familyIds = $families->pluck('id');

        $activeLogins = ParentPortalEngagement::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->where('month_start_date', $monthStartDate)
            ->whereIn('parent_id', $familyIds)
            ->where('is_active_monthly', true)
            ->distinct()
            ->count('parent_id');

        $enrolledFamilies = $families->count();

        return [
            'active_logins' => $activeLogins,
            'enrolled_families' => $enrolledFamilies,
            'inactive' => max(0, $enrolledFamilies - $activeLogins),
            'rate' => $enrolledFamilies > 0 ? round($activeLogins / $enrolledFamilies, 4) : 0.0,
        ];
    }

    public function familyRows(AcademicSession $session, string $monthStartDate): Collection
    {
        $engagements = ParentPortalEngagement::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->where('month_start_date', $monthStartDate)
            ->get()
            ->keyBy('parent_id');

        return $this->enrolledFamiliesQuery($session->school_id)
            ->with(['learners.schoolClass'])
            ->orderBy('name')
            ->get()
            ->map(fn (Guardian $guardian) => [
                'guardian' => $guardian,
                'engagement' => $engagements->get($guardian->id),
            ]);
    }

    /**
     * Enrolled families = active guardians linked to at least one enrolled learner.
     */
    private function enrolledFamiliesQuery(int $schoolId)
    {
        return Guardian::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereHas('learners', fn ($q) => $q->where('status', 'enrolled'));
    }
}
