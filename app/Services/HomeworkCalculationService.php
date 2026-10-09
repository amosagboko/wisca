<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\HomeworkLog;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Term;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HomeworkCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-03')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $logs = $this->logsForCurrentWeek($session, $term);
        $given = (int) $logs->sum('given_count');
        if ($given === 0) {
            return null;
        }

        $completed = (int) $logs->sum('completed_on_time_count');
        $rate = round($completed / $given, 4);
        $achievement = $this->evaluator->achievementRate($rate, (float) $kpi->default_target);
        $window = $this->weekWindow($session, $term);

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
                    'week_number' => $window['week_number'],
                    'given' => $given,
                    'completed_on_time' => $completed,
                    'logs' => $logs->count(),
                ],
            ]
        );
    }

    /**
     * @return array{week_number: int, start: Carbon, end: Carbon}
     */
    public function weekWindow(AcademicSession $session, Term $term): array
    {
        $maxWeek = (int) Topic::whereHas('schemeOfWork', function ($query) use ($session, $term) {
            $query->where('academic_session_id', $session->id)
                ->where('term_id', $term->id);
        })->max('week_number');

        $weekNumber = $term->schemeWeekNumber(max(1, $maxWeek));

        return [
            'week_number' => $weekNumber,
            'start' => $term->instructionalWeekStart($weekNumber),
            'end' => $term->instructionalWeekEnd($weekNumber),
        ];
    }

    public function suggestedGivenDate(AcademicSession $session, Term $term): string
    {
        $window = $this->weekWindow($session, $term);
        $start = $window['start']->copy()->startOfDay();
        $end = $window['end']->copy()->startOfDay();

        return now()->startOfDay()->max($start)->min($end)->toDateString();
    }

    public function logsForCurrentWeek(AcademicSession $session, Term $term, ?int $teacherId = null, ?array $subjectIds = null): Collection
    {
        $window = $this->weekWindow($session, $term);

        return HomeworkLog::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->whereBetween('given_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->when($teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->when($subjectIds !== null, fn ($query) => $query->whereIn('subject_id', $subjectIds ?: [0]))
            ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $session->school_id))
            ->with(['teacher', 'schoolClass', 'subject'])
            ->latest('given_date')
            ->get();
    }

    public function rateForLogs(Collection $logs): array
    {
        $given = (int) $logs->sum('given_count');
        $completed = (int) $logs->sum('completed_on_time_count');
        $rate = $given > 0 ? round($completed / $given, 4) : 0.0;

        return compact('given', 'completed', 'rate');
    }
}
