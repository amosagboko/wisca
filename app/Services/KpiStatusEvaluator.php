<?php

namespace App\Services;

class KpiStatusEvaluator
{
    public function __construct(
        protected StatusThresholdResolver $thresholds,
    ) {}

    public function forSchool(?int $schoolId): static
    {
        $this->thresholds->forSchool($schoolId);

        return $this;
    }

    public function kpiStatus(float $achievementRate): string
    {
        $scale = $this->thresholds->all()['kpi'];

        if ($achievementRate >= (float) $scale['on_track']) {
            return 'ON TRACK';
        }

        if ($achievementRate >= (float) $scale['needs_attention']) {
            return 'NEEDS ATTENTION';
        }

        return 'OFF TRACK';
    }

    public function pillarHeadline(float $averageAchievement): string
    {
        $scale = $this->thresholds->all()['pillar_headline'];

        if ($averageAchievement >= (float) $scale['exceeding']) {
            return 'EXCEEDING';
        }

        if ($averageAchievement >= (float) $scale['on_track']) {
            return 'ON TRACK';
        }

        return 'NEEDS ATTENTION';
    }

    public function overallHealth(float $averageAchievement): string
    {
        $scale = $this->thresholds->all()['overall_health'];

        if ($averageAchievement >= (float) $scale['healthy']) {
            return 'HEALTHY';
        }

        if ($averageAchievement >= (float) $scale['satisfactory']) {
            return 'SATISFACTORY';
        }

        return 'CRITICAL';
    }

    public function achievementRate(?float $actual, ?float $target): ?float
    {
        if ($target === null || $target <= 0 || $actual === null) {
            return null;
        }

        return round($actual / $target, 4);
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'ON TRACK', 'EXCEEDING', 'HEALTHY' => 'bg-green-100 text-green-800',
            'NEEDS ATTENTION', 'SATISFACTORY' => 'bg-yellow-100 text-yellow-800',
            'OFF TRACK', 'CRITICAL' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function statusAccentClass(string $status): string
    {
        return match ($status) {
            'ON TRACK', 'EXCEEDING', 'HEALTHY' => 'border-emerald-500',
            'NEEDS ATTENTION', 'SATISFACTORY' => 'border-amber-500',
            'OFF TRACK', 'CRITICAL' => 'border-red-500',
            default => 'border-slate-300',
        };
    }

    public function statusDotClass(string $status): string
    {
        return match ($status) {
            'ON TRACK', 'EXCEEDING', 'HEALTHY' => 'bg-emerald-500',
            'NEEDS ATTENTION', 'SATISFACTORY' => 'bg-amber-500',
            'OFF TRACK', 'CRITICAL' => 'bg-red-500',
            default => 'bg-slate-400',
        };
    }
}
