<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\ExamResult;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\Term;
use App\Models\User;
use App\Support\AtRiskCriteria;
use App\Support\ExamPassMark;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AtRiskCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-07')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->monthSummary($session, $term);
        if ($summary['identified'] === 0) {
            return null;
        }

        $rate = $summary['rate'];
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);
        $window = $this->monthWindow($term);

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'school_class_id' => null,
                'subject_id' => null,
            ],
            [
                'target_value' => $kpi->default_target,
                'actual_value' => $rate,
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start' => $window['start']->toDateString(),
                'period_end' => $window['end']->toDateString(),
                'metadata' => [
                    'identified' => $summary['identified'],
                    'with_plan' => $summary['with_plan'],
                    'without_plan' => $summary['without_plan'],
                ],
            ]
        );
    }

    public function monthSummary(AcademicSession $session, Term $term, ?array $classIds = null, bool $includeRecords = true): array
    {
        $query = $this->openQuery($session, $classIds);
        $identified = (clone $query)->count();
        $withPlan = (clone $query)->whereHas('plans', fn ($plans) => $this->activeSupportPlan($plans))->count();
        $withoutPlan = max(0, $identified - $withPlan);

        return [
            'identified' => $identified,
            'with_plan' => $withPlan,
            'without_plan' => $withoutPlan,
            'rate' => $identified > 0 ? round($withPlan / $identified, 4) : 0.0,
            'records' => $includeRecords ? $this->openRecords($session, $term, $classIds) : collect(),
        ];
    }

    public function openRecords(AcademicSession $session, ?Term $term = null, ?array $classIds = null): Collection
    {
        return $this->openQuery($session, $classIds)
            ->with(['learner', 'schoolClass', 'identifier', 'plans.coordinator'])
            ->latest('identification_date')
            ->get();
    }

    public function withoutPlanRecords(AcademicSession $session, ?array $classIds = null, int $limit = 10): Collection
    {
        return $this->openQuery($session, $classIds)
            ->whereDoesntHave('plans', fn ($plans) => $this->activeSupportPlan($plans))
            ->with(['learner', 'schoolClass', 'identifier', 'plans.coordinator'])
            ->latest('identification_date')
            ->limit($limit)
            ->get();
    }

    protected function openQuery(AcademicSession $session, ?array $classIds = null): Builder
    {
        return AtRiskLearner::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->when($classIds !== null, fn ($query) => $query->whereIn('school_class_id', $classIds ?: [0]))
            ->whereHas('learner', fn ($query) => $query->where('school_id', $session->school_id));
    }

    protected function activeSupportPlan(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereIn('plan_type', [AtRiskCriteria::TIER_2, AtRiskCriteria::TIER_3]);
    }

    public function belowPassUnflagged(AcademicSession $session, Term $term, ?array $classIds = null): Collection
    {
        $passMark = ExamPassMark::percent();
        $flaggedIds = AtRiskLearner::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->pluck('learner_id');

        $results = ExamResult::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', ExamCalculationService::ASSESSMENT_KEY)
            ->where('status', 'verified')
            ->where('score', '<', $passMark)
            ->when($classIds !== null, fn ($query) => $query->whereIn('school_class_id', $classIds ?: [0]))
            ->whereNotIn('learner_id', $flaggedIds->all() ?: [0])
            ->whereHas('learner', fn ($query) => $query
                ->where('school_id', $session->school_id)
                ->where('status', 'enrolled'))
            ->with(['learner.schoolClass', 'subject'])
            ->orderBy('score')
            ->get();

        return $results->groupBy('learner_id')->map(function (Collection $rows) use ($passMark) {
            $first = $rows->first();

            return [
                'learner' => $first->learner,
                'pass_mark' => $passMark,
                'sittings' => $rows->map(fn (ExamResult $row) => [
                    'subject' => $row->subject?->name,
                    'score' => $row->score,
                ])->values(),
                'lowest' => (float) $rows->min('score'),
            ];
        })->values();
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function monthWindow(Term $term): array
    {
        $today = now()->startOfDay();
        $termStart = $term->start_date->copy()->startOfDay();
        $termEnd = $term->end_date->copy()->startOfDay();

        if ($today->gt($termEnd)) {
            $cursor = $termEnd;
        } elseif ($today->lt($termStart)) {
            $cursor = $termStart;
        } else {
            $cursor = $today;
        }

        $start = $cursor->copy()->startOfMonth();
        $end = $cursor->copy()->endOfMonth();

        if ($start->lt($termStart)) {
            $start = $termStart->copy();
        }
        if ($end->gt($termEnd)) {
            $end = $termEnd->copy()->endOfDay();
        }

        return compact('start', 'end');
    }

    public function flagBelowPassFromSitting(
        User $actor,
        AcademicSession $session,
        Term $term,
        int $classId,
        int $subjectId,
    ): int {
        $passMark = ExamPassMark::percent();
        $results = ExamResult::query()
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', ExamCalculationService::ASSESSMENT_KEY)
            ->where('status', 'verified')
            ->where('score', '<', $passMark)
            ->with('learner')
            ->get();

        $flagged = 0;

        foreach ($results as $result) {
            $learner = $result->learner;
            if (! $learner || $learner->status !== 'enrolled') {
                continue;
            }

            $record = AtRiskLearner::firstOrNew([
                'learner_id' => $learner->id,
                'academic_session_id' => $session->id,
            ]);
            $alreadyActive = $record->exists && $record->status === 'active';
            $factors = collect($record->risk_factors ?? [])
                ->push(AtRiskCriteria::BELOW_PASS_MARK)
                ->unique()
                ->values()
                ->all();

            $record->fill([
                'school_class_id' => $learner->school_class_id,
                'term_id' => $term->id,
                'identified_by' => $record->identified_by ?: $actor->id,
                'identification_date' => $record->identification_date ?: now()->toDateString(),
                'risk_factors' => $factors,
                'risk_level' => $record->risk_level ?: 'medium',
                'status' => 'active',
                'resolved_by' => null,
                'resolved_at' => null,
                'concern_note' => $record->concern_note ?: 'Auto-flagged from a verified marksheet ('.$result->score.'%, pass mark '.(int) $passMark.'%).',
            ])->save();

            if (! $alreadyActive) {
                $flagged++;
            }
        }

        if ($flagged > 0) {
            $this->recalculateForSession($session, $term);
        }

        return $flagged;
    }

    public function eligibleLearners(AcademicSession $session, ?array $classIds = null): Collection
    {
        $flaggedIds = AtRiskLearner::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->pluck('learner_id');

        return Learner::query()
            ->where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->when($classIds !== null, fn ($query) => $query->whereIn('school_class_id', $classIds ?: [0]))
            ->whereNotIn('id', $flaggedIds->all() ?: [0])
            ->with('schoolClass')
            ->orderBy('name')
            ->get();
    }
}
