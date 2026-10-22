<?php

namespace App\Services;

use App\Models\AtRiskLearner;
use App\Models\Observation;
use App\Models\Topic;
use App\Models\TopicCatchUpPlan;
use App\Models\TopicCoverageLog;
use Illuminate\Support\Collection;

class HodExceptionFeed
{
    /**
     * @param  array<string, mixed>  $ops
     * @param  array{chips?: Collection<int, array<string, mixed>>}  $pulse
     * @return array{open: Collection<int, array<string, mixed>>, closed: Collection<int, array<string, mixed>>}
     */
    public function compose(array $ops, array $pulse = []): array
    {
        $open = collect()
            ->concat($this->kpiFlags($pulse['chips'] ?? collect()))
            ->concat($this->catchUpFlags($ops['catch_up_needed'] ?? collect()))
            ->concat($this->coverageFlags($ops['pending_coverage_items'] ?? $ops['pending'] ?? collect()))
            ->concat($this->atRiskPlanFlags($ops['at_risk_without_plan'] ?? collect()))
            ->concat($this->unflaggedFlags($ops['at_risk_unflagged'] ?? collect()))
            ->concat($this->observationFlags($ops['observations'] ?? collect()))
            ->unique('key')
            ->values();

        return [
            'open' => $open,
            'closed' => collect($ops['recently_closed'] ?? []),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>|mixed  $chips
     * @return Collection<int, array<string, mixed>>
     */
    protected function kpiFlags(mixed $chips): Collection
    {
        return collect($chips)
            ->filter(fn ($chip) => is_array($chip) && ($chip['below_target'] ?? false))
            ->map(function (array $chip) {
                return $this->flag(
                    'kpi-'.$chip['key'],
                    'kpi',
                    $chip['label'].' below target',
                    $chip['period'] ?? 'Weekly Pulse',
                    'Current value is below the approved target.',
                    'Review the class-level records and outstanding actions.',
                    $chip['href'],
                    'Review details',
                    null,
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, Topic>|mixed  $topics
     * @return Collection<int, array<string, mixed>>
     */
    protected function catchUpFlags(mixed $topics): Collection
    {
        return collect($topics)
            ->filter(fn ($topic) => $topic instanceof Topic)
            ->map(function (Topic $topic) {
                $scheme = $topic->schemeOfWork;

                return $this->flag(
                    'catch-up-'.$topic->id,
                    'catch-up',
                    'Catch-up needed · Wk '.$topic->week_number.' '.$topic->title,
                    ($scheme?->schoolClass?->name ?? 'Class').' · '.($scheme?->subject?->name ?? 'Subject'),
                    'Active SoW topic is behind the instructional week without verified coverage (AE-01.4, not an IIP).',
                    'Open catch-up so the teacher can recover this topic.',
                    route('curriculum-coverage.report'),
                    'Open plan',
                    'HOD',
                    [
                        'form' => [
                            'action' => route('catch-ups.store'),
                            'fields' => ['topic_id' => $topic->id],
                            'cta' => 'Open catch-up',
                        ],
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, TopicCoverageLog>|mixed  $logs
     * @return Collection<int, array<string, mixed>>
     */
    protected function coverageFlags(mixed $logs): Collection
    {
        return collect($logs)
            ->filter(fn ($log) => $log instanceof TopicCoverageLog && $log->status === 'submitted')
            ->map(function (TopicCoverageLog $log) {
                return $this->flag(
                    'coverage-'.$log->id,
                    'coverage',
                    'Delivered, not verified · '.($log->topic?->title ?? 'Coverage'),
                    ($log->teacher?->name ?? 'Teacher').' · '.($log->schoolClass?->name ?? 'Class'),
                    'Teacher reported delivery. Verified coverage is still outstanding.',
                    'Review the workbook evidence and verify or return the log.',
                    route('coverage-logs.index', ['status' => 'submitted']),
                    'Review details',
                    'HOD',
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, AtRiskLearner>|mixed  $records
     * @return Collection<int, array<string, mixed>>
     */
    protected function atRiskPlanFlags(mixed $records): Collection
    {
        return collect($records)
            ->filter(fn ($record) => $record instanceof AtRiskLearner && ! $record->hasActivePlan())
            ->map(function (AtRiskLearner $record) {
                return $this->flag(
                    'at-risk-plan-'.$record->id,
                    'at-risk',
                    ($record->learner?->name ?? 'Learner').' needs an intervention plan',
                    $record->schoolClass?->name ?? 'Class',
                    'Identified learner has no active Tier 2/3 plan (AE-07).',
                    'Write the individualised intervention plan.',
                    route('at-risk.plans.create', $record),
                    'Open plan',
                    'HOD',
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|mixed  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function unflaggedFlags(mixed $rows): Collection
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row) && isset($row['learner']))
            ->map(function (array $row) {
                $learner = $row['learner'];

                return $this->flag(
                    'unflagged-'.$learner->id,
                    'at-risk',
                    $learner->name.' is below pass and not flagged',
                    $learner->schoolClass?->name ?? 'Class',
                    'Verified exam score is below the pass mark. The learner is not on the at-risk register.',
                    'Review the caseload and flag the learner if support is required.',
                    route('at-risk.index'),
                    'Review details',
                    'HOD',
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, Observation>|mixed  $observations
     * @return Collection<int, array<string, mixed>>
     */
    protected function observationFlags(mixed $observations): Collection
    {
        return collect($observations)
            ->filter(fn ($obs) => $obs instanceof Observation && $obs->status === 'follow_up_required')
            ->map(function (Observation $obs) {
                return $this->flag(
                    'observation-'.$obs->id,
                    'observation',
                    'Observation follow-up · '.($obs->teacher?->name ?? 'Teacher'),
                    ($obs->schoolClass?->name ?? 'Class').' · '.($obs->subject?->name ?? 'Subject'),
                    'The lesson did not meet Effective or Better (Secure+).',
                    'Open the observation and complete the follow-up.',
                    route('observations.show', $obs),
                    'Review details',
                    'HOD',
                );
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function flag(
        string $key,
        string $category,
        string $title,
        string $context,
        string $reason,
        string $next,
        string $href,
        string $cta,
        ?string $owner,
        array $extra = [],
    ): array {
        return array_merge([
            'key' => $key,
            'category' => $category,
            'title' => $title,
            'context' => $context,
            'reason' => $reason,
            'next' => $next,
            'href' => $href,
            'cta' => $cta,
            'owner' => $owner ?? 'HOD',
            'status' => 'open',
        ], $extra);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recentlyClosed(array $ops): Collection
    {
        $catchUps = collect($ops['recent_catch_ups'] ?? [])
            ->filter(fn ($plan) => $plan instanceof TopicCatchUpPlan)
            ->map(fn (TopicCatchUpPlan $plan) => [
                'key' => 'closed-catch-up-'.$plan->id,
                'title' => 'Catch-up addressed · '.($plan->topic?->title ?? 'Topic'),
                'when' => $plan->addressed_at,
            ]);

        $resolved = collect($ops['recent_resolved_at_risk'] ?? [])
            ->filter(fn ($record) => $record instanceof AtRiskLearner)
            ->map(fn (AtRiskLearner $record) => [
                'key' => 'closed-at-risk-'.$record->id,
                'title' => 'At-risk resolved · '.($record->learner?->name ?? 'Learner'),
                'when' => $record->resolved_at,
            ]);

        $verified = collect($ops['recent_verified_coverage'] ?? [])
            ->filter(fn ($log) => $log instanceof TopicCoverageLog)
            ->map(fn (TopicCoverageLog $log) => [
                'key' => 'closed-coverage-'.$log->id,
                'title' => 'Coverage verified · '.($log->topic?->title ?? 'Topic'),
                'when' => $log->verified_at,
            ]);

        return $catchUps->concat($resolved)->concat($verified)
            ->sortByDesc(fn ($row) => $row['when']?->timestamp ?? 0)
            ->take(5)
            ->values();
    }
}
