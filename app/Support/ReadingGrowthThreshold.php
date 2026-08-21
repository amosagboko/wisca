<?php

namespace App\Support;

use App\Models\Kpi;

class ReadingGrowthThreshold
{
    /**
     * Minimum grade-level growth that counts as "≥1.0 grade level".
     * Stored in AE-08 KPI config['growth_min']; defaults to 1.0.
     */
    public static function min(?Kpi $kpi = null): float
    {
        $kpi ??= Kpi::where('code', 'AE-08')->first();

        return (float) ($kpi?->config['growth_min'] ?? 1.0);
    }

    public static function achieved(float $baseline, float $followup, ?Kpi $kpi = null): bool
    {
        return ($followup - $baseline) >= static::min($kpi);
    }

    public static function growth(float $baseline, float $followup): float
    {
        return round($followup - $baseline, 2);
    }
}
