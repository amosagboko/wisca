<?php

namespace App\Services;

use App\Models\LessonPlan;
use App\Models\User;

class LessonPlanReview
{
    /**
     * @var array<string, string>
     */
    public const ITEMS = [
        'alignment' => 'Curriculum is aligned to the Active Scheme of Work topic and selected objectives.',
        'quality' => 'Activities and resources are complete and teachable as written.',
        'engagement' => 'Learner engagement is planned (not lecture-only).',
        'assessment' => 'Assessment checks the stated learning objectives.',
    ];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach (array_keys(self::ITEMS) as $key) {
            $rules['checklist.'.$key] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{items: array<string, array{label: string, passed: bool}>, reviewed_by: int, reviewed_at: string}
     */
    public function snapshot(User $reviewer, array $answers): array
    {
        $items = [];
        foreach (self::ITEMS as $key => $label) {
            $items[$key] = [
                'label' => $label,
                'passed' => filter_var($answers[$key] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return [
            'items' => $items,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function isComplete(?array $snapshot): bool
    {
        $items = $snapshot['items'] ?? null;
        if (! is_array($items)) {
            return false;
        }

        foreach (array_keys(self::ITEMS) as $key) {
            if (! array_key_exists($key, $items) || ! array_key_exists('passed', $items[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function allPassed(?array $snapshot): bool
    {
        if (! $this->isComplete($snapshot)) {
            return false;
        }

        foreach ($snapshot['items'] as $item) {
            if (! ($item['passed'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    public function planHasCompleteChecklist(LessonPlan $plan): bool
    {
        return $this->isComplete($plan->review_checklist);
    }
}
