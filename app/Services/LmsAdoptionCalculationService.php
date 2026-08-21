<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\LmsUsageLog;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Collection;

class LmsAdoptionCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * DI-01: active weekly LMS users (staff + students) ÷ total staff + students.
     */
    public function recalculateForWeek(AcademicSession $session, string $weekStartDate, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'DI-01')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);

        $summary = $this->weekSummary($session, $weekStartDate);
        if ($summary['total_population'] === 0) {
            return null;
        }

        $achievement = $this->evaluator->achievementRate($summary['rate'], (float) $kpi->default_target);

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'academic_session_id' => $session->id,
                'term_id' => $term?->id,
                'school_class_id' => null,
                'subject_id' => null,
            ],
            [
                'target_value' => $kpi->default_target,
                'actual_value' => $summary['rate'],
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start' => $weekStartDate,
                'period_end' => now()->parse($weekStartDate)->addDays(6)->toDateString(),
                'metadata' => [
                    'active_users' => $summary['active_users'],
                    'total_population' => $summary['total_population'],
                    'active_staff' => $summary['active_staff'],
                    'active_learners' => $summary['active_learners'],
                ],
            ]
        );
    }

    public function weekSummary(AcademicSession $session, string $weekStartDate): array
    {
        $staffTotal = User::query()
            ->where('school_id', $session->school_id)
            ->where('status', 'active')
            ->count();

        $learnerTotal = Learner::query()
            ->where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->count();

        $activeStaff = LmsUsageLog::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->where('week_start_date', $weekStartDate)
            ->where('actor_type', 'staff')
            ->where('is_active_weekly', true)
            ->count();

        $activeLearners = LmsUsageLog::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->where('week_start_date', $weekStartDate)
            ->where('actor_type', 'learner')
            ->where('is_active_weekly', true)
            ->count();

        $activeUsers = $activeStaff + $activeLearners;
        $totalPopulation = $staffTotal + $learnerTotal;

        return [
            'active_users' => $activeUsers,
            'active_staff' => $activeStaff,
            'active_learners' => $activeLearners,
            'staff_total' => $staffTotal,
            'learner_total' => $learnerTotal,
            'total_population' => $totalPopulation,
            'rate' => $totalPopulation > 0 ? round($activeUsers / $totalPopulation, 4) : 0.0,
        ];
    }

    public function weeklyRows(AcademicSession $session, string $weekStartDate): Collection
    {
        return LmsUsageLog::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->where('week_start_date', $weekStartDate)
            ->with(['user', 'learner.schoolClass'])
            ->orderBy('actor_type')
            ->orderByDesc('is_active_weekly')
            ->orderByDesc('activity_count')
            ->get();
    }
}
