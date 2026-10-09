<?php

namespace App\Services;

use App\Models\Term;
use App\Models\Topic;
use Carbon\Carbon;

class AcademicReportingPeriod
{
    /**
     * @return array{week_number: int, start: Carbon, end: Carbon}
     */
    public function window(Term $term, int $weekNumber): array
    {
        $weekNumber = max(1, $weekNumber);

        return [
            'week_number' => $weekNumber,
            'start' => $term->instructionalWeekStart($weekNumber),
            'end' => $term->instructionalWeekEnd($weekNumber),
        ];
    }

    /**
     * @return array{week_number: int, start: Carbon, end: Carbon}
     */
    public function current(Term $term, int $maxWeek = 1, ?Carbon $on = null): array
    {
        return $this->window($term, $this->weekNumber($term, $maxWeek, $on));
    }

    public function weekNumber(Term $term, int $maxWeek = 1, ?Carbon $on = null): int
    {
        $maxWeek = max(1, $maxWeek);
        $today = ($on ?? now())->copy()->startOfDay();
        $start = $term->start_date->copy()->startOfDay();

        if ($today->lt($start)) {
            return 1;
        }

        $week = (int) floor($start->diffInDays($today) / 7) + 1;

        return max(1, min($maxWeek, $week));
    }

    public function maxWeekForTerm(Term $term): int
    {
        $max = (int) Topic::query()
            ->whereHas('schemeOfWork', fn ($query) => $query
                ->where('term_id', $term->id)
                ->where('status', 'active'))
            ->max('week_number');

        return max(1, $max);
    }

    public function midTermWeek(int $maxWeek): int
    {
        return (int) max(1, (int) ceil(max(1, $maxWeek) / 2));
    }

    public function dueAtInWeek(Term $term, int $weekNumber, int $weekday): Carbon
    {
        $start = $term->instructionalWeekStart($weekNumber)->startOfDay();
        $end = $term->instructionalWeekEnd($weekNumber);

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ((int) $cursor->dayOfWeek === $weekday) {
                return $cursor->copy()->endOfDay();
            }
            $cursor->addDay();
        }

        return $start->copy()->next($weekday)->endOfDay();
    }
}
