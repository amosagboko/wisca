<?php

namespace App\Support;

use App\Models\Kpi;

class ObservationRubric
{
    public const SCALE = [
        1 => 'Emerging',
        2 => 'Developing',
        3 => 'Secure',
        4 => 'Exemplary',
    ];

    /**
     * Appendix B — 12 instructional standards.
     *
     * @return array<string, string>
     */
    public static function standards(): array
    {
        return [
            's1' => 'Learning objectives shared with learners',
            's2' => 'Starter and activation of prior knowledge',
            's3' => 'Subject knowledge and accuracy',
            's4' => 'Differentiation and inclusion',
            's5' => 'Questioning and discussion',
            's6' => 'Learner engagement and participation',
            's7' => 'Assessment for learning',
            's8' => 'Behaviour for learning',
            's9' => 'Use of resources, including digital',
            's10' => 'Pace, structure, and time management',
            's11' => 'Christian worldview and character formation',
            's12' => 'Plenary and evidence of learning',
        ];
    }

    public static function passMin(?Kpi $kpi = null): float
    {
        $kpi ??= Kpi::where('code', 'AE-06')->first();

        return (float) ($kpi?->config['observation_pass_min'] ?? 3);
    }

    public static function scaleLabel(int|float|null $score): string
    {
        $rounded = (int) round((float) $score);

        return static::SCALE[$rounded] ?? '—';
    }

    /**
     * @param  array<string, mixed>  $scores
     */
    public static function overall(?array $scores): ?float
    {
        if (! $scores) {
            return null;
        }

        $values = [];
        foreach (array_keys(static::standards()) as $key) {
            if (! isset($scores[$key]) || $scores[$key] === '' || $scores[$key] === null) {
                return null;
            }
            $values[] = (int) $scores[$key];
        }

        if (count($values) !== count(static::standards())) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    public static function isEffective(?float $overall, ?Kpi $kpi = null): bool
    {
        return $overall !== null && $overall >= static::passMin($kpi);
    }
}
