<?php

namespace App\Support;

/**
 * Locked Excel Master KPI Data sample Actuals (Board acceptance §11).
 * Achievement = actual ÷ target; statuses use default Excel scales.
 */
class ExcelSampleCatalog
{
    /**
     * @return array<string, array{target: float, actual: float}>
     */
    public static function samples(): array
    {
        return [
            'AE-01' => ['target' => 1.0, 'actual' => 0.96],
            'AE-02' => ['target' => 0.9, 'actual' => 0.92],
            'AE-03' => ['target' => 0.95, 'actual' => 0.94],
            'AE-04' => ['target' => 0.95, 'actual' => 0.965],
            'AE-05' => ['target' => 1.0, 'actual' => 0.98],
            'AE-06' => ['target' => 0.9, 'actual' => 0.88],
            'AE-07' => ['target' => 1.0, 'actual' => 1.0],
            'AE-08' => ['target' => 0.85, 'actual' => 0.82],
            'CE-01' => ['target' => 0.95, 'actual' => 0.97],
            'CE-02' => ['target' => 0.85, 'actual' => 0.88],
            'CE-03' => ['target' => 10.0, 'actual' => 9.5],
            'CE-04' => ['target' => 0.9, 'actual' => 0.93],
            'CE-05' => ['target' => 1.0, 'actual' => 1.0],
            'CE-06' => ['target' => 0.85, 'actual' => 0.8],
            'CE-07' => ['target' => 0.85, 'actual' => 0.89],
            'DI-01' => ['target' => 0.9, 'actual' => 0.94],
            'DI-02' => ['target' => 0.85, 'actual' => 0.87],
            'DI-03' => ['target' => 0.95, 'actual' => 0.96],
            'DI-04' => ['target' => 0.85, 'actual' => 0.78],
            'DI-05' => ['target' => 0.8, 'actual' => 0.83],
            'DI-06' => ['target' => 0.8, 'actual' => 0.82],
        ];
    }

    /**
     * Expected Executive sheet rollup for the sample Actuals (Excel §11).
     *
     * @return array{
     *   on_track: int,
     *   needs_attention: int,
     *   off_track: int,
     *   avg_achievement: float,
     *   overall_health: string,
     *   pillars: array<string, array{avg: float, headline: string, health: string, on_track: int, needs_attention: int, off_track: int}>
     * }
     */
    public static function expectedExecutive(): array
    {
        return [
            'on_track' => 13,
            'needs_attention' => 8,
            'off_track' => 0,
            'avg_achievement' => 0.9998,
            'overall_health' => 'HEALTHY',
            'pillars' => [
                'academic_excellence' => [
                    'avg' => 0.9888,
                    'headline' => 'ON TRACK',
                    'health' => 'HEALTHY',
                    'on_track' => 3,
                    'needs_attention' => 5,
                    'off_track' => 0,
                ],
                'christocentric_education' => [
                    'avg' => 1.004,
                    'headline' => 'EXCEEDING',
                    'health' => 'HEALTHY',
                    'on_track' => 5,
                    'needs_attention' => 2,
                    'off_track' => 0,
                ],
                'digital_innovation' => [
                    'avg' => 1.0098,
                    'headline' => 'EXCEEDING',
                    'health' => 'HEALTHY',
                    'on_track' => 5,
                    'needs_attention' => 1,
                    'off_track' => 0,
                ],
            ],
        ];
    }
}
