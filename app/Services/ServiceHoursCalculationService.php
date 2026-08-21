<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\ServiceActivityType;
use App\Models\ServiceLog;
use App\Models\Term;
use Illuminate\Support\Collection;

class ServiceHoursCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * CE-03: total verified service hours ÷ student roll.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'CE-03')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['roll'] === 0) {
            return null;
        }

        $actual = $summary['hours_per_learner'];
        $achievement = $this->evaluator->achievementRate($actual, (float) $kpi->default_target);

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
                'actual_value' => $actual,
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start' => $term->start_date->toDateString(),
                'period_end' => $term->end_date->toDateString(),
                'metadata' => [
                    'verified_hours' => $summary['verified_hours'],
                    'verified_logs' => $summary['verified_logs'],
                    'student_roll' => $summary['roll'],
                ],
            ]
        );
    }

    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $logs = ServiceLog::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'verified')
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->get();

        $roll = Learner::where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->count();

        $verifiedHours = (float) $logs->sum('verified_hours');

        return [
            'verified_hours' => round($verifiedHours, 2),
            'verified_logs' => $logs->count(),
            'roll' => $roll,
            'hours_per_learner' => $roll > 0 ? round($verifiedHours / $roll, 2) : 0.0,
        ];
    }

    public function logRows(
        AcademicSession $session,
        ?Term $term = null,
        ?int $typeId = null,
        string $status = '',
    ): Collection {
        return ServiceLog::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when($typeId, fn ($q) => $q->where('service_activity_type_id', $typeId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->with(['activityType', 'submitter', 'verifier', 'term'])
            ->orderByDesc('service_date')
            ->orderByDesc('created_at')
            ->get();
    }

    public function activityTypeTotals(AcademicSession $session, ?Term $term = null): Collection
    {
        $types = ServiceActivityType::where('school_id', $session->school_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return $types->map(function (ServiceActivityType $type) use ($session, $term) {
            $logs = ServiceLog::query()
                ->where('school_id', $session->school_id)
                ->where('academic_session_id', $session->id)
                ->where('service_activity_type_id', $type->id)
                ->where('status', 'verified')
                ->when($term, fn ($q) => $q->where('term_id', $term->id))
                ->get();

            return [
                'type' => $type,
                'verified_logs' => $logs->count(),
                'verified_hours' => round((float) $logs->sum('verified_hours'), 2),
                'participants' => (int) $logs->sum('participant_count'),
            ];
        })->filter(fn ($row) => $row['verified_logs'] > 0)->values();
    }
}
