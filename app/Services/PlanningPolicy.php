<?php

namespace App\Services;

use App\Models\School;
use Carbon\Carbon;

class PlanningPolicy
{
    public const DEFAULT_DUE_WEEKDAY = Carbon::THURSDAY;

    /**
     * @var array<int, string>
     */
    public const WEEKDAYS = [
        Carbon::SUNDAY => 'Sunday',
        Carbon::MONDAY => 'Monday',
        Carbon::TUESDAY => 'Tuesday',
        Carbon::WEDNESDAY => 'Wednesday',
        Carbon::THURSDAY => 'Thursday',
        Carbon::FRIDAY => 'Friday',
        Carbon::SATURDAY => 'Saturday',
    ];

    public function lessonPlanDueWeekday(?School $school): int
    {
        $stored = $school?->settings['planning_policy']['lesson_plan_due_weekday'] ?? self::DEFAULT_DUE_WEEKDAY;
        $weekday = (int) $stored;

        return array_key_exists($weekday, self::WEEKDAYS) ? $weekday : self::DEFAULT_DUE_WEEKDAY;
    }

    public function lessonPlanDueWeekdayName(?School $school): string
    {
        return self::WEEKDAYS[$this->lessonPlanDueWeekday($school)];
    }

    public function putLessonPlanDueWeekday(School $school, int $weekday): void
    {
        if (! array_key_exists($weekday, self::WEEKDAYS)) {
            $weekday = self::DEFAULT_DUE_WEEKDAY;
        }

        $settings = $school->settings ?? [];
        $settings['planning_policy'] = [
            'lesson_plan_due_weekday' => $weekday,
        ];
        $school->update(['settings' => $settings]);
    }
}
