<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class TeacherActivityDrawer
{
    /**
     * @param  array<string, mixed>  $week
     * @return array{items: Collection<int, array<string, mixed>>, due_count: int, current_week: int}
     */
    public function compose(array $week, User $teacher): array
    {
        $weekNumber = (int) ($week['week_number'] ?? $week['max_week'] ?? 1);
        $exam = $week['exam_term'] ?? ['enrolled' => 0, 'rate' => 0.0, 'pass_mark' => 50, 'passed' => 0];
        $observations = collect($week['observations'] ?? []);
        $followUps = $observations->where('status', 'follow_up_required')->count();

        $items = collect([
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
                    : 'Termly marksheet. Enter scores here; this is not an exam-portal sync.',
            ],
            $teacher->canRecordReading() ? [
                'key' => 'literacy',
                'label' => 'Literacy baseline survey',
                'code' => 'AE-08',
                'href' => route('reading.index'),
                'value' => null,
                'status' => $weekNumber === 1 ? 'due' : 'scheduled',
                'badge' => $weekNumber === 1 ? 'Due week 1' : 'Beginning of term',
                'note' => 'Record approved reading levels. Growth uses baseline vs follow-up.',
            ] : null,
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
                'key' => 'observations',
                'label' => 'Lesson observations',
                'code' => 'AE-06',
                'href' => route('observations.index'),
                'value' => $observations->isNotEmpty() ? (string) $observations->count() : null,
                'status' => $followUps > 0 ? 'due' : 'scheduled',
                'badge' => $followUps > 0
                    ? $followUps.' follow-up'.($followUps === 1 ? '' : 's')
                    : 'Own records',
                'note' => 'View observations of your lessons. Teachers do not conduct observations.',
            ],
            [
                'key' => 'chapel',
                'label' => 'Chapel roll',
                'code' => 'CE-01',
                'href' => route('chapel.index'),
                'value' => null,
                'status' => 'scheduled',
                'badge' => 'Term activity',
                'note' => 'Mark chapel attendance for your assigned classes.',
            ],
            [
                'key' => 'stem',
                'label' => 'STEM sessions',
                'code' => 'DI-02',
                'href' => route('stem.create'),
                'value' => null,
                'status' => 'scheduled',
                'badge' => 'Log session',
                'note' => 'Record STEM project completions for your classes.',
            ],
            [
                'key' => 'service',
                'label' => 'Community service hours',
                'code' => 'CE-03',
                'href' => route('service.create'),
                'value' => null,
                'status' => 'scheduled',
                'badge' => 'Log hours',
                'note' => 'Excel target is 10 hours per learner per term.',
            ],
            [
                'key' => 'character',
                'label' => 'Character ratings',
                'code' => 'CE-02',
                'href' => route('character.index'),
                'value' => null,
                'status' => 'scheduled',
                'badge' => 'Term activity',
                'note' => 'Rate character domains for learners in your classes.',
            ],
            [
                'key' => 'scripture',
                'label' => 'Scripture assessment',
                'code' => 'CE-06',
                'href' => route('scripture.index'),
                'value' => null,
                'status' => 'scheduled',
                'badge' => 'Term activity',
                'note' => 'Record scripture assessments for your classes.',
            ],
        ])->filter()->values();

        return [
            'items' => $items,
            'due_count' => $items->where('status', 'due')->count(),
            'current_week' => $weekNumber,
        ];
    }
}
