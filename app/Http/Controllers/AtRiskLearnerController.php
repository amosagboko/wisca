<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\Learner;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Services\AtRiskCalculationService;
use App\Support\AtRiskCriteria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AtRiskLearnerController extends Controller
{
    public function index(Request $request, AtRiskCalculationService $atRisk): View
    {
        $user = auth()->user();
        abort_unless($user->canViewAtRisk(), 403);

        // All sessions for this school
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')->get();

        // Resolve selected session
        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        // Terms for selected session
        $allTerms = Term::where('academic_session_id', $session->id)
            ->orderBy('start_date')->get();

        $termId = $request->integer('term_id');
        $term = $termId
            ? $allTerms->firstWhere('id', $termId)
            : Term::currentForSession($session->id);

        // Classes accessible to user
        $classIds = $this->classIds($user, $session->id);
        $allClasses = \App\Models\SchoolClass::where('school_id', $user->school_id)
            ->when($classIds !== null, fn ($q) => $q->whereIn('id', $classIds ?: [0]))
            ->orderBy('display_order')->orderBy('name')->get();

        $filterClassId  = $request->integer('class_id');
        $filterPlan     = $request->query('plan', '');     // 'with_plan', 'without_plan', ''
        $filterLevel    = $request->query('level', '');    // 'low','medium','high', ''
        $filterStatus   = $request->query('status', 'active'); // 'active','resolved','all'
        $search         = trim((string) $request->query('search', ''));

        // Determine effective class IDs after optional class filter
        $effectiveClassIds = ($filterClassId && (
            $classIds === null || in_array($filterClassId, $classIds, true)
        )) ? [$filterClassId] : $classIds;

        // Base record query – honours session, class scope, and status filter
        $recordQuery = AtRiskLearner::query()
            ->where('academic_session_id', $session->id)
            ->when($filterStatus !== 'all', fn ($q) => $q->where('status', $filterStatus === 'resolved' ? 'resolved' : 'active'))
            ->when($effectiveClassIds !== null, fn ($q) => $q->whereIn('school_class_id', $effectiveClassIds ?: [0]))
            ->when($term && $filterStatus !== 'resolved', fn ($q) => $q->where('term_id', $term->id))
            ->when($filterLevel !== '', fn ($q) => $q->where('risk_level', $filterLevel))
            ->when($search !== '', fn ($q) => $q->whereHas('learner', fn ($lq) => $lq
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('admission_no', 'like', '%'.$search.'%')))
            ->whereHas('learner', fn ($q) => $q->where('school_id', $session->school_id))
            ->with(['learner.schoolClass', 'schoolClass', 'identifier', 'plans.coordinator'])
            ->latest('identification_date');

        $allRecords = $recordQuery->get();

        // Plan filter applied in-memory (needs loaded relationship)
        $records = match ($filterPlan) {
            'with_plan'    => $allRecords->filter->hasActivePlan()->values(),
            'without_plan' => $allRecords->reject->hasActivePlan()->values(),
            default        => $allRecords,
        };

        // Split into active / resolved for display
        $activeRecords   = $records->where('status', 'active')->values();
        $resolvedRecords = $records->where('status', 'resolved')->sortByDesc('resolved_at')->values();

        // AE-07 summary always uses the unfiltered active records for this session/term
        $rawOpen = $atRisk->openRecords($session, $term)
            ->when($classIds !== null, fn ($rows) => $rows->whereIn('school_class_id', $classIds ?: [0])->values());

        $summary = [
            'identified'   => $rawOpen->count(),
            'with_plan'    => $rawOpen->filter->hasActivePlan()->count(),
            'without_plan' => $rawOpen->reject->hasActivePlan()->count(),
            'rate'         => $rawOpen->count() > 0
                ? round($rawOpen->filter->hasActivePlan()->count() / $rawOpen->count(), 4)
                : 0.0,
        ];

        $filters = compact(
            'sessionId', 'termId', 'filterClassId', 'filterPlan',
            'filterLevel', 'filterStatus', 'search'
        );

        return view('at-risk.index', [
            'activeRecords'  => $activeRecords,
            'resolvedRecords'=> $resolvedRecords,
            'unflagged'      => ($term && $filterStatus !== 'resolved' && $filterPlan !== 'with_plan')
                ? $atRisk->belowPassUnflagged($session, $term, $effectiveClassIds)
                : collect(),
            'summary'        => $summary,
            'session'        => $session,
            'term'           => $term,
            'allSessions'    => $allSessions,
            'allTerms'       => $allTerms,
            'allClasses'     => $allClasses,
            'filters'        => $filters,
            'canIdentify'    => $user->canIdentifyAtRisk(),
            'canManagePlans' => $user->canManageInterventionPlans(),
        ]);
    }

    public function create(AtRiskCalculationService $atRisk): View
    {
        $user = auth()->user();
        abort_unless($user->canIdentifyAtRisk(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        return view('at-risk.identify', [
            'learners' => $atRisk->eligibleLearners($session, $this->classIds($user, $session->id)),
            'factors' => AtRiskCriteria::factors(),
            'levels' => AtRiskCriteria::levels(),
            'preselected' => (int) request('learner'),
        ]);
    }

    public function store(Request $request, AtRiskCalculationService $calculator): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canIdentifyAtRisk(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $learnerIds = $calculator->eligibleLearners($session, $this->classIds($user, $session->id))->pluck('id')->all();

        $validated = $request->validate([
            'learner_id' => ['required', 'integer', Rule::in($learnerIds ?: [0])],
            'risk_factors' => ['required', 'array', 'min:1'],
            'risk_factors.*' => ['in:'.implode(',', array_keys(AtRiskCriteria::factors()))],
            'concern_note' => ['nullable', 'string', 'max:2000'],
            'risk_level' => ['required', Rule::in(array_keys(AtRiskCriteria::levels()))],
        ]);

        if (in_array(AtRiskCriteria::CONCERN, $validated['risk_factors'], true)) {
            $request->validate(['concern_note' => ['required', 'string', 'max:2000']]);
            $validated['concern_note'] = $request->input('concern_note');
        }

        $learner = Learner::where('school_id', $user->school_id)->findOrFail($validated['learner_id']);

        $record = AtRiskLearner::firstOrNew([
            'learner_id' => $learner->id,
            'academic_session_id' => $session->id,
        ]);

        $record->fill([
            'school_class_id' => $learner->school_class_id,
            'term_id' => $term?->id,
            'identified_by' => $user->id,
            'identification_date' => now()->toDateString(),
            'risk_factors' => array_values($validated['risk_factors']),
            'concern_note' => $validated['concern_note'] ?? null,
            'risk_level' => $validated['risk_level'],
            'status' => 'active',
            'resolved_by' => null,
            'resolved_at' => null,
        ])->save();

        $calculator->recalculateForSession($session, $term);

        return redirect()
            ->route('at-risk.show', $record)
            ->with('success', 'Learner identified as at-risk. Add an active Tier 2 or Tier 3 plan for AE-07.');
    }

    public function fromExam(Request $request, AtRiskCalculationService $calculator): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canIdentifyAtRisk(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $classIds = $this->classIds($user, $session->id);
        $queue = $calculator->belowPassUnflagged($session, $term, $classIds);
        $learnerIds = $queue->pluck('learner.id')->all();

        $validated = $request->validate([
            'learner_id' => ['required', 'integer', Rule::in($learnerIds ?: [0])],
        ]);

        $learner = Learner::where('school_id', $user->school_id)->findOrFail($validated['learner_id']);

        $record = AtRiskLearner::firstOrNew([
            'learner_id' => $learner->id,
            'academic_session_id' => $session->id,
        ]);

        $factors = collect($record->risk_factors ?? [])->push(AtRiskCriteria::BELOW_PASS_MARK)->unique()->values()->all();

        $record->fill([
            'school_class_id' => $learner->school_class_id,
            'term_id' => $term->id,
            'identified_by' => $record->identified_by ?: $user->id,
            'identification_date' => $record->identification_date ?: now()->toDateString(),
            'risk_factors' => $factors,
            'risk_level' => $record->risk_level ?: 'medium',
            'status' => 'active',
            'resolved_by' => null,
            'resolved_at' => null,
        ])->save();

        $calculator->recalculateForSession($session, $term);

        return redirect()
            ->route('at-risk.show', $record)
            ->with('success', $learner->name.' flagged from a below-pass-mark result. Add an active Tier 2 or Tier 3 plan.');
    }

    public function show(AtRiskLearner $atRiskLearner): View
    {
        $this->assertCanView($atRiskLearner);
        $atRiskLearner->load(['learner.schoolClass', 'schoolClass', 'identifier', 'resolver', 'plans.coordinator']);

        return view('at-risk.show', [
            'record' => $atRiskLearner,
            'canManagePlans' => auth()->user()->canManageInterventionPlans(),
            'canIdentify' => auth()->user()->canIdentifyAtRisk(),
        ]);
    }

    public function resolve(AtRiskLearner $atRiskLearner, AtRiskCalculationService $calculator): RedirectResponse
    {
        $this->assertCanView($atRiskLearner);
        abort_unless(auth()->user()->canIdentifyAtRisk(), 403);

        $atRiskLearner->update([
            'status' => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        $session = AcademicSession::currentForSchool(auth()->user()->school_id);
        $term = $session ? Term::currentForSession($session->id) : null;
        if ($session) {
            $calculator->recalculateForSession($session, $term);
        }

        return redirect()->route('at-risk.index')->with('success', 'Learner marked as no longer at-risk.');
    }

    protected function assertCanView(AtRiskLearner $record): void
    {
        $user = auth()->user();
        abort_unless($user->canViewAtRisk(), 403);
        abort_unless((int) $record->learner?->school_id === (int) $user->school_id, 403);

        $classIds = $this->classIds($user, $record->academic_session_id);
        if ($classIds !== null) {
            abort_unless(in_array((int) $record->school_class_id, $classIds, true), 403);
        }
    }

    /** @return array<int, int>|null */
    protected function classIds($user, ?int $sessionId): ?array
    {
        if ($user->canManageInterventionPlans() || $user->isHoD() || $user->isHoS() || $user->isLearningSupport()) {
            return null;
        }

        if (! $sessionId) {
            return [];
        }

        return TeacherAssignment::where('teacher_id', $user->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->pluck('school_class_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
