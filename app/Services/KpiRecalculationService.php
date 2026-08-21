<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchemeOfWork;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KpiRecalculationService
{
    public function __construct(
        protected CoverageCalculationService $coverage,
        protected HomeworkCalculationService $homework,
        protected AttendanceCalculationService $attendance,
        protected LessonPlanCalculationService $lessonPlans,
        protected ExamCalculationService $exams,
        protected ObservationCalculationService $observations,
        protected AtRiskCalculationService $atRisk,
        protected ReadingCalculationService $reading,
        protected ChapelCalculationService $chapel,
        protected CharacterCalculationService $character,
        protected ServiceHoursCalculationService $serviceHours,
        protected RestorativeDisciplineCalculationService $discipline,
        protected BullyingCaseCalculationService $bullying,
        protected ScriptureCalculationService $scripture,
        protected PartnershipCalculationService $partnership,
        protected LmsAdoptionCalculationService $lms,
        protected StemProjectCalculationService $stem,
        protected DigitalEthicsCalculationService $ethics,
        protected DigitalCompetencyCalculationService $competency,
        protected EAssessmentCalculationService $eAssessment,
        protected ParentPortalCalculationService $parentPortal,
    ) {}

    /**
     * @return array<int, string>
     */
    public function recalculate(AcademicSession $session, string $frequency = 'all'): array
    {
        $term = Term::currentForSession($session->id);
        $done = [];
        $frequency = strtolower($frequency);

        $run = function (string $label, callable $fn) use (&$done): void {
            $fn();
            $done[] = $label;
        };

        $match = fn (string ...$keys) => $frequency === 'all' || in_array($frequency, $keys, true);

        if ($match('fortnightly', 'ae-01') && $term) {
            $run('AE-01', function () use ($session, $term) {
                $schemes = SchemeOfWork::query()
                    ->where('academic_session_id', $session->id)
                    ->where('term_id', $term->id)
                    ->whereIn('status', ['active', 'approved'])
                    ->get();

                foreach ($schemes as $scheme) {
                    $this->coverage->recalculateForScheme($scheme);
                }
            });
        }

        if ($match('weekly', 'ae-03') && $term) {
            $run('AE-03', fn () => $this->homework->recalculateForSession($session, $term));
        }

        if ($match('weekly', 'ae-04') && $term) {
            $run('AE-04', fn () => $this->attendance->recalculateForSession($session, $term));
        }

        if ($match('weekly', 'ae-05') && $term) {
            $run('AE-05', fn () => $this->lessonPlans->recalculateForSession($session, $term));
        }

        if ($match('termly', 'ae-02') && $term) {
            $run('AE-02', fn () => $this->exams->recalculateForSession($session, $term));
        }

        if ($match('termly', 'ae-06') && $term) {
            $run('AE-06', fn () => $this->observations->recalculateForSession($session, $term));
        }

        if ($match('monthly', 'ae-07') && $term) {
            $run('AE-07', fn () => $this->atRisk->recalculateForSession($session, $term));
        }

        if ($match('termly', 'ae-08') && $term) {
            $run('AE-08', fn () => $this->reading->recalculateForSession($session, $term));
        }

        if ($match('daily', 'ce-01') && $term) {
            $run('CE-01', fn () => $this->chapel->recalculateForSession($session, $term));
        }

        if ($match('termly', 'ce-02') && $term) {
            $run('CE-02', fn () => $this->character->recalculateForSession($session, $term));
        }

        if ($match('termly', 'ce-03') && $term) {
            $run('CE-03', fn () => $this->serviceHours->recalculateForSession($session, $term));
        }

        if ($match('monthly', 'ce-04') && $term) {
            $run('CE-04', fn () => $this->discipline->recalculateForSession($session, $term));
        }

        if ($match('monthly', 'ce-05') && $term) {
            $run('CE-05', fn () => $this->bullying->recalculateForSession($session, $term));
        }

        if ($match('termly', 'ce-06') && $term) {
            $run('CE-06', fn () => $this->scripture->recalculateForSession($session, $term));
        }

        if ($match('termly', 'ce-07') && $term) {
            $run('CE-07', fn () => $this->partnership->recalculateForSession($session, $term));
        }

        if ($match('weekly', 'di-01') && $term) {
            $run('DI-01', function () use ($session, $term) {
                $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
                $this->lms->recalculateForWeek($session, $weekStart, $term);
            });
        }

        if ($match('termly', 'di-02') && $term) {
            $run('DI-02', fn () => $this->stem->recalculateForSession($session, $term));
        }

        if ($match('termly', 'di-03') && $term) {
            $run('DI-03', fn () => $this->ethics->recalculateForSession($session, $term));
        }

        if ($match('termly', 'di-04') && $term) {
            $run('DI-04', fn () => $this->competency->recalculateForSession($session, $term));
        }

        if ($match('termly', 'di-05') && $term) {
            $run('DI-05', fn () => $this->eAssessment->recalculateForSession($session, $term));
        }

        if ($match('monthly', 'di-06') && $term) {
            $run('DI-06', function () use ($session, $term) {
                $monthStart = Carbon::now()->startOfMonth()->toDateString();
                $this->parentPortal->recalculateForMonth($session, $monthStart, $term);
            });
        }

        return $done;
    }

    /**
     * @return Collection<int, AcademicSession>
     */
    public function activeSessions(?int $schoolId = null): Collection
    {
        return AcademicSession::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'active')
            ->orderBy('school_id')
            ->get();
    }
}
