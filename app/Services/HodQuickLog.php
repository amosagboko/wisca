<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class HodQuickLog
{
    /**
     * @return Collection<int, array{label: string, href: string}>
     */
    public function actions(User $user): Collection
    {
        $items = [];

        if ($user->canConductObservation()) {
            $items[] = ['label' => 'Lesson observation', 'href' => route('observations.create')];
        }
        if ($user->canIdentifyAtRisk()) {
            $items[] = ['label' => 'At-risk learner', 'href' => route('at-risk.create')];
        }
        if ($user->isHoD() || $user->isLeadership() || $user->isAdmin() || $user->isTeacher()) {
            $items[] = ['label' => 'Conduct or incident', 'href' => route('discipline.create')];
            $items[] = ['label' => 'STEM session', 'href' => route('stem.create')];
            $items[] = ['label' => 'Community service hours', 'href' => route('service.create')];
        }

        return collect($items)->values();
    }
}
