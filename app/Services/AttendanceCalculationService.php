<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceLog;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'AE-04')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $logs = $this->logsForCurrentWeek($session, $term);
        $enrolled = (int) $logs->sum('enrolled_count');
        if ($enrolled === 0) {
            return null;
        }

        $present = (int) $logs->sum('present_count');
        $rate = round($present / $enrolled, 4);
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
                    'present' => $present,
                    'enrolled' => $enrolled,
                    'class_days' => $logs->count(),
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

    public function suggestedDate(AcademicSession $session, Term $term): string
    {
        $window = $this->weekWindow($session, $term);
        $start = $window['start']->copy()->startOfDay();
        $end = $window['end']->copy()->startOfDay();

        return now()->startOfDay()->max($start)->min($end)->toDateString();
    }

    public function logsForCurrentWeek(AcademicSession $session, Term $term, ?array $classIds = null): Collection
    {
        $window = $this->weekWindow($session, $term);

        return AttendanceLog::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->whereBetween('attendance_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->when($classIds !== null, fn ($query) => $query->whereIn('school_class_id', $classIds ?: [0]))
            ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $session->school_id))
            ->with(['recorder', 'schoolClass'])
            ->orderByDesc('attendance_date')
            ->orderBy('school_class_id')
            ->get();
    }

    public function rateForLogs(Collection $logs): array
    {
        $enrolled = (int) $logs->sum('enrolled_count');
        $present = (int) $logs->sum('present_count');
        $rate = $enrolled > 0 ? round($present / $enrolled, 4) : 0.0;

        return compact('enrolled', 'present', 'rate');
    }

    public function officerWeek(AcademicSession $session): array
    {
        $term = Term::currentForSession($session->id);
        $classes = SchoolClass::where('school_id', $session->school_id)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        if (! $term) {
            return [
                'term' => null,
                'week_number' => 1,
                'suggested_date' => now()->toDateString(),
                'suggested_date_label' => now()->format('d M Y'),
                'attendance_logs' => collect(),
                'attendance_week' => $this->rateForLogs(collect()),
                'missing_classes' => $classes,
                'classes' => $classes,
            ];
        }

        $window = $this->weekWindow($session, $term);
        $logs = $this->logsForCurrentWeek($session, $term);
        $date = $this->suggestedDate($session, $term);
        $loggedToday = $logs
            ->filter(fn (AttendanceLog $log) => $log->attendance_date->toDateString() === $date)
            ->pluck('school_class_id');

        return [
            'term' => $term,
            'week_number' => $window['week_number'],
            'suggested_date' => $date,
            'suggested_date_label' => Carbon::parse($date)->format('d M Y'),
            'attendance_logs' => $logs,
            'attendance_week' => $this->rateForLogs($logs),
            'missing_classes' => $classes->reject(fn (SchoolClass $class) => $loggedToday->contains($class->id))->values(),
            'classes' => $classes,
        ];
    }
}
