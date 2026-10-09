<?php

namespace Tests\Unit;

use App\Services\AcademicReportingPeriod;
use App\Services\PlanningPolicy;
use App\Models\Term;
use Carbon\Carbon;
use Tests\TestCase;

class CurriculumCoverageP2RulesTest extends TestCase
{
    public function test_planning_policy_defaults_to_thursday(): void
    {
        $policy = new PlanningPolicy;

        $this->assertSame(Carbon::THURSDAY, $policy->lessonPlanDueWeekday(null));
        $this->assertSame('Thursday', $policy->lessonPlanDueWeekdayName(null));
    }

    public function test_term_lesson_plan_due_at_helper_remains_monday(): void
    {
        $term = new Term(['start_date' => '2025-09-01']);

        $this->assertTrue($term->lessonPlanDueAt(1)->isMonday());
    }

    public function test_reporting_period_mid_term_and_week_window(): void
    {
        $periods = new AcademicReportingPeriod;
        $this->assertSame(5, $periods->midTermWeek(10));
        $this->assertSame(1, $periods->midTermWeek(1));

        $term = new Term(['start_date' => '2025-09-01']);
        $window = $periods->window($term, 1);
        $this->assertSame(1, $window['week_number']);
        $this->assertTrue($window['start']->equalTo($term->instructionalWeekStart(1)));
    }

    public function test_due_at_in_week_uses_requested_weekday(): void
    {
        $periods = new AcademicReportingPeriod;
        $term = new Term(['start_date' => '2025-09-01']);
        $due = $periods->dueAtInWeek($term, 1, Carbon::THURSDAY);

        $this->assertTrue($due->isThursday());
        $this->assertTrue($due->betweenIncluded($term->instructionalWeekStart(1), $term->instructionalWeekEnd(1)));
    }
}
