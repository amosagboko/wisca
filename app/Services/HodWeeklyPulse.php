<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class HodWeeklyPulse
{
    public const COVERAGE_TARGET = 0.95;

    public const HOMEWORK_TARGET = 0.95;

    public const ATTENDANCE_TARGET = 0.95;

    public const ASSIMILATION_TARGET = 0.90;

    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $ops
     * @return array{chips: Collection<int, array<string, mixed>>, pills: Collection<int, array<string, mixed>>, see_all: string}
     */
    public function compose(array $ops, User $hod): array
    {
        $this->evaluator->forSchool((int) $hod->school_id);

        $coverage = $ops['coverage_summary'] ?? [];
        $expected = (int) ($coverage['expected'] ?? 0);
        $verified = (int) ($coverage['verified'] ?? 0);
        $homework = $ops['homework_week'] ?? ['given' => 0, 'rate' => 0.0];
        $attendance = $ops['attendance_week'] ?? ['enrolled' => 0, 'rate' => 0.0];
        $week = (int) ($ops['max_week'] ?? 1);

        $chips = collect([
            $this->chip(
                'coverage',
                'Curriculum Coverage',
                $expected > 0 ? (float) ($coverage['rate'] ?? 0) : null,
                self::COVERAGE_TARGET,
                $expected > 0,
                'AE-01 · verified topics this term',
                route('curriculum-coverage.report'),
                $expected > 0 ? $verified.' verified of '.$expected.' expected' : null,
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
            $this->chip(
                'assimilation',
                'Learner Understanding',
                null,
                self::ASSIMILATION_TARGET,
                false,
                'AE-10 · awaiting implementation',
                route('activities.coming-soon', ['activity' => 'learner-assimilation-rate']),
            ),
        ]);

        $followUps = collect($ops['observations'] ?? [])->where('status', 'follow_up_required')->count();
        $withoutPlan = (int) (($ops['at_risk']['without_plan'] ?? 0));
        $outstanding = (int) ($coverage['outstanding_verification'] ?? 0);

        $pills = collect([
            $followUps > 0 ? [
                'key' => 'observations',
                'label' => 'Observations: '.$followUps.' follow-up'.($followUps === 1 ? '' : 's').' this term',
                'href' => route('observations.index'),
            ] : null,
            $withoutPlan > 0 ? [
                'key' => 'at-risk',
                'label' => 'At-risk: '.$withoutPlan.' without a plan',
                'href' => route('at-risk.index', ['plan' => 'without_plan']),
            ] : null,
            $outstanding > 0 ? [
                'key' => 'coverage-verify',
                'label' => 'Coverage: '.$outstanding.' awaiting verification',
                'href' => route('coverage-logs.index', ['status' => 'submitted']),
            ] : null,
        ])->filter()->take(5)->values();

        return [
            'chips' => $chips,
            'pills' => $pills,
            'see_all' => route('curriculum-coverage.report'),
            'week' => $week,
        ];
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
            'below_target' => $hasData && $achievement !== null && $achievement < 1.0,
        ];
    }
}
