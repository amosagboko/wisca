<?php

namespace App\Http\Controllers;

use App\Services\PlanningPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanningPolicyController extends Controller
{
    public function edit(Request $request, PlanningPolicy $policy): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isHoS(), 403);

        $school = $user->school;
        abort_unless($school, 403);

        return view('planning-policy.edit', [
            'school' => $school,
            'weekday' => $policy->lessonPlanDueWeekday($school),
            'weekdays' => PlanningPolicy::WEEKDAYS,
        ]);
    }

    public function update(Request $request, PlanningPolicy $policy): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isHoS(), 403);

        $school = $user->school;
        abort_unless($school, 403);

        $validated = $request->validate([
            'lesson_plan_due_weekday' => ['required', 'integer', 'in:0,1,2,3,4,5,6'],
        ]);

        $policy->putLessonPlanDueWeekday($school, (int) $validated['lesson_plan_due_weekday']);

        return redirect()
            ->route('planning-policy.edit')
            ->with('success', 'Planning due day updated. New lesson plans use this weekday. Historical due dates were not rewritten.');
    }
}
