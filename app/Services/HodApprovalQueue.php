<?php

namespace App\Services;

use App\Models\LessonPlan;
use App\Models\SchemeOfWork;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HodApprovalQueue
{
    public const WARN_HOURS = 20;

    public const SLA_HOURS = 24;

    public const PREVIEW_LIMIT = 3;

    public const REVIEW_PAGE_SIZE = 15;

    /**
     * @param  array<string, mixed>  $ops
     * @return array{tabs: Collection<int, array<string, mixed>>, default: string, empty: bool}
     */
    public function compose(array $ops): array
    {
        $schemes = $this->sortItems($this->schemes($ops['pending_schemes'] ?? collect()));
        $plans = $this->sortItems($this->plans($ops['pending_plan_items'] ?? $ops['pending_plans'] ?? collect()));
        $schemeTotal = (int) ($ops['pending_scheme_total'] ?? $schemes->count());
        $planTotal = (int) ($ops['pending_plan_total'] ?? $plans->count());

        $tabs = collect([
            $this->tab(
                'schemes',
                'Schemes of Work',
                $schemes->take(self::PREVIEW_LIMIT),
                $schemeTotal,
                'hod.reviews.schemes',
                'Review scheme',
            ),
            $this->tab(
                'plans',
                'Lesson plans',
                $plans->take(self::PREVIEW_LIMIT),
                $planTotal,
                'hod.reviews.lesson-plans',
                'Review lesson plan',
            ),
        ]);

        return [
            'tabs' => $tabs,
            'default' => $schemeTotal > 0 ? 'schemes' : ($planTotal > 0 ? 'plans' : 'schemes'),
            'empty' => $schemeTotal === 0 && $planTotal === 0,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public function sortItems(Collection $items): Collection
    {
        return $items->sortBy([
            ['overdue', 'desc'],
            ['warn', 'desc'],
            ['submitted_at', 'asc'],
        ])->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function tab(
        string $key,
        string $label,
        Collection $items,
        int $total,
        string $reviewRoute,
        string $reviewCta,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'items' => $items,
            'total' => $total,
            'more' => max(0, $total - $items->count()),
            'review_route' => $reviewRoute,
            'review_cta' => $reviewCta,
        ];
    }

    /**
     * @param  Collection<int, SchemeOfWork>|mixed  $schemes
     * @return Collection<int, array<string, mixed>>
     */
    public function schemes(mixed $schemes): Collection
    {
        return collect($schemes)
            ->filter(fn ($scheme) => $scheme instanceof SchemeOfWork && $scheme->isSubmitted())
            ->map(function (SchemeOfWork $scheme) {
                $scheme->loadMissing(['subject', 'schoolClass.schoolSection', 'submittedBy', 'uploadedBy']);
                $teacher = $scheme->submittedBy ?? $scheme->uploadedBy;
                $clock = $this->clock($scheme->submitted_at);

                return $this->item(
                    'queue-scheme-'.$scheme->id,
                    'scheme',
                    'Scheme of Work',
                    ($scheme->subject?->name ?? 'Subject').' · v'.$scheme->version,
                    $teacher?->name,
                    $scheme->schoolClass?->schoolSection?->name,
                    $scheme->schoolClass?->name,
                    $scheme->subject?->name,
                    $scheme->submitted_at,
                    $clock,
                    route('schemes.show', $scheme),
                    'Review scheme',
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, LessonPlan>|mixed  $plans
     * @return Collection<int, array<string, mixed>>
     */
    public function plans(mixed $plans): Collection
    {
        return collect($plans)
            ->filter(fn ($plan) => $plan instanceof LessonPlan && $plan->status === 'submitted')
            ->map(function (LessonPlan $plan) {
                $plan->loadMissing(['topic', 'teacher', 'schoolClass.schoolSection', 'subject']);
                $clock = $this->clock($plan->submitted_at);

                return $this->item(
                    'queue-plan-'.$plan->id,
                    'plan',
                    'Lesson plan',
                    $plan->topic?->title ?? 'Lesson plan',
                    $plan->teacher?->name,
                    $plan->schoolClass?->schoolSection?->name,
                    $plan->schoolClass?->name,
                    $plan->subject?->name,
                    $plan->submitted_at,
                    $clock,
                    route('lesson-plans.show', $plan),
                    'Review plan',
                );
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function clock(?Carbon $submitted): array
    {
        if (! $submitted) {
            return [
                'elapsed_label' => '—',
                'remaining_label' => '—',
                'hours_elapsed' => 0.0,
                'warn' => false,
                'overdue' => false,
                'deadline_at' => null,
            ];
        }

        $now = now();
        $deadline = $submitted->copy()->addHours(self::SLA_HOURS);
        $hoursElapsed = max(0.0, $submitted->diffInSeconds($now) / 3600);
        $overdue = $now->gt($deadline);
        $warn = ! $overdue && $hoursElapsed >= self::WARN_HOURS;

        return [
            'elapsed_label' => $this->hoursLabel($hoursElapsed).' ago',
            'remaining_label' => $overdue
                ? '24-hour HOD review SLA has passed'
                : $this->hoursLabel(max(0.0, self::SLA_HOURS - $hoursElapsed)).' left',
            'hours_elapsed' => $hoursElapsed,
            'warn' => $warn,
            'overdue' => $overdue,
            'deadline_at' => $deadline,
        ];
    }

    protected function hoursLabel(float $hours): string
    {
        if ($hours < 1) {
            $minutes = max(1, (int) round($hours * 60));

            return $minutes.' minute'.($minutes === 1 ? '' : 's');
        }

        $whole = (int) floor($hours);

        return $whole.' hour'.($whole === 1 ? '' : 's');
    }

    /**
     * @param  array<string, mixed>  $clock
     * @return array<string, mixed>
     */
    protected function item(
        string $key,
        string $type,
        string $typeLabel,
        string $title,
        ?string $teacher,
        ?string $section,
        ?string $class,
        ?string $subject,
        ?Carbon $submittedAt,
        array $clock,
        string $href,
        string $cta,
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'type_label' => $typeLabel,
            'title' => $title,
            'teacher' => $teacher,
            'section' => $section,
            'class' => $class,
            'subject' => $subject,
            'submitted_at' => $submittedAt,
            'elapsed_label' => $clock['elapsed_label'],
            'remaining_label' => $clock['remaining_label'],
            'hours_elapsed' => $clock['hours_elapsed'],
            'warn' => $clock['warn'],
            'overdue' => $clock['overdue'],
            'deadline_at' => $clock['deadline_at'],
            'href' => $href,
            'cta' => $cta,
        ];
    }
}
