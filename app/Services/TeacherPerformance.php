<?php

namespace App\Services;

use App\Models\AtRiskLearner;
use App\Models\Kpi;
use App\Models\Observation;
use App\Models\SchemeOfWork;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;

class TeacherPerformance
{
    public const COVERAGE_TARGET = 0.95;

    public const HOMEWORK_TARGET = 0.95;

    public const ATTENDANCE_TARGET = 0.95;

    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $week
     * @return array{chips: Collection<int, array<string, mixed>>, follow_ups: Collection<int, array<string, mixed>>}
     */
    public function compose(array $week, User $teacher): array
    {
        $this->evaluator->forSchool((int) $teacher->school_id);

        $coverage = $week['coverage_summary'] ?? [];
        $expected = (int) ($coverage['expected'] ?? 0);
        $verified = (int) ($coverage['verified'] ?? 0);
        $planned = (int) ($coverage['planned'] ?? 0);
        $delivered = (int) ($coverage['delivered'] ?? 0);
        $homework = $week['homework_week'] ?? ['given' => 0, 'rate' => 0.0];
        $attendance = $week['attendance_week'] ?? ['enrolled' => 0, 'rate' => 0.0];
        $plans = $this->lessonPlanRate($week);

        $chips = collect([
            $this->chip(
                'coverage',
                'Curriculum Coverage',
                $expected > 0 ? (float) ($coverage['rate'] ?? 0) : null,
                self::COVERAGE_TARGET,
                $expected > 0,
                'AE-01 · verified topics this term',
                route('curriculum-coverage.report'),
                $expected > 0
                    ? $planned.' planned · '.$delivered.' delivered · '.$verified.' verified of '.$expected
                    : null,
            ),
            $this->chip(
                'plans',
                'Lesson plans',
                $plans['has_data'] ? $plans['rate'] : null,
                $plans['target'],
                $plans['has_data'],
                'AE-05 · on-time approved ÷ this-week topics',
                route('lesson-plans.index'),
                $plans['has_data']
                    ? $plans['approved_on_time'].' of '.$plans['required'].' approved on time'
                    : null,
            ),
            $this->chip(
                'homework',
                'Homework/Classwork',
                (int) ($homework['given'] ?? 0) > 0 ? (float) ($homework['rate'] ?? 0) : null,
                self::HOMEWORK_TARGET,
                (int) ($homework['given'] ?? 0) > 0,
                'AE-03 · on-time ÷ given · this week',
                route('homework.index'),
            ),
            $this->chip(
                'attendance',
                'Learner Attendance',
                (int) ($attendance['enrolled'] ?? 0) > 0 ? (float) ($attendance['rate'] ?? 0) : null,
                self::ATTENDANCE_TARGET,
                (int) ($attendance['enrolled'] ?? 0) > 0,
                'AE-04 · present ÷ enrolled · this week',
                route('attendance.index'),
            ),
        ]);

        return [
            'chips' => $chips,
            'follow_ups' => $this->followUps($week),
        ];
    }

    /**
     * @param  array<string, mixed>  $week
     * @return array{rate: float, target: float, has_data: bool, required: int, approved_on_time: int}
     */
    protected function lessonPlanRate(array $week): array
    {
        $target = (float) (Kpi::query()->where('code', 'AE-05')->value('default_target') ?? 1.0);
        $weekTopics = collect($week['this_week_topics'] ?? [])
            ->filter(fn (Topic $topic) => $topic->schemeOfWork instanceof SchemeOfWork && $topic->schemeOfWork->isActive())
            ->values();
        $required = $weekTopics->count();
        if ($required === 0) {
            return [
                'rate' => 0.0,
                'target' => $target,
                'has_data' => false,
                'required' => 0,
                'approved_on_time' => 0,
            ];
        }

        $approvedOnTime = $weekTopics->filter(function (Topic $topic) {
            $plans = $topic->relationLoaded('lessonPlans')
                ? $topic->lessonPlans
                : collect();

            return $plans->where('status', 'approved')->where('on_time', true)->isNotEmpty();
        })->count();

        return [
            'rate' => round($approvedOnTime / $required, 4),
            'target' => $target,
            'has_data' => true,
            'required' => $required,
            'approved_on_time' => $approvedOnTime,
        ];
    }

    /**
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    protected function followUps(array $week): Collection
    {
        $items = collect();

        collect($week['schemes'] ?? [])->each(function (SchemeOfWork $scheme) use ($items) {
            $scheme->topics->each(function (Topic $topic) use ($scheme, $items) {
                $context = $scheme->schoolClass->name.' · '.$scheme->subject->name;
                if ($topic->catchUpPlan?->isOpen()) {
                    $items->push([
                        'key' => 'catch-up-'.$topic->id,
                        'label' => 'Open catch-up · Wk '.$topic->week_number.' '.$topic->title,
                        'context' => $context,
                        'href' => $topic->hasApprovedLessonPlan() && $topic->isLoggable()
                            ? route('coverage-logs.create', ['topic' => $topic->id])
                            : route('lesson-plans.index'),
                        'cta' => $topic->hasApprovedLessonPlan() && $topic->isLoggable()
                            ? 'Record Delivery'
                            : 'Open plans',
                    ]);
                }

                if ($topic->latestCoverageLog?->status === 'rejected') {
                    $items->push([
                        'key' => 'coverage-rejected-'.$topic->id,
                        'label' => 'Coverage returned · Wk '.$topic->week_number.' '.$topic->title,
                        'context' => $context.' · '.($topic->latestCoverageLog->rejection_reason ?: 'Returned for revision.'),
                        'href' => route('coverage-logs.create', ['topic' => $topic->id]),
                        'cta' => 'Resubmit coverage',
                    ]);
                }
            });
        });

        collect($week['observations'] ?? [])
            ->filter(fn ($observation) => $observation instanceof Observation && $observation->status === 'follow_up_required')
            ->each(function (Observation $observation) use ($items) {
                $items->push([
                    'key' => 'observation-'.$observation->id,
                    'label' => 'Observation follow-up',
                    'context' => ($observation->schoolClass->name ?? 'Class')
                        .' · '.($observation->subject->name ?? 'Subject'),
                    'href' => route('observations.show', $observation),
                    'cta' => 'View observation',
                ]);
            });

        collect(($week['at_risk'] ?? [])['records'] ?? [])
            ->filter(fn ($record) => $record instanceof AtRiskLearner && ! $record->hasActivePlan())
            ->each(function (AtRiskLearner $record) use ($items) {
                $items->push([
                    'key' => 'at-risk-'.$record->id,
                    'label' => $record->learner->name,
                    'context' => ($record->schoolClass->name ?? 'Class').' · Identified without a plan. Learning Support or HOD adds the IIP.',
                    'href' => route('at-risk.show', $record),
                    'cta' => 'View caseload',
                ]);
            });

        return $items->unique('key')->take(8)->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function chip(
        string $key,
        string $label,
        ?float $actual,
        float $target,
        bool $hasData,
        string $period,
        string $href,
        ?string $detail = null,
    ): array {
        $achievement = $hasData && $actual !== null
            ? $this->evaluator->achievementRate($actual, $target)
            : null;
        $status = $achievement === null ? null : $this->evaluator->kpiStatus($achievement);

        return [
            'key' => $key,
            'label' => $label,
            'actual' => $actual,
            'target' => $target,
            'has_data' => $hasData,
            'status' => $status,
            'status_class' => $status ? $this->evaluator->statusBadgeClass($status) : 'bg-slate-100 text-slate-600',
            'accent_class' => $status ? $this->evaluator->statusAccentClass($status) : 'border-slate-200',
            'period' => $period,
            'detail' => $detail,
            'href' => $href,
        ];
    }
}
