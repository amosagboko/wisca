<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\CharacterDomain;
use App\Models\CharacterRating;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Support\Collection;

class CharacterCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * CE-02: learners rated ≥ passing_level on ALL active domains ÷ enrolled.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'CE-02')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['enrolled'] === 0) {
            return null;
        }

        $rate        = $summary['rate'];
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id'              => $kpi->id,
                'academic_session_id' => $session->id,
                'term_id'             => $term->id,
                'school_class_id'     => null,
                'subject_id'          => null,
            ],
            [
                'target_value'     => $kpi->default_target,
                'actual_value'     => $rate,
                'achievement_rate' => $achievement,
                'status'           => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start'     => $term->start_date->toDateString(),
                'period_end'       => $term->end_date->toDateString(),
                'metadata'         => [
                    'passing'      => $summary['passing'],
                    'enrolled'     => $summary['enrolled'],
                    'domains_used' => $summary['domain_count'],
                ],
            ]
        );
    }

    /**
     * School-wide CE-02 summary for a session/term.
     */
    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $domains = CharacterDomain::where('school_id', $session->school_id)
            ->where('status', 'active')
            ->orderBy('display_order')
            ->get();

        $domainCount = $domains->count();
        $enrolled    = Learner::where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->count();

        if ($domainCount === 0 || $enrolled === 0) {
            return [
                'domain_count' => $domainCount,
                'enrolled'     => $enrolled,
                'passing'      => 0,
                'rate'         => 0.0,
                'domains'      => $domains,
            ];
        }

        // A learner passes CE-02 if they have a ≥ passing_level rating on ALL active domains
        $passing = $this->countPassingLearners($session, $term, $domains);

        return [
            'domain_count' => $domainCount,
            'enrolled'     => $enrolled,
            'passing'      => $passing,
            'rate'         => round($passing / $enrolled, 4),
            'domains'      => $domains,
        ];
    }

    /**
     * Per-class breakdown.
     */
    public function classSummaries(AcademicSession $session, ?Term $term, Collection $domains): Collection
    {
        if ($domains->isEmpty()) {
            return collect();
        }

        $classes = SchoolClass::where('school_id', $session->school_id)
            ->orderBy('name')->get();

        return $classes->map(function (SchoolClass $class) use ($session, $term, $domains) {
            $enrolled = Learner::where('school_class_id', $class->id)
                ->where('status', 'enrolled')->count();

            $passing = $this->countPassingLearners($session, $term, $domains, $class->id);

            return [
                'class'    => $class,
                'enrolled' => $enrolled,
                'passing'  => $passing,
                'rate'     => $enrolled > 0 ? round($passing / $enrolled, 4) : 0.0,
            ];
        })->filter(fn ($r) => $r['enrolled'] > 0)->values();
    }

    /**
     * Learner-level ratings for a class (for the marksheet view).
     * Returns: learner → [domain_id => CharacterRating|null]
     */
    public function classMarksheet(AcademicSession $session, ?Term $term, int $classId, Collection $domains): Collection
    {
        $learners = Learner::where('school_class_id', $classId)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        $ratings = CharacterRating::where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->whereIn('learner_id', $learners->pluck('id'))
            ->whereIn('character_domain_id', $domains->pluck('id'))
            ->get()
            ->groupBy('learner_id');

        return $learners->map(function (Learner $learner) use ($ratings, $domains) {
            $learnerRatings = $ratings->get($learner->id, collect())->keyBy('character_domain_id');

            $domainMap = $domains->mapWithKeys(fn ($d) => [$d->id => $learnerRatings->get($d->id)]);

            $allRated  = $domainMap->every(fn ($r) => $r !== null);
            $allPassed = $allRated && $domainMap->every(fn ($r, $domainId) => $r?->isPassing());

            return [
                'learner'    => $learner,
                'ratings'    => $domainMap,     // domain_id => CharacterRating|null
                'all_rated'  => $allRated,
                'all_passed' => $allPassed,
            ];
        });
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function countPassingLearners(
        AcademicSession $session,
        ?Term $term,
        Collection $domains,
        ?int $classId = null,
    ): int {
        $learnerIds = Learner::where('school_id', $session->school_id)
            ->where('status', 'enrolled')
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->pluck('id');

        if ($learnerIds->isEmpty()) {
            return 0;
        }

        $passing = 0;

        foreach ($learnerIds as $learnerId) {
            $allPass = true;

            foreach ($domains as $domain) {
                $rating = CharacterRating::where('learner_id', $learnerId)
                    ->where('character_domain_id', $domain->id)
                    ->where('academic_session_id', $session->id)
                    ->when($term, fn ($q) => $q->where('term_id', $term->id))
                    ->value('level');

                if ($rating === null || $rating < $domain->passing_level) {
                    $allPass = false;
                    break;
                }
            }

            if ($allPass) {
                $passing++;
            }
        }

        return $passing;
    }
}
