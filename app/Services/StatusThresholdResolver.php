<?php

namespace App\Services;

use App\Models\School;

class StatusThresholdResolver
{
    protected ?int $schoolId = null;

    public function forSchool(?int $schoolId): static
    {
        $this->schoolId = $schoolId;

        return $this;
    }

    /**
     * @return array{
     *   kpi: array{on_track: float, needs_attention: float},
     *   pillar_headline: array{exceeding: float, on_track: float},
     *   overall_health: array{healthy: float, satisfactory: float}
     * }
     */
    public function all(): array
    {
        $defaults = config('wisca.status_thresholds');
        $schoolId = $this->schoolId ?? auth()->user()?->school_id;

        if (! $schoolId) {
            return $defaults;
        }

        $overrides = School::query()->find($schoolId)?->settings['status_thresholds'] ?? [];

        return array_replace_recursive($defaults, $overrides);
    }
}
