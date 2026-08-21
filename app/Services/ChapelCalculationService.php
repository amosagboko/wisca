<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ChapelAttendance;
use App\Models\ChapelSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\Term;
use Illuminate\Support\Collection;

class ChapelCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * CE-01: present + participating ÷ school roll.
     * Recalculates for the given session (and optional term) and persists a KpiPeriodicData row.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'CE-01')->first();
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

        $rate        = $summary['rate'];
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
                'target_value'     => $kpi->default_target,
                'actual_value'     => $rate,
                'achievement_rate' => $achievement,
                'status'           => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start'     => $term->start_date->toDateString(),
                'period_end'       => $term->end_date->toDateString(),
                'metadata'         => [
                    'sessions_held'  => $summary['sessions_held'],
                    'participating'  => $summary['participating'],
                    'roll'           => $summary['roll'],
                ],
            ]
        );
    }

    /**
     * Aggregate CE-01 figures for a session (+ optional term filter).
     *
     * Returns:
     *   sessions_held   – count of held sessions with a roll taken
     *   roll            – distinct enrolled learners expected (school roll)
     *   participating   – total learner-session slots that count for CE-01
     *   rate            – participating / (roll × sessions_held)
     */
    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $heldSessions = ChapelSession::where('academic_session_id', $session->id)
            ->where('status', 'held')
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->with(['activityType', 'attendances'])
            ->get();

        $sessionsHeld = $heldSessions->count();

        $roll = Learner::where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->count();

        if ($sessionsHeld === 0 || $roll === 0) {
            return [
                'sessions_held' => $sessionsHeld,
                'roll'          => $roll,
                'participating' => 0,
                'rate'          => 0.0,
            ];
        }

        // Denominator: roll × sessions held (each learner expected at every session)
        $denominator = $roll * $sessionsHeld;

        // Numerator: attendance rows that count as "present + participating"
        $participating = 0;
        foreach ($heldSessions as $cs) {
            $levels = $cs->activityType?->countingLevels() ?? ['active', 'leading'];
            $participating += $cs->attendances
                ->where('status', '!=', 'absent')
                ->whereIn('participation_level', $levels)
                ->count();
        }

        return [
            'sessions_held' => $sessionsHeld,
            'roll'          => $roll,
            'participating' => $participating,
            'rate'          => round($participating / $denominator, 4),
        ];
    }

    /**
     * Per-session detail for the index page.
     */
    public function sessionRows(AcademicSession $session, ?Term $term = null, ?int $typeId = null): Collection
    {
        return ChapelSession::where('academic_session_id', $session->id)
            ->when($term,   fn ($q) => $q->where('term_id', $term->id))
            ->when($typeId, fn ($q) => $q->where('chapel_activity_type_id', $typeId))
            ->with(['activityType', 'leader', 'attendances'])
            ->orderByDesc('session_date')
            ->get()
            ->map(function (ChapelSession $cs) {
                $roll  = $cs->attendances->count();
                $levels = $cs->activityType?->countingLevels() ?? ['active', 'leading'];
                $participating = $cs->attendances
                    ->where('status', '!=', 'absent')
                    ->whereIn('participation_level', $levels)
                    ->count();

                return [
                    'session'       => $cs,
                    'roll_taken'    => $roll > 0,
                    'roll'          => $roll,
                    'participating' => $participating,
                    'rate'          => $roll > 0 ? round($participating / $roll, 4) : null,
                ];
            });
    }
}
