<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\InterventionPlan;
use App\Models\Term;
use App\Services\AtRiskCalculationService;
use App\Support\AtRiskCriteria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InterventionPlanController extends Controller
{
    public function create(AtRiskLearner $atRiskLearner): View
    {
        $this->assertCanManage($atRiskLearner);

        return view('at-risk.plan-form', [
            'record' => $atRiskLearner->load(['learner', 'schoolClass']),
            'plan' => new InterventionPlan([
                'plan_type' => AtRiskCriteria::TIER_2,
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'review_date' => now()->addWeeks(4)->toDateString(),
            ]),
        ]);
    }

    public function store(Request $request, AtRiskLearner $atRiskLearner, AtRiskCalculationService $calculator): RedirectResponse
    {
        $this->assertCanManage($atRiskLearner);

        return $this->persist($request, $atRiskLearner, new InterventionPlan, $calculator);
    }

    public function edit(InterventionPlan $interventionPlan): View
    {
        $interventionPlan->load('atRiskLearner.learner', 'atRiskLearner.schoolClass');
        $this->assertCanManage($interventionPlan->atRiskLearner);

        return view('at-risk.plan-form', [
            'record' => $interventionPlan->atRiskLearner,
            'plan' => $interventionPlan,
        ]);
    }

    public function update(Request $request, InterventionPlan $interventionPlan, AtRiskCalculationService $calculator): RedirectResponse
    {
        $interventionPlan->load('atRiskLearner.learner');
        $this->assertCanManage($interventionPlan->atRiskLearner);

        return $this->persist($request, $interventionPlan->atRiskLearner, $interventionPlan, $calculator);
    }

    protected function persist(
        Request $request,
        AtRiskLearner $record,
        InterventionPlan $plan,
        AtRiskCalculationService $calculator,
    ): RedirectResponse {
        $validated = $request->validate([
            'plan_type' => ['required', Rule::in(array_keys(AtRiskCriteria::planTypes()))],
            'objectives' => ['required', 'string', 'max:4000'],
            'strategies' => ['required', 'string', 'max:4000'],
            'start_date' => ['required', 'date'],
            'review_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(array_keys(AtRiskCriteria::planStatuses()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === 'active') {
            $record->plans()
                ->when($plan->exists, fn ($q) => $q->where('id', '!=', $plan->id))
                ->where('status', 'active')
                ->update(['status' => 'completed']);
        }

        $plan->fill([
            ...$validated,
            'at_risk_learner_id' => $record->id,
            'coordinator_id' => $plan->coordinator_id ?: auth()->id(),
        ])->save();

        $session = AcademicSession::currentForSchool(auth()->user()->school_id);
        $term = $session ? Term::currentForSession($session->id) : null;
        if ($session) {
            $calculator->recalculateForSession($session, $term);
        }

        return redirect()
            ->route('at-risk.show', $record)
            ->with('success', $plan->countsTowardKpi()
                ? 'Active '.$plan->typeLabel().' plan saved. This learner now counts in the AE-07 numerator.'
                : 'Plan saved. Only an active Tier 2 or Tier 3 plan counts toward AE-07.');
    }

    protected function assertCanManage(AtRiskLearner $record): void
    {
        $user = auth()->user();
        abort_unless($user->canManageInterventionPlans(), 403);
        abort_unless((int) $record->learner?->school_id === (int) $user->school_id, 403);
    }
}
