<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ExamResult;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Support\ExamPassMark;

class ExamCalculationService
{
    public const ASSESSMENT_KEY = 'term_exam';

    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-02')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $hasResults = ExamResult::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', self::ASSESSMENT_KEY)
            ->exists();

        if (! $hasResults) {
            return null;
        }

        $summary = $this->termSummary($session, $term, $kpi);
        if ($summary['enrolled'] === 0) {
            return null;
        }

        $rate = $summary['rate'];
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);

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
                'period_start' => $term->start_date->toDateString(),
                'period_end' => $term->end_date->toDateString(),
                'metadata' => [
                    'passed' => $summary['passed'],
                    'enrolled' => $summary['enrolled'],
                    'pass_mark' => $summary['pass_mark'],
                    'sittings' => $summary['sittings'],
                ],
            ]
        );
    }

    public function termSummary(AcademicSession $session, Term $term, ?Kpi $kpi = null): array
    {
        $passMark = ExamPassMark::percent($kpi);
        $assignments = TeacherAssignment::where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $session->school_id))
            ->get(['school_class_id', 'subject_id']);

        $classIds = $assignments->pluck('school_class_id')->unique()->values()->all();
        $enrolledByClass = $this->enrolledCountsByClass($session, $classIds);
        $passedByPair = ExamResult::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', self::ASSESSMENT_KEY)
            ->where('score', '>=', $passMark)
            ->when($classIds !== [], fn ($query) => $query->whereIn('school_class_id', $classIds))
            ->selectRaw('school_class_id, subject_id, COUNT(*) as passed')
            ->groupBy('school_class_id', 'subject_id')
            ->get()
            ->keyBy(fn ($row) => $row->school_class_id.'-'.$row->subject_id);

        $enrolled = 0;
        $passed = 0;
        $sittings = 0;

        foreach ($assignments as $assignment) {
            $roll = (int) ($enrolledByClass[$assignment->school_class_id] ?? 0);
            if ($roll === 0) {
                continue;
            }

            $sittings++;
            $enrolled += $roll;
            $passed += (int) ($passedByPair[$assignment->school_class_id.'-'.$assignment->subject_id]->passed ?? 0);
        }

        return [
            'enrolled' => $enrolled,
            'passed' => $passed,
            'rate' => $enrolled > 0 ? round($passed / $enrolled, 4) : 0.0,
            'pass_mark' => $passMark,
            'sittings' => $sittings,
        ];
    }

    /**
     * Lightweight sitting stats for a set of assignments (no class-roll models).
     *
     * @param  \Illuminate\Support\Collection<int, TeacherAssignment>  $assignments
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function sittingOverviews(AcademicSession $session, Term $term, $assignments)
    {
        $assignments = collect($assignments);
        $passMark = ExamPassMark::percent();
        $classIds = $assignments->pluck('school_class_id')->unique()->values()->all();
        $enrolledByClass = $this->enrolledCountsByClass($session, $classIds);

        $stats = ExamResult::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', self::ASSESSMENT_KEY)
            ->when($classIds !== [], fn ($query) => $query->whereIn('school_class_id', $classIds))
            ->selectRaw(
                'school_class_id, subject_id, COUNT(*) as recorded,
                SUM(CASE WHEN score >= '.$passMark.' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN status = \'rejected\' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN status = \'verified\' THEN 1 ELSE 0 END) as verified_count,
                MAX(CASE WHEN status = \'rejected\' THEN rejection_reason ELSE NULL END) as rejection_reason'
            )
            ->groupBy('school_class_id', 'subject_id')
            ->get()
            ->keyBy(fn ($row) => $row->school_class_id.'-'.$row->subject_id);

        return $assignments->map(function (TeacherAssignment $assignment) use ($enrolledByClass, $stats, $passMark) {
            $key = $assignment->school_class_id.'-'.$assignment->subject_id;
            $row = $stats->get($key);
            $enrolled = (int) ($enrolledByClass[$assignment->school_class_id] ?? 0);
            $recorded = (int) ($row->recorded ?? 0);
            $passed = (int) ($row->passed ?? 0);
            $reviewStatus = $this->sittingReviewStatusFromCounts(
                $enrolled,
                $recorded,
                (int) ($row->rejected_count ?? 0),
                (int) ($row->verified_count ?? 0),
            );

            return [
                'assignment' => $assignment,
                'enrolled' => $enrolled,
                'recorded' => $recorded,
                'passed' => $passed,
                'rate' => $enrolled > 0 ? round($passed / $enrolled, 4) : 0.0,
                'pass_mark' => $passMark,
                'review_status' => $reviewStatus,
                'rejection_reason' => $row->rejection_reason ?? null,
                'locked' => $reviewStatus === 'verified',
            ];
        })->values();
    }

    /**
     * @param  list<int>  $classIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function enrolledCountsByClass(AcademicSession $session, array $classIds)
    {
        if ($classIds === []) {
            return collect();
        }

        return Learner::query()
            ->where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->whereIn('school_class_id', $classIds)
            ->selectRaw('school_class_id, COUNT(*) as enrolled')
            ->groupBy('school_class_id')
            ->pluck('enrolled', 'school_class_id');
    }

    public function sittingReviewStatusFromCounts(int $enrolled, int $recorded, int $rejectedCount, int $verifiedCount): string
    {
        if ($enrolled <= 0 || $recorded < $enrolled) {
            return 'incomplete';
        }

        if ($rejectedCount > 0) {
            return 'rejected';
        }

        if ($recorded > 0 && $verifiedCount >= $recorded) {
            return 'verified';
        }

        return 'submitted';
    }

    public function sittingSummary(AcademicSession $session, Term $term, int $classId, int $subjectId): array
    {
        $passMark = ExamPassMark::percent();
        $learners = Learner::where('school_class_id', $classId)
            ->where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        $results = ExamResult::query()
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', self::ASSESSMENT_KEY)
            ->get()
            ->keyBy('learner_id');

        $passed = $learners->filter(function (Learner $learner) use ($results, $passMark) {
            $result = $results->get($learner->id);

            return $result && $result->score >= $passMark;
        })->count();

        $recorded = $learners->filter(fn (Learner $learner) => $results->has($learner->id))->count();
        $enrolledResults = $learners->map(fn (Learner $learner) => $results->get($learner->id))->filter();
        $reviewStatus = $this->sittingReviewStatus($enrolledResults, $learners->count(), $recorded);

        return [
            'learners' => $learners,
            'results' => $results,
            'enrolled' => $learners->count(),
            'recorded' => $recorded,
            'passed' => $passed,
            'rate' => $learners->count() > 0 ? round($passed / $learners->count(), 4) : 0.0,
            'pass_mark' => $passMark,
            'review_status' => $reviewStatus,
            'rejection_reason' => $enrolledResults->first(fn ($result) => $result->status === 'rejected')?->rejection_reason,
            'locked' => $reviewStatus === 'verified',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExamResult>  $results
     */
    public function sittingReviewStatus($results, int $enrolled, int $recorded): string
    {
        if ($enrolled <= 0 || $recorded < $enrolled) {
            return 'incomplete';
        }

        $results = collect($results);
        if ($results->contains(fn (ExamResult $result) => $result->status === 'rejected')) {
            return 'rejected';
        }

        if ($results->isNotEmpty() && $results->every(fn (ExamResult $result) => $result->status === 'verified')) {
            return 'verified';
        }

        return 'submitted';
    }
}
