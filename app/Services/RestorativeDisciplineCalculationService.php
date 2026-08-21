<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\DisciplineIncident;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RestorativeDisciplineCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * CE-04: incidents with completed restorative agreement ÷ total incidents logged.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'CE-04')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->monthSummary($session, $term);
        if ($summary['logged'] === 0) {
            return null;
        }

        $achievement = $this->evaluator->achievementRate($summary['rate'], (float) $kpi->default_target);
        $window = $this->monthWindow($term);

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
                'period_start' => $window['start']->toDateString(),
                'period_end' => $window['end']->toDateString(),
                'metadata' => [
                    'logged' => $summary['logged'],
                    'completed' => $summary['completed'],
                    'pending' => $summary['pending'],
                ],
            ]
        );
    }

    public function monthSummary(AcademicSession $session, Term $term): array
    {
        $window = $this->monthWindow($term);

        $incidents = DisciplineIncident::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->whereBetween('incident_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->get();

        $logged = $incidents->count();
        $completed = $incidents->filter(fn (DisciplineIncident $i) => $i->restorativeCompleted())->count();

        return [
            'logged' => $logged,
            'completed' => $completed,
            'pending' => max(0, $logged - $completed),
            'rate' => $logged > 0 ? round($completed / $logged, 4) : 0.0,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function monthWindow(Term $term): array
    {
        $today = now()->startOfDay();
        $termStart = $term->start_date->copy()->startOfDay();
        $termEnd = $term->end_date->copy()->startOfDay();

        if ($today->gt($termEnd)) {
            $cursor = $termEnd;
        } elseif ($today->lt($termStart)) {
            $cursor = $termStart;
        } else {
            $cursor = $today;
        }

        $start = $cursor->copy()->startOfMonth();
        $end = $cursor->copy()->endOfMonth();

        if ($start->lt($termStart)) {
            $start = $termStart->copy();
        }
        if ($end->gt($termEnd)) {
            $end = $termEnd->copy()->endOfDay();
        }

        return compact('start', 'end');
    }

    public function incidentRows(
        AcademicSession $session,
        ?Term $term = null,
        ?int $typeId = null,
        string $status = '',
        string $restorativeStatus = '',
    ): Collection {
        return DisciplineIncident::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when($typeId, fn ($q) => $q->where('discipline_incident_type_id', $typeId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($restorativeStatus !== '', fn ($q) => $q->where('restorative_status', $restorativeStatus))
            ->with(['type', 'learner.schoolClass', 'reporter', 'resolver', 'term'])
            ->orderByDesc('incident_date')
            ->orderByDesc('created_at')
            ->get();
    }
}
