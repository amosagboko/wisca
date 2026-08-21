<?php

namespace App\Services;

use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Pillar;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function executiveSummary(int $schoolId, int $sessionId): array
    {
        $this->evaluator->forSchool($schoolId);

        $pillars = Pillar::where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('display_order')
            ->with([
                'school',
                'kpis' => fn ($q) => $q->where('status', 'active')->orderBy('display_order'),
            ])
            ->get();

        $pillarSummaries = $pillars->map(fn (Pillar $pillar) => $this->pillarSummary($pillar, $sessionId));

        $allAchievements = $pillarSummaries->flatMap(fn ($p) => $p['achievements'])->filter(fn ($v) => $v !== null);

        $schoolAverage = $allAchievements->isNotEmpty()
            ? round($allAchievements->avg(), 4)
            : 0;

        return [
            'school_name' => $pillars->first()?->school?->name ?? 'WISCA',
            'pillars' => $pillarSummaries,
            'totals' => [
                'total_kpis' => $pillarSummaries->sum('total_kpis'),
                'reported_kpis' => $pillarSummaries->sum('reported_kpis'),
                'on_track' => $pillarSummaries->sum('on_track'),
                'needs_attention' => $pillarSummaries->sum('needs_attention'),
                'off_track' => $pillarSummaries->sum('off_track'),
                'avg_achievement' => $schoolAverage,
                'overall_health' => $this->evaluator->overallHealth($schoolAverage),
            ],
        ];
    }

    public function pillarSummary(Pillar $pillar, int $sessionId): array
    {
        $this->evaluator->forSchool($pillar->school_id);

        $rowsByKpi = KpiPeriodicData::whereIn('kpi_id', $pillar->kpis->pluck('id'))
            ->where('academic_session_id', $sessionId)
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('kpi_id');

        $resolved = $pillar->kpis->mapWithKeys(function (Kpi $kpi) use ($rowsByKpi) {
            return [$kpi->id => $this->resolveExecutiveRow($rowsByKpi->get($kpi->id, collect()))];
        });

        $achievements = $pillar->kpis->map(function (Kpi $kpi) use ($resolved) {
            return $resolved->get($kpi->id)?->achievement_rate;
        })->filter(fn ($v) => $v !== null);

        $avg = $achievements->isNotEmpty() ? round($achievements->avg(), 4) : 0;

        $statusCounts = $resolved->filter()->countBy('status');

        return [
            'pillar' => $pillar,
            'total_kpis' => $pillar->kpis->count(),
            'reported_kpis' => $resolved->filter()->count(),
            'on_track' => $statusCounts->get('ON TRACK', 0),
            'needs_attention' => $statusCounts->get('NEEDS ATTENTION', 0),
            'off_track' => $statusCounts->get('OFF TRACK', 0),
            'avg_achievement' => $avg,
            'headline_status' => $this->evaluator->pillarHeadline($avg),
            'overall_health' => $this->evaluator->overallHealth($avg),
            'achievements' => $achievements,
            'kpis' => $pillar->kpis->map(function (Kpi $kpi) use ($resolved) {
                return [
                    'kpi' => $kpi,
                    'data' => $resolved->get($kpi->id),
                ];
            }),
        ];
    }

    /**
     * Board rollup: prefer school-wide row (null class/subject); else unweighted
     * average of the latest row per class/subject scope (Excel AVERAGE of members).
     */
    protected function resolveExecutiveRow(Collection $rows): ?KpiPeriodicData
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $schoolWide = $rows
            ->filter(fn (KpiPeriodicData $row) => $row->school_class_id === null && $row->subject_id === null)
            ->sortByDesc('updated_at')
            ->first();

        $scoped = $rows->filter(fn (KpiPeriodicData $row) => $row->school_class_id !== null || $row->subject_id !== null);

        if ($schoolWide && ($scoped->isEmpty() || $schoolWide->updated_at >= $scoped->max('updated_at'))) {
            return $schoolWide;
        }

        if ($scoped->isEmpty()) {
            return $schoolWide;
        }

        $latestPerScope = $scoped
            ->sortByDesc('updated_at')
            ->unique(fn (KpiPeriodicData $row) => ($row->school_class_id ?? 'n').':'.($row->subject_id ?? 'n'))
            ->values();

        if ($latestPerScope->count() === 1) {
            return $latestPerScope->first();
        }

        $avgAchievement = round((float) $latestPerScope->avg('achievement_rate'), 4);
        $avgActual = round((float) $latestPerScope->avg('actual_value'), 4);
        $target = (float) ($latestPerScope->first()->target_value ?? 0);

        $rollup = $latestPerScope->first()->replicate();
        $rollup->school_class_id = null;
        $rollup->subject_id = null;
        $rollup->actual_value = $avgActual;
        $rollup->target_value = $target;
        $rollup->achievement_rate = $avgAchievement;
        $rollup->status = $this->evaluator->kpiStatus($avgAchievement);
        $rollup->metadata = array_merge($rollup->metadata ?? [], [
            'executive_rollup' => true,
            'scoped_rows' => $latestPerScope->count(),
        ]);

        return $rollup;
    }
}
