<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\DigitalCompetencyArea;
use App\Models\DigitalCompetencyRating;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Collection;

class DigitalCompetencyCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * DI-04: staff demonstrating Level 3+ digital competence ÷ total staff.
     * A staff member counts when rated ≥ passing_level on ALL active areas.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'DI-04')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['staff_total'] === 0) {
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
                    'proficient' => $summary['proficient'],
                    'staff_total' => $summary['staff_total'],
                    'areas_used' => $summary['area_count'],
                ],
            ]
        );
    }

    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $areas = DigitalCompetencyArea::where('school_id', $session->school_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $staff = User::where('school_id', $session->school_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $proficient = 0;
        foreach ($staff as $member) {
            if ($this->staffIsProficient($session, $term, $member->id, $areas)) {
                $proficient++;
            }
        }

        $staffTotal = $staff->count();

        return [
            'area_count' => $areas->count(),
            'staff_total' => $staffTotal,
            'proficient' => $proficient,
            'rate' => $staffTotal > 0 ? round($proficient / $staffTotal, 4) : 0.0,
            'areas' => $areas,
        ];
    }

    public function staffMarksheet(AcademicSession $session, ?Term $term, Collection $areas): Collection
    {
        $staff = User::where('school_id', $session->school_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $ratings = DigitalCompetencyRating::query()
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->whereIn('user_id', $staff->pluck('id'))
            ->whereIn('digital_competency_area_id', $areas->pluck('id'))
            ->get()
            ->groupBy('user_id');

        return $staff->map(function (User $member) use ($ratings, $areas) {
            $memberRatings = $ratings->get($member->id, collect())->keyBy('digital_competency_area_id');
            $domainMap = $areas->mapWithKeys(fn ($area) => [$area->id => $memberRatings->get($area->id)]);
            $allRated = $domainMap->every(fn ($r) => $r !== null);
            $allPassed = $allRated && $domainMap->every(fn ($r) => $r?->isPassing());

            return [
                'staff' => $member,
                'ratings' => $domainMap,
                'all_rated' => $allRated,
                'proficient' => $allPassed,
            ];
        });
    }

    public function activeAreasForSchool(int $schoolId): Collection
    {
        return DigitalCompetencyArea::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    private function staffIsProficient(
        AcademicSession $session,
        ?Term $term,
        int $userId,
        Collection $areas,
    ): bool {
        if ($areas->isEmpty()) {
            return false;
        }

        foreach ($areas as $area) {
            $level = DigitalCompetencyRating::query()
                ->where('user_id', $userId)
                ->where('digital_competency_area_id', $area->id)
                ->where('academic_session_id', $session->id)
                ->when($term, fn ($q) => $q->where('term_id', $term->id))
                ->value('level');

            if ($level === null || $level < $area->passing_level) {
                return false;
            }
        }

        return true;
    }
}
