<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchemeOfWork;
use App\Models\Term;
use App\Models\Topic;
use App\Models\TopicCatchUpPlan;
use App\Models\TopicCoverageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TopicCatchUpService
{
    public function __construct(
        protected AcademicPeriodService $periods,
        protected SchemeOfWorkService $schemes,
        protected CurriculumCoverageKpiService $kpis,
        protected HodScope $scope,
        protected AcademicReportingPeriod $reporting,
    ) {}

    public function behindWeek(Term $term, int $selectedWeek = 0): int
    {
        if ($selectedWeek > 0) {
            return $selectedWeek;
        }

        return $this->reporting->weekNumber($term, 40);
    }

    /**
     * Active SoW topics earlier than the instructional week that still lack verified coverage and an identified catch-up.
     */
    public function neededQuery(AcademicSession $session, Term $term, int $behindWeek, ?User $hod = null): Builder
    {
        return Topic::query()
            ->where('week_number', '<', max(1, $behindWeek))
            ->whereHas('schemeOfWork', function (Builder $query) use ($session, $term, $hod) {
                $query->where('academic_session_id', $session->id)
                    ->where('term_id', $term->id)
                    ->where('status', 'active')
                    ->whereHas('schoolClass', fn (Builder $class) => $class->where('school_id', $session->school_id));
                if ($hod) {
                    $this->scope->constrainBySubject($query, $hod);
                }
            })
            ->whereDoesntHave('coverageLogs', fn (Builder $query) => $query->where('status', 'verified'))
            ->where(function (Builder $query) {
                $query->whereDoesntHave('catchUpPlan')
                    ->orWhereHas('catchUpPlan', fn (Builder $plan) => $plan->where('status', TopicCatchUpPlan::STATUS_CANCELLED));
            });
    }

    public function open(User $hod, Topic $topic, ?string $notes = null, ?int $targetWeek = null): TopicCatchUpPlan
    {
        abort_unless($hod->isHoD(), 403, 'Only a Head of Department can open a catch-up plan.');

        $topic->loadMissing(['schemeOfWork.subject', 'schemeOfWork.academicSession', 'schemeOfWork.term', 'coverageLogs.verifier']);
        $scheme = $topic->schemeOfWork;
        abort_unless($scheme, 422, 'This topic is not attached to a scheme of work.');
        abort_unless((int) $scheme->subject?->school_id === (int) $hod->school_id, 403);

        if (! $this->schemes->hodCanTargetSubject($hod, $scheme->subject)) {
            abort(403, 'You cannot open catch-up for this subject.');
        }

        if (! $scheme->isActive()) {
            throw ValidationException::withMessages([
                'topic_id' => 'Catch-up can only be opened on a topic from an Active Scheme of Work.',
            ]);
        }

        $this->periods->assertAcceptsNewActivity($scheme->academicSession, $scheme->term);

        if ($this->kpis->hasHodVerifiedCoverage($topic)) {
            throw ValidationException::withMessages([
                'topic_id' => 'This topic already has HOD-verified coverage.',
            ]);
        }

        $existing = TopicCatchUpPlan::query()->where('topic_id', $topic->id)->first();

        if ($existing?->isOpen()) {
            throw ValidationException::withMessages([
                'topic_id' => 'A catch-up plan is already open for this topic.',
            ]);
        }

        if ($existing?->isAddressed()) {
            throw ValidationException::withMessages([
                'topic_id' => 'This topic already has an addressed catch-up plan.',
            ]);
        }

        $plan = $existing ?? new TopicCatchUpPlan(['topic_id' => $topic->id]);
        $plan->fill([
            'opened_by' => $hod->id,
            'opened_at' => now(),
            'notes' => $notes,
            'target_week_number' => $targetWeek,
            'status' => TopicCatchUpPlan::STATUS_OPEN,
            'addressed_at' => null,
            'addressed_by' => null,
            'coverage_log_id' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);
        $plan->save();

        $this->recalculate($scheme);

        return $plan;
    }

    public function cancel(User $hod, TopicCatchUpPlan $plan): TopicCatchUpPlan
    {
        abort_unless($hod->isHoD(), 403, 'Only a Head of Department can cancel a catch-up plan.');

        $plan->loadMissing(['topic.schemeOfWork.subject', 'topic.schemeOfWork.academicSession', 'topic.schemeOfWork.term']);
        $scheme = $plan->topic?->schemeOfWork;
        abort_unless($scheme, 422, 'This catch-up is not attached to a scheme of work.');
        abort_unless((int) $scheme->subject?->school_id === (int) $hod->school_id, 403);

        if (! $this->schemes->hodCanTargetSubject($hod, $scheme->subject)) {
            abort(403, 'You cannot cancel catch-up for this subject.');
        }

        if (! $plan->isOpen()) {
            throw ValidationException::withMessages([
                'topic_id' => 'Only an open catch-up plan can be cancelled.',
            ]);
        }

        $this->periods->assertAcceptsNewActivity($scheme->academicSession, $scheme->term);

        $plan->update([
            'status' => TopicCatchUpPlan::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $hod->id,
        ]);

        $this->recalculate($scheme);

        return $plan->fresh();
    }

    public function markAddressedFromVerifiedLog(TopicCoverageLog $log): void
    {
        $plan = TopicCatchUpPlan::query()
            ->where('topic_id', $log->topic_id)
            ->where('status', TopicCatchUpPlan::STATUS_OPEN)
            ->first();

        if (! $plan) {
            return;
        }

        $plan->update([
            'status' => TopicCatchUpPlan::STATUS_ADDRESSED,
            'addressed_at' => now(),
            'addressed_by' => $log->verified_by,
            'coverage_log_id' => $log->id,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);
    }

    protected function recalculate(SchemeOfWork $scheme): void
    {
        $scheme->loadMissing(['academicSession', 'term']);
        if ($scheme->academicSession && $scheme->term) {
            $this->kpis->recalculateForTerm($scheme->academicSession, $scheme->term);
        }
    }
}
