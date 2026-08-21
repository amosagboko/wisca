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
            ->get();

        $enrolled = 0;
        $passed = 0;
        $sittings = 0;

        foreach ($assignments as $assignment) {
            $roll = Learner::where('school_class_id', $assignment->school_class_id)
                ->where('school_id', $session->school_id)
                ->where('status', 'enrolled')
                ->pluck('id');

            if ($roll->isEmpty()) {
                continue;
            }

            $sittings++;
            $enrolled += $roll->count();

            $passed += ExamResult::query()
                ->whereIn('learner_id', $roll)
                ->where('subject_id', $assignment->subject_id)
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id)
                ->where('assessment_key', self::ASSESSMENT_KEY)
                ->where('score', '>=', $passMark)
                ->count();
        }

        return [
            'enrolled' => $enrolled,
            'passed' => $passed,
            'rate' => $enrolled > 0 ? round($passed / $enrolled, 4) : 0.0,
            'pass_mark' => $passMark,
            'sittings' => $sittings,
        ];
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

        return [
            'learners' => $learners,
            'results' => $results,
            'enrolled' => $learners->count(),
            'recorded' => $recorded,
            'passed' => $passed,
            'rate' => $learners->count() > 0 ? round($passed / $learners->count(), 4) : 0.0,
            'pass_mark' => $passMark,
        ];
    }
}
