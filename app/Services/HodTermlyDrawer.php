<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class HodTermlyDrawer
{
    /**
     * @param  array<string, mixed>  $ops
     * @return array{items: Collection<int, array<string, mixed>>, due_count: int, current_week: int}
     */
    public function compose(array $ops, User $hod): array
    {
        $week = (int) ($ops['pulse_week'] ?? $ops['max_week'] ?? 1);
        $maxWeek = max(1, (int) ($ops['max_week'] ?? 1));
        $exam = $ops['exam_term'] ?? ['enrolled' => 0, 'rate' => 0.0, 'pass_mark' => 50, 'passed' => 0];
        $observation = $ops['observation_term'] ?? ['observed' => 0, 'rate' => 0.0];
        $followUps = collect($ops['observations'] ?? [])->where('status', 'follow_up_required')->count();

        $items = collect([
            [
                'key' => 'literacy',
                'label' => 'Literacy baseline survey',
                'code' => 'AE-08',
                'href' => route('reading.index'),
                'value' => null,
                'status' => $week === 1 ? 'due' : 'scheduled',
                'badge' => $week === 1 ? 'Due week 1' : 'Beginning of term',
                'note' => 'Record approved reading levels. Growth uses baseline vs follow-up.',
            ],
            [
                'key' => 'numeracy',
                'label' => 'Numeracy baseline survey',
                'code' => 'AE-09',
                'href' => route('activities.coming-soon', ['activity' => 'numeracy-progress']),
                'value' => null,
                'status' => 'awaiting',
                'badge' => 'Awaiting data',
                'note' => 'Numeracy capture is not implemented yet.',
            ],
            [
                'key' => 'exams',
                'label' => 'Examination pass rate',
                'code' => 'AE-02',
                'href' => route('exam-results.index'),
                'value' => (int) ($exam['enrolled'] ?? 0) > 0
                    ? number_format(((float) $exam['rate']) * 100, 1).'%'
                    : null,
                'status' => (int) ($exam['enrolled'] ?? 0) > 0 ? 'ready' : 'awaiting',
                'badge' => (int) ($exam['enrolled'] ?? 0) > 0
                    ? 'Target 90% · pass ≥'.((int) ($exam['pass_mark'] ?? 50)).'%'
                    : 'Awaiting data',
                'note' => (int) ($exam['enrolled'] ?? 0) > 0
                    ? ((int) $exam['passed']).' of '.((int) $exam['enrolled']).' enrolled sittings passed.'
                    : 'Termly marksheet. Teachers enter scores; this is not an exam-portal sync.',
            ],
            [
                'key' => 'service',
                'label' => 'Community service hours close-out',
                'code' => 'CE-03',
                'href' => route('service.index'),
                'value' => null,
                'status' => $week >= max(1, $maxWeek - 1) ? 'due' : 'scheduled',
                'badge' => $week >= max(1, $maxWeek - 1) ? 'Due near term end' : 'Target 10 hours / learner',
                'note' => 'Excel target is 10 hours per learner per term.',
            ],
            [
                'key' => 'observations',
                'label' => 'Termly observation follow-up',
                'code' => 'AE-06',
                'href' => route('observations.index'),
                'value' => (int) ($observation['observed'] ?? 0) > 0
                    ? number_format(((float) $observation['rate']) * 100, 1).'%'
                    : null,
                'status' => $followUps > 0 ? 'due' : ((int) ($observation['observed'] ?? 0) > 0 ? 'ready' : 'scheduled'),
                'badge' => $followUps > 0
                    ? $followUps.' follow-up'.($followUps === 1 ? '' : 's')
                    : 'Target 90% Secure+',
                'note' => 'Effective or Better is Secure (3) or higher.',
            ],
        ]);

        return [
            'items' => $items,
            'due_count' => $items->where('status', 'due')->count(),
            'current_week' => $week,
        ];
    }
}
