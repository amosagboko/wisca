<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\BullyingCase;
use App\Models\BullyingCaseType;
use App\Models\Learner;
use App\Models\Term;
use App\Services\BullyingCaseCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BullyingCaseController extends Controller
{
    public function index(Request $request, BullyingCaseCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $allSessions = AcademicSession::where('school_id', $user->school_id)->orderByDesc('start_date')->get();
        $sessionId = $request->integer('session_id');
        $session = $sessionId ? $allSessions->firstWhere('id', $sessionId) : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $termId = $request->integer('term_id');
        $term = $termId ? $allTerms->firstWhere('id', $termId) : Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $types = BullyingCaseType::where('school_id', $user->school_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $typeId = $request->integer('type_id');
        $status = (string) $request->query('status', '');
        $safety = (string) $request->query('safety', '');

        $summary = $service->monthSummary($session, $term);
        $rows = $service->caseRows($session, $term, $typeId ?: null, $status, $safety);

        $filters = compact('sessionId', 'termId', 'typeId', 'status', 'safety');
        $activeFilters = (bool) ($sessionId || $termId || $typeId || $status !== '' || $safety !== '');

        return view('bullying.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'types',
            'summary',
            'rows',
            'filters',
            'activeFilters'
        ));
    }

    public function create(): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $terms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $types = BullyingCaseType::where('school_id', $user->school_id)->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
        $learners = Learner::where('school_id', $user->school_id)->where('status', 'enrolled')->orderBy('name')->get();

        return view('bullying.form', [
            'case' => new BullyingCase([
                'reported_on' => now()->toDateString(),
                'severity' => 'medium',
                'status' => 'reported',
                'safety_plan_created' => false,
            ]),
            'terms' => $terms,
            'types' => $types,
            'learners' => $learners,
            'session' => $session,
        ]);
    }

    public function store(Request $request, BullyingCaseCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $data = $this->validateCase($request);
        $data['school_id'] = $user->school_id;
        $data['academic_session_id'] = $session->id;
        $data['reported_by'] = $user->id;

        if ($data['safety_plan_created']) {
            $data['safety_plan_created_at'] = now();
        }
        if ($data['status'] === 'closed') {
            $data['closed_at'] = now();
            $data['closed_by'] = $user->id;
        }

        BullyingCase::create($data);
        $service->recalculateForSession($session, Term::find($data['term_id']));

        return redirect()->route('bullying.index')->with('success', 'Bullying case logged and CE-05 recalculated.');
    }

    public function edit(BullyingCase $bullyingCase): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($bullyingCase->school_id === $user->school_id, 404);

        $terms = Term::where('academic_session_id', $bullyingCase->academic_session_id)->orderBy('start_date')->get();
        $types = BullyingCaseType::where('school_id', $user->school_id)->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
        $learners = Learner::where('school_id', $user->school_id)->where('status', 'enrolled')->orderBy('name')->get();

        return view('bullying.form', [
            'case' => $bullyingCase,
            'terms' => $terms,
            'types' => $types,
            'learners' => $learners,
            'session' => $bullyingCase->academicSession,
        ]);
    }

    public function update(Request $request, BullyingCase $bullyingCase, BullyingCaseCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($bullyingCase->school_id === $user->school_id, 404);

        $data = $this->validateCase($request);

        if ($data['safety_plan_created'] && $bullyingCase->safety_plan_created_at === null) {
            $data['safety_plan_created_at'] = now();
        } elseif (! $data['safety_plan_created']) {
            $data['safety_plan_created_at'] = null;
        }

        if ($data['status'] === 'closed' && $bullyingCase->closed_at === null) {
            $data['closed_at'] = now();
            $data['closed_by'] = $user->id;
        } elseif ($data['status'] !== 'closed') {
            $data['closed_at'] = null;
        }

        $bullyingCase->update($data);
        $service->recalculateForSession($bullyingCase->academicSession, $bullyingCase->term);

        return redirect()->route('bullying.index')->with('success', 'Bullying case updated and CE-05 recalculated.');
    }

    private function validateCase(Request $request): array
    {
        return $request->validate([
            'bullying_case_type_id' => ['required', 'integer', 'exists:bullying_case_types,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'target_learner_id' => ['nullable', 'integer', 'exists:learners,id'],
            'reported_by_learner_id' => ['nullable', 'integer', 'exists:learners,id'],
            'reported_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:reported,investigating,closed'],
            'safety_plan_created' => ['boolean'],
            'safety_plan' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isHoS()
            || $user->isHoD()
            || $user->isTeacher()
            || $user->hasRole('chaplain')
            || $user->hasRole('student_life_coordinator');
    }
}
