<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Term;
use App\Support\ExcelSampleCatalog;
use Illuminate\Support\Collection;

class ExcelParityService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
        protected DashboardService $dashboard,
    ) {}

    /**
     * Write school-wide Excel sample Actuals for Board parity (§11).
     *
     * @return Collection<int, KpiPeriodicData>
     */
    public function applySamples(int $schoolId, int $sessionId, ?int $termId = null): Collection
    {
        $this->evaluator->forSchool($schoolId);
        $termId ??= Term::currentForSession($sessionId)?->id;
        $written = collect();

        foreach (ExcelSampleCatalog::samples() as $code => $sample) {
            $kpi = Kpi::query()
                ->where('code', $code)
                ->whereHas('pillar', fn ($q) => $q->where('school_id', $schoolId))
                ->first();

            if (! $kpi) {
                continue;
            }

            $achievement = $this->evaluator->achievementRate($sample['actual'], $sample['target']);

            $written->push(KpiPeriodicData::updateOrCreate(
                [
                    'kpi_id' => $kpi->id,
                    'academic_session_id' => $sessionId,
                    'term_id' => $termId,
                    'school_class_id' => null,
                    'subject_id' => null,
                ],
                [
                    'target_value' => $sample['target'],
                    'actual_value' => $sample['actual'],
                    'achievement_rate' => $achievement,
                    'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                    'metadata' => [
                        'source' => 'excel_sample',
                        'board_parity' => true,
                    ],
                ]
            ));
        }

        return $written;
    }

    /**
     * @return array{ok: bool, failures: array<int, string>, summary: array, expected: array}
     */
    public function verify(int $schoolId, int $sessionId): array
    {
        $this->evaluator->forSchool($schoolId);
        $summary = $this->dashboard->executiveSummary($schoolId, $sessionId);
        $expected = ExcelSampleCatalog::expectedExecutive();
        $failures = [];

        foreach (ExcelSampleCatalog::samples() as $code => $sample) {
            $kpi = Kpi::query()
                ->where('code', $code)
                ->whereHas('pillar', fn ($q) => $q->where('school_id', $schoolId))
                ->first();

            $row = null;
            foreach ($summary['pillars'] as $pillar) {
                $match = $pillar['kpis']->first(fn ($r) => $r['kpi']->code === $code);
                if ($match) {
                    $row = $match['data'];
                    break;
                }
            }

            $expectedAch = $this->evaluator->achievementRate($sample['actual'], $sample['target']);
            $expectedStatus = $this->evaluator->kpiStatus($expectedAch ?? 0);

            if (! $row) {
                $failures[] = "{$code}: missing periodic data";
                continue;
            }

            if (round((float) $row->achievement_rate, 4) !== round((float) $expectedAch, 4)) {
                $failures[] = "{$code}: achievement {$row->achievement_rate} ≠ {$expectedAch}";
            }

            if ($row->status !== $expectedStatus) {
                $failures[] = "{$code}: status {$row->status} ≠ {$expectedStatus}";
            }
        }

        $totals = $summary['totals'];
        if ((int) $totals['on_track'] !== $expected['on_track']) {
            $failures[] = "totals.on_track {$totals['on_track']} ≠ {$expected['on_track']}";
        }
        if ((int) $totals['needs_attention'] !== $expected['needs_attention']) {
            $failures[] = "totals.needs_attention {$totals['needs_attention']} ≠ {$expected['needs_attention']}";
        }
        if ((int) $totals['off_track'] !== $expected['off_track']) {
            $failures[] = "totals.off_track {$totals['off_track']} ≠ {$expected['off_track']}";
        }
        if (round((float) $totals['avg_achievement'], 4) !== $expected['avg_achievement']) {
            $failures[] = "totals.avg_achievement {$totals['avg_achievement']} ≠ {$expected['avg_achievement']}";
        }
        if ($totals['overall_health'] !== $expected['overall_health']) {
            $failures[] = "totals.overall_health {$totals['overall_health']} ≠ {$expected['overall_health']}";
        }

        foreach ($expected['pillars'] as $code => $exp) {
            $pillar = $summary['pillars']->first(fn ($p) => $p['pillar']->code === $code);
            if (! $pillar) {
                $failures[] = "pillar {$code}: missing";
                continue;
            }
            if (round((float) $pillar['avg_achievement'], 4) !== $exp['avg']) {
                $failures[] = "pillar {$code} avg {$pillar['avg_achievement']} ≠ {$exp['avg']}";
            }
            if ($pillar['headline_status'] !== $exp['headline']) {
                $failures[] = "pillar {$code} headline {$pillar['headline_status']} ≠ {$exp['headline']}";
            }
            if ($pillar['overall_health'] !== $exp['health']) {
                $failures[] = "pillar {$code} health {$pillar['overall_health']} ≠ {$exp['health']}";
            }
            if ((int) $pillar['on_track'] !== $exp['on_track']
                || (int) $pillar['needs_attention'] !== $exp['needs_attention']
                || (int) $pillar['off_track'] !== $exp['off_track']) {
                $failures[] = "pillar {$code} counts OT/NA/OFF {$pillar['on_track']}/{$pillar['needs_attention']}/{$pillar['off_track']} ≠ {$exp['on_track']}/{$exp['needs_attention']}/{$exp['off_track']}";
            }
        }

        return [
            'ok' => $failures === [],
            'failures' => $failures,
            'summary' => $summary,
            'expected' => $expected,
        ];
    }

    public function resolveSchoolSession(?int $schoolId = null, ?int $sessionId = null): array
    {
        $session = $sessionId
            ? AcademicSession::query()->findOrFail($sessionId)
            : AcademicSession::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->first()
                ?? AcademicSession::query()->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))->orderByDesc('start_date')->firstOrFail();

        return [
            'school_id' => $schoolId ?? (int) $session->school_id,
            'session_id' => (int) $session->id,
            'session' => $session,
        ];
    }
}
