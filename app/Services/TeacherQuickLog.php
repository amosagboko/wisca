<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class TeacherQuickLog
{
    /**
     * @return Collection<int, array{label: string, href: string}>
     */
    public function actions(User $user): Collection
    {
        $items = [
            ['label' => 'Homework log', 'href' => route('homework.create')],
            ['label' => 'Class register', 'href' => route('attendance.create')],
            ['label' => 'Coverage log', 'href' => route('coverage-logs.create')],
        ];

        if ($user->canIdentifyAtRisk()) {
            $items[] = ['label' => 'At-risk learner', 'href' => route('at-risk.create')];
        }

        $items[] = ['label' => 'Conduct or incident', 'href' => route('discipline.create')];
        $items[] = ['label' => 'STEM session', 'href' => route('stem.create')];
        $items[] = ['label' => 'Community service hours', 'href' => route('service.create')];

        if ($user->canRecordReading()) {
            $items[] = ['label' => 'Reading assessment', 'href' => route('reading.create')];
        }

        return collect($items)->values();
    }
}
