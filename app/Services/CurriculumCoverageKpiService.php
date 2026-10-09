<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\LessonPlan;
use App\Models\SchemeOfWork;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\TopicCatchUpPlan;
use App\Models\TopicCoverageLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CurriculumCoverageKpiService
{
    public const AE01_1A = 'ae-01.1a';

    public const AE01_1B = 'ae-01.1b';

    public const AE01_2_PROXY = 'ae-01.2-proxy';

    public const AE01_3_WEEKLY = 'ae-01.3-weekly';

    public const AE01_3_MIDTERM = 'ae-01.3-midterm';

    public const AE01_4 = 'ae-01.4';

    public const AE05_1 = 'ae-05.1';

    public const AE05_2 = 'ae-05.2';

    public const AE05_3 = 'ae-05.3';

    public function __construct(
        protected AcademicReportingPeriod $periods,
        protected KpiStatusEvaluator $evaluator,
        protected LessonPlanReview $lessonPlanReview,
    ) {}

    public function recalculateForTerm(AcademicSession $session, Term $term): void
    {
        $this->recalculateSoWCompleteness($session, $term);
        $maxWeek = $this->periods->maxWeekForTerm($term);
        $week = $this->periods->current($term, $maxWeek);
        $this->recalculateWeeklyMeasures($session, $term, $week['week_number']);
        $this->recalculateMidTerm($session, $term);
        $this->recalculateCatchUp($session, $term);
        $this->recalculateLessonPlanSubmeasures($session, $term, $week['week_number']);
    }

    public function recalculateSoWCompleteness(AcademicSession $session, Term $term): void
    {
        $kpi = Kpi::where('code', 'AE-01')->first();
        if (! $kpi) {
            return;
        }

        $keys = TeacherAssignment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->get(['school_class_id', 'subject_id'])
            ->unique(fn ($row) => $row->school_class_id.'-'.$row->subject_id)
            ->values();

        if ($keys->isEmpty()) {
            $keys = SchemeOfWork::query()
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id)
                ->get(['school_class_id', 'subject_id'])
                ->unique(fn ($row) => $row->school_class_id.'-'.$row->subject_id)
                ->values();
        }

        $complete = 0;
        foreach ($keys as $key) {
            $scheme = SchemeOfWork::query()
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id)
                ->where('school_class_id', $key->school_class_id)
                ->where('subject_id', $key->subject_id)
                ->where('status', 'active')
                ->with('topics')
                ->first();

            $ok = $scheme && $this->schemeHasWeeklyLos($scheme);
            if ($ok) {
                $complete++;
            }

            $this->write($kpi, $session, $term, self::AE01_1A, 1.0, $ok ? 1.0 : 0.0, $term->start_date, $term->end_date, [
                'complete' => $ok,
                'scheme_id' => $scheme?->id,
            ], (int) $key->school_class_id, (int) $key->subject_id);
        }

        $required = $keys->count();
        if ($required === 0) {
            return;
        }

        $this->write($kpi, $session, $term, self::AE01_1A, 1.0, round($complete / $required, 4), $term->start_date, $term->end_date, [
            'required' => $required,
            'complete' => $complete,
            'rollup' => 'school',
        ]);
    }

    public function recalculateWeeklyMeasures(AcademicSession $session, Term $term, int $weekNumber): void
    {
        $ae01 = Kpi::where('code', 'AE-01')->first();
        if (! $ae01) {
            return;
        }

        $window = $this->periods->window($term, $weekNumber);
        $schemes = $this->activeSchemes($session, $term);

        foreach ($schemes as $scheme) {
            $topics = $scheme->topics->where('week_number', $weekNumber)->values();
            $denominator = $topics->count();
            if ($denominator === 0) {
                continue;
            }

            $plannedOnTime = $topics->filter(fn (Topic $topic) => $this->hasOnTimePlan($topic))->count();
            $verified = $topics->filter(fn (Topic $topic) => $this->hasHodVerifiedCoverage($topic))->count();

            $this->write($ae01, $session, $term, self::AE01_1B, 1.0, round($plannedOnTime / $denominator, 4), $window['start'], $window['end'], [
                'week_number' => $weekNumber,
                'denominator' => $denominator,
                'numerator' => $plannedOnTime,
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);

            $this->write($ae01, $session, $term, self::AE01_2_PROXY, 0.95, round($verified / $denominator, 4), $window['start'], $window['end'], [
                'week_number' => $weekNumber,
                'denominator' => $denominator,
                'numerator' => $verified,
                'calculation_method' => 'proxy',
                'label' => 'Coverage proxy — not taught within scheduled class time',
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);

            $this->write($ae01, $session, $term, self::AE01_3_WEEKLY, 1.0, round($verified / $denominator, 4), $window['start'], $window['end'], [
                'week_number' => $weekNumber,
                'denominator' => $denominator,
                'numerator' => $verified,
                'cadence' => 'weekly',
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);
        }
    }

    public function recalculateMidTerm(AcademicSession $session, Term $term): void
    {
        $ae01 = Kpi::where('code', 'AE-01')->first();
        if (! $ae01) {
            return;
        }

        foreach ($this->activeSchemes($session, $term) as $scheme) {
            $topics = $scheme->topics;
            $maxWeek = max(1, (int) $topics->max('week_number'));
            $mid = $this->periods->midTermWeek($maxWeek);
            $window = $this->periods->window($term, $mid);
            $denominator = $topics->count();
            if ($denominator === 0) {
                continue;
            }

            $verified = $topics->filter(fn (Topic $topic) => $this->hasHodVerifiedCoverage($topic))->count();

            $this->write($ae01, $session, $term, self::AE01_3_MIDTERM, 1.0, round($verified / $denominator, 4), $term->start_date, $window['end'], [
                'cadence' => 'mid_term',
                'mid_term_week' => $mid,
                'denominator' => $denominator,
                'numerator' => $verified,
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);
        }
    }

    public function recalculateCatchUp(AcademicSession $session, Term $term): void
    {
        $ae01 = Kpi::where('code', 'AE-01')->first();
        if (! $ae01) {
            return;
        }

        $identifiedTotal = 0;
        $addressedTotal = 0;

        foreach ($this->activeSchemes($session, $term) as $scheme) {
            $topicIds = $scheme->topics->pluck('id');
            if ($topicIds->isEmpty()) {
                continue;
            }

            $plans = TopicCatchUpPlan::query()
                ->whereIn('topic_id', $topicIds)
                ->identified()
                ->get();

            $identified = $plans->count();
            if ($identified === 0) {
                KpiPeriodicData::query()
                    ->where('kpi_id', $ae01->id)
                    ->where('measure_key', self::AE01_4)
                    ->where('academic_session_id', $session->id)
                    ->where('term_id', $term->id)
                    ->where('school_class_id', $scheme->school_class_id)
                    ->where('subject_id', $scheme->subject_id)
                    ->delete();

                continue;
            }

            $addressed = $plans->where('status', TopicCatchUpPlan::STATUS_ADDRESSED)->count();
            $identifiedTotal += $identified;
            $addressedTotal += $addressed;

            $this->write($ae01, $session, $term, self::AE01_4, 1.0, round($addressed / $identified, 4), $term->start_date, $term->end_date, [
                'cadence' => 'monthly',
                'identified' => $identified,
                'addressed' => $addressed,
                'open' => $identified - $addressed,
                'label' => 'Identified untaught topics addressed through catch-up',
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);
        }

        if ($identifiedTotal === 0) {
            KpiPeriodicData::query()
                ->where('kpi_id', $ae01->id)
                ->where('measure_key', self::AE01_4)
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id)
                ->whereNull('school_class_id')
                ->whereNull('subject_id')
                ->delete();

            return;
        }

        $this->write($ae01, $session, $term, self::AE01_4, 1.0, round($addressedTotal / $identifiedTotal, 4), $term->start_date, $term->end_date, [
            'cadence' => 'monthly',
            'identified' => $identifiedTotal,
            'addressed' => $addressedTotal,
            'open' => $identifiedTotal - $addressedTotal,
            'rollup' => 'school',
            'label' => 'Identified untaught topics addressed through catch-up',
        ]);
    }

    public function recalculateLessonPlanSubmeasures(AcademicSession $session, Term $term, int $weekNumber): void
    {
        $kpi = Kpi::where('code', 'AE-05')->first();
        if (! $kpi) {
            return;
        }

        $window = $this->periods->window($term, $weekNumber);

        foreach ($this->activeSchemes($session, $term) as $scheme) {
            $topics = $scheme->topics->where('week_number', $weekNumber)->values();
            $required = $topics->count();
            if ($required === 0) {
                continue;
            }

            $submittedOnTime = $topics->filter(function (Topic $topic) {
                return $topic->lessonPlans
                    ->whereIn('status', ['submitted', 'approved'])
                    ->where('on_time', true)
                    ->isNotEmpty();
            })->count();

            $reviewed = $topics->filter(function (Topic $topic) {
                return $topic->lessonPlans
                    ->whereIn('status', ['approved', 'rejected'])
                    ->contains(fn (LessonPlan $plan) => $this->lessonPlanReview->planHasCompleteChecklist($plan));
            })->count();

            $approved = $topics->filter(fn (Topic $topic) => $topic->hasApprovedLessonPlan())->count();

            $submittedPlans = $topics->flatMap->lessonPlans->where('status', '!=', 'draft');
            $slaOk = $submittedPlans->filter(function (LessonPlan $plan) {
                if (! in_array($plan->status, ['approved', 'rejected'], true) || ! $plan->submitted_at || ! $plan->approved_at) {
                    return false;
                }

                return $plan->approved_at->lte($plan->submitted_at->copy()->addDay());
            })->count();
            $submittedCount = $submittedPlans->whereIn('status', ['submitted', 'approved', 'rejected'])->count();

            $this->write($kpi, $session, $term, self::AE05_1, 0.95, round($submittedOnTime / $required, 4), $window['start'], $window['end'], [
                'week_number' => $weekNumber,
                'required' => $required,
                'submitted_on_time' => $submittedOnTime,
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);

            $this->write($kpi, $session, $term, self::AE05_2, 1.0, round($reviewed / $required, 4), $window['start'], $window['end'], [
                'week_number' => $weekNumber,
                'required' => $required,
                'reviewed' => $reviewed,
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);

            $slaRate = $submittedCount > 0 ? round($slaOk / $submittedCount, 4) : ($approved > 0 ? round($approved / $required, 4) : 0.0);
            $this->write($kpi, $session, $term, self::AE05_3, 0.95, $slaRate, $window['start'], $window['end'], [
                'week_number' => $weekNumber,
                'approved' => $approved,
                'sla_within_24h' => $slaOk,
                'submitted' => $submittedCount,
            ], (int) $scheme->school_class_id, (int) $scheme->subject_id);
        }
    }

    /**
     * @return Collection<int, SchemeOfWork>
     */
    public function activeSchemes(AcademicSession $session, Term $term): Collection
    {
        return SchemeOfWork::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->with(['topics.lessonPlans', 'topics.coverageLogs.verifier'])
            ->get();
    }

    public function weekTopics(SchemeOfWork $scheme, int $weekNumber): Collection
    {
        $scheme->loadMissing('topics');

        return $scheme->topics->where('week_number', $weekNumber)->values();
    }

    public function missedTopics(SchemeOfWork $scheme): Collection
    {
        $scheme->loadMissing(['topics.coverageLogs.verifier']);

        return $scheme->topics
            ->filter(fn (Topic $topic) => ! $this->hasHodVerifiedCoverage($topic))
            ->values();
    }

    public function hasHodVerifiedCoverage(Topic $topic): bool
    {
        $logs = $topic->relationLoaded('coverageLogs')
            ? $topic->coverageLogs
            : $topic->coverageLogs()->with('verifier')->get();

        return $logs->contains(function (TopicCoverageLog $log) {
            return $log->status === 'verified' && $log->verifier instanceof User && $log->verifier->isHoD();
        });
    }

    protected function hasOnTimePlan(Topic $topic): bool
    {
        $plans = $topic->relationLoaded('lessonPlans') ? $topic->lessonPlans : $topic->lessonPlans;

        return $plans
            ->whereIn('status', ['submitted', 'approved'])
            ->where('on_time', true)
            ->isNotEmpty();
    }

    protected function schemeHasWeeklyLos(SchemeOfWork $scheme): bool
    {
        if ($scheme->topics->isEmpty()) {
            return false;
        }

        return $scheme->topics->every(function (Topic $topic) {
            $objectives = array_values(array_filter(array_map('trim', (array) $topic->learning_objectives)));

            return $topic->week_number >= 1 && trim((string) $topic->title) !== '' && $objectives !== [];
        });
    }

    protected function write(
        Kpi $kpi,
        AcademicSession $session,
        Term $term,
        string $measureKey,
        float $target,
        float $actual,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $metadata,
        ?int $classId = null,
        ?int $subjectId = null,
    ): void {
        $this->evaluator->forSchool((int) $session->school_id);
        $achievement = $this->evaluator->achievementRate($actual, $target);

        KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'measure_key' => $measureKey,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'school_class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => null,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            [
                'target_value' => $target,
                'actual_value' => $actual,
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'metadata' => $metadata,
            ]
        );
    }
}
