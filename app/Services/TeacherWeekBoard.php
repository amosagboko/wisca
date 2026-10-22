<?php

namespace App\Services;

use App\Models\SchemeOfWork;
use App\Models\TeacherAssignment;
use App\Models\Topic;
use Illuminate\Support\Collection;

class TeacherWeekBoard
{
    public function __construct(
        protected AcademicPeriodService $periods,
    ) {}

    /**
     * One compact row per assignment for My Teaching Week.
     *
     * @param  array<string, mixed>  $week
     * @return Collection<int, array<string, mixed>>
     */
    public function compose(array $week): Collection
    {
        $weekNumber = (int) ($week['week_number'] ?? 1);
        $blocked = ! $this->periods->acceptsNewActivity($week['session'] ?? null, $week['term'] ?? null);
        /** @var Collection<int, TeacherAssignment> $assignments */
        $assignments = collect($week['assignments'] ?? []);
        /** @var Collection<int, SchemeOfWork> $schemes */
        $schemes = collect($week['schemes'] ?? []);
        /** @var Collection<int, SchemeOfWork> $pipeline */
        $pipeline = collect($week['scheme_pipeline'] ?? []);
        /** @var Collection<int, Topic> $thisWeekTopics */
        $thisWeekTopics = collect($week['this_week_topics'] ?? []);

        return $assignments->map(function (TeacherAssignment $assignment) use ($weekNumber, $schemes, $pipeline, $thisWeekTopics, $blocked) {
            $classId = (int) $assignment->school_class_id;
            $subjectId = (int) $assignment->subject_id;
            $active = $schemes->first(function (SchemeOfWork $scheme) use ($classId, $subjectId) {
                return (int) $scheme->school_class_id === $classId
                    && (int) $scheme->subject_id === $subjectId
                    && $scheme->isActive();
            });
            $approved = $schemes->first(function (SchemeOfWork $scheme) use ($classId, $subjectId) {
                return (int) $scheme->school_class_id === $classId
                    && (int) $scheme->subject_id === $subjectId
                    && $scheme->isApproved();
            });
            $pair = $pipeline->filter(
                fn (SchemeOfWork $scheme) => (int) $scheme->school_class_id === $classId
                    && (int) $scheme->subject_id === $subjectId
            );
            $submitted = $pair->first(fn (SchemeOfWork $scheme) => $scheme->isSubmitted());
            $scheme = $active ?? $approved ?? $submitted ?? $pair->first();

            $topics = $thisWeekTopics
                ->filter(function (Topic $topic) use ($classId, $subjectId) {
                    $scheme = $topic->schemeOfWork;

                    return $scheme
                        && (int) $scheme->school_class_id === $classId
                        && (int) $scheme->subject_id === $subjectId;
                })
                ->values();

            $primary = $topics->first(fn (Topic $topic) => $this->topicNeedsAction($topic)) ?? $topics->first();
            $cta = $blocked ? [] : $this->contextCta($primary, (bool) $active);

            return [
                'key' => 'assignment-'.$assignment->id,
                'class' => $assignment->schoolClass->name,
                'subject' => $assignment->subject->name,
                'week' => $weekNumber,
                'scheme_label' => $this->schemeLabel($active, $approved, $submitted, $scheme),
                'scheme_href' => $scheme ? route('schemes.show', $scheme) : null,
                'topics_line' => $topics->isEmpty()
                    ? 'No topic this week'
                    : $topics->pluck('title')->filter()->implode(' · '),
                'plan_status' => $this->planStatus($primary, $active, $submitted),
                'delivery_status' => $this->deliveryStatus($primary),
                'cta' => $cta['label'] ?? null,
                'href' => $cta['href'] ?? null,
                'rejection_reason' => $this->rejectionReason($primary, $scheme),
            ];
        })->values();
    }

    protected function schemeLabel(?SchemeOfWork $active, ?SchemeOfWork $approved, ?SchemeOfWork $submitted, ?SchemeOfWork $scheme): string
    {
        if ($active) {
            return 'Active scheme';
        }
        if ($approved) {
            return 'Approved scheme';
        }
        if ($submitted) {
            return 'Awaiting HOD';
        }
        if ($scheme?->isReturned()) {
            return 'Returned scheme';
        }
        if ($scheme?->isEditableDraft()) {
            return 'Draft scheme';
        }

        return 'No approved scheme';
    }

    protected function planStatus(?Topic $topic, ?SchemeOfWork $active, ?SchemeOfWork $submitted): string
    {
        if (! $active) {
            return $submitted ? 'Awaiting HOD' : 'No approved scheme';
        }
        if (! $topic) {
            return 'No topic this week';
        }

        $plan = $topic->latestLessonPlan;
        if ($topic->hasApprovedLessonPlan()) {
            return 'Approved';
        }
        if ($plan?->status === 'submitted') {
            return 'Awaiting HOD';
        }
        if ($plan?->status === 'rejected') {
            return 'Returned';
        }
        if ($plan?->isEditable()) {
            return 'Draft';
        }

        return 'Not planned';
    }

    protected function deliveryStatus(?Topic $topic): string
    {
        if (! $topic || ! $topic->hasApprovedLessonPlan()) {
            return '—';
        }

        $log = $topic->latestCoverageLog;
        if ($topic->status === 'covered' || $log?->status === 'verified') {
            return 'Verified';
        }
        if ($log?->status === 'submitted') {
            return 'Reported';
        }
        if ($log?->status === 'rejected') {
            return 'Returned';
        }
        if ($topic->isLoggable()) {
            return 'Not delivered';
        }

        return '—';
    }

    /**
     * @return array{label: string, href: string}|array{}
     */
    protected function contextCta(?Topic $topic, bool $hasActive): array
    {
        if (! $hasActive || ! $topic) {
            return [];
        }

        $plan = $topic->latestLessonPlan;
        if ($plan?->status === 'rejected') {
            return [
                'label' => 'View Feedback',
                'href' => $plan->isEditable()
                    ? route('lesson-plans.edit', $plan)
                    : route('lesson-plans.show', $plan),
            ];
        }

        if ($plan && $plan->isEditable()) {
            return [
                'label' => 'Continue Planning',
                'href' => route('lesson-plans.edit', $plan),
            ];
        }

        if (! $topic->hasApprovedLessonPlan()) {
            if ($plan?->status === 'submitted') {
                return [];
            }

            return [
                'label' => 'Plan Lessons',
                'href' => route('lesson-plans.create', ['topic' => $topic->id]),
            ];
        }

        if ($topic->hasApprovedLessonPlan() && $topic->isLoggable()) {
            return [
                'label' => 'Record Delivery',
                'href' => route('coverage-logs.create', ['topic' => $topic->id]),
            ];
        }

        $approved = $topic->approvedLessonPlan();
        if ($approved) {
            return [
                'label' => 'View Approved Plan',
                'href' => route('lesson-plans.show', $approved),
            ];
        }

        return [];
    }

    protected function topicNeedsAction(Topic $topic): bool
    {
        $plan = $topic->latestLessonPlan;
        if ($plan?->status === 'submitted') {
            return false;
        }
        if ($topic->latestCoverageLog?->status === 'submitted') {
            return false;
        }

        return ! $topic->hasApprovedLessonPlan() || $topic->isLoggable();
    }

    protected function rejectionReason(?Topic $topic, ?SchemeOfWork $scheme): ?string
    {
        $plan = $topic?->latestLessonPlan;
        if ($plan?->status === 'rejected' && filled($plan->rejection_reason)) {
            return $plan->rejection_reason;
        }

        $log = $topic?->latestCoverageLog;
        if ($log?->status === 'rejected' && filled($log->rejection_reason)) {
            return $log->rejection_reason;
        }

        if ($scheme?->isReturned() && filled($scheme->rejection_reason)) {
            return $scheme->rejection_reason;
        }

        return null;
    }
}
