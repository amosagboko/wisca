<?php

namespace Tests\Unit;

use App\Services\KpiStatusEvaluator;
use App\Services\StatusThresholdResolver;
use App\Support\ExcelSampleCatalog;
use Tests\TestCase;

class KpiStatusEvaluatorTest extends TestCase
{
    protected function evaluator(): KpiStatusEvaluator
    {
        return new KpiStatusEvaluator(new StatusThresholdResolver);
    }

    public function test_kpi_status_scale_matches_excel(): void
    {
        $evaluator = $this->evaluator();

        $this->assertSame('ON TRACK', $evaluator->kpiStatus(1.0));
        $this->assertSame('ON TRACK', $evaluator->kpiStatus(1.05));
        $this->assertSame('NEEDS ATTENTION', $evaluator->kpiStatus(0.90));
        $this->assertSame('NEEDS ATTENTION', $evaluator->kpiStatus(0.99));
        $this->assertSame('OFF TRACK', $evaluator->kpiStatus(0.8999));
    }

    public function test_pillar_headline_scale_matches_excel(): void
    {
        $evaluator = $this->evaluator();

        $this->assertSame('EXCEEDING', $evaluator->pillarHeadline(1.0));
        $this->assertSame('ON TRACK', $evaluator->pillarHeadline(0.90));
        $this->assertSame('NEEDS ATTENTION', $evaluator->pillarHeadline(0.8999));
    }

    public function test_overall_health_scale_matches_excel(): void
    {
        $evaluator = $this->evaluator();

        $this->assertSame('HEALTHY', $evaluator->overallHealth(0.95));
        $this->assertSame('SATISFACTORY', $evaluator->overallHealth(0.90));
        $this->assertSame('CRITICAL', $evaluator->overallHealth(0.8999));
    }

    public function test_achievement_rate_is_actual_over_target(): void
    {
        $evaluator = $this->evaluator();

        $this->assertSame(0.96, $evaluator->achievementRate(0.96, 1.0));
        $this->assertSame(1.0222, $evaluator->achievementRate(0.92, 0.9));
        $this->assertNull($evaluator->achievementRate(0.9, 0));
        $this->assertNull($evaluator->achievementRate(null, 1.0));
    }

    public function test_thresholds_are_configurable(): void
    {
        config([
            'wisca.status_thresholds.kpi' => [
                'on_track' => 0.98,
                'needs_attention' => 0.85,
            ],
        ]);

        $evaluator = $this->evaluator();

        $this->assertSame('ON TRACK', $evaluator->kpiStatus(0.98));
        $this->assertSame('NEEDS ATTENTION', $evaluator->kpiStatus(0.90));
        $this->assertSame('OFF TRACK', $evaluator->kpiStatus(0.84));
    }

    public function test_excel_sample_catalog_produces_board_counts(): void
    {
        $evaluator = $this->evaluator();
        $rates = [];
        $onTrack = 0;
        $needsAttention = 0;
        $offTrack = 0;

        foreach (ExcelSampleCatalog::samples() as $sample) {
            $ach = $evaluator->achievementRate($sample['actual'], $sample['target']);
            $rates[] = $ach;
            $status = $evaluator->kpiStatus($ach ?? 0);
            match ($status) {
                'ON TRACK' => $onTrack++,
                'NEEDS ATTENTION' => $needsAttention++,
                default => $offTrack++,
            };
        }

        $expected = ExcelSampleCatalog::expectedExecutive();
        $this->assertSame($expected['on_track'], $onTrack);
        $this->assertSame($expected['needs_attention'], $needsAttention);
        $this->assertSame($expected['off_track'], $offTrack);
        $this->assertSame($expected['avg_achievement'], round(array_sum($rates) / count($rates), 4));
        $this->assertSame($expected['overall_health'], $evaluator->overallHealth(round(array_sum($rates) / count($rates), 4)));
    }
}
