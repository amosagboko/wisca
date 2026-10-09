<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\DigitalEthicsAudit;
use App\Models\Learner;
use App\Models\Term;
use App\Services\DigitalEthicsCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DigitalEthicsAuditController extends Controller
{
    public function index(Request $request, DigitalEthicsCalculationService $service): View
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

        $types = $service->activeTypesForSchool($user->school_id);
        $typeId = $request->integer('type_id');
        $compliance = (string) $request->query('compliance', '');

        $summary = $service->sessionSummary($session, $term);
        $rows = $service->auditRows($session, $term, $typeId ?: null, $compliance);

        $filters = compact('sessionId', 'termId', 'typeId', 'compliance');
        $activeFilters = (bool) ($sessionId || $termId || $typeId || $compliance !== '');

        return view('ethics.index', compact(
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

    public function create(DigitalEthicsCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $terms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $types = $service->activeTypesForSchool($user->school_id);
        abort_unless($types->isNotEmpty(), 403, 'No active digital ethics audit types configured.');

        $learners = Learner::where('school_id', $user->school_id)
            ->where('status', 'enrolled')
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        return view('ethics.form', [
            'audit' => new DigitalEthicsAudit([
                'audited_on' => now()->toDateString(),
                'free_of_violations' => true,
                'term_id' => Term::currentForSession($session->id)?->id,
            ]),
            'session' => $session,
            'terms' => $terms,
            'types' => $types,
            'learners' => $learners,
        ]);
    }

    public function store(Request $request, DigitalEthicsCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $data = $this->validateAudit($request);
        $learner = isset($data['learner_id'])
            ? Learner::where('school_id', $user->school_id)->find($data['learner_id'])
            : null;

        DigitalEthicsAudit::create([
            ...$data,
            'school_id' => $user->school_id,
            'academic_session_id' => $session->id,
            'school_class_id' => $learner?->school_class_id,
            'audited_by' => $user->id,
        ]);

        $service->recalculateForSession($session, isset($data['term_id']) ? Term::find($data['term_id']) : null);

        return redirect()->route('ethics.index')
            ->with('success', 'Digital ethics audit logged and DI-03 recalculated.');
    }

    public function edit(DigitalEthicsAudit $digitalEthicsAudit, DigitalEthicsCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($digitalEthicsAudit->school_id === $user->school_id, 404);

        $session = $digitalEthicsAudit->academicSession;
        $terms = Term::where('academic_session_id', $digitalEthicsAudit->academic_session_id)->orderBy('start_date')->get();
        $types = $service->activeTypesForSchool($user->school_id);
        $learners = Learner::where('school_id', $user->school_id)
            ->where('status', 'enrolled')
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        return view('ethics.form', [
            'audit' => $digitalEthicsAudit,
            'session' => $session,
            'terms' => $terms,
            'types' => $types,
            'learners' => $learners,
        ]);
    }

    public function update(
        Request $request,
        DigitalEthicsAudit $digitalEthicsAudit,
        DigitalEthicsCalculationService $service,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($digitalEthicsAudit->school_id === $user->school_id, 404);

        $data = $this->validateAudit($request);
        $learner = isset($data['learner_id'])
            ? Learner::where('school_id', $user->school_id)->find($data['learner_id'])
            : null;

        $digitalEthicsAudit->update([
            ...$data,
            'school_class_id' => $learner?->school_class_id,
            'audited_by' => $user->id,
        ]);

        $service->recalculateForSession(
            $digitalEthicsAudit->academicSession,
            $digitalEthicsAudit->term
        );

        return redirect()->route('ethics.index')
            ->with('success', 'Digital ethics audit updated and DI-03 recalculated.');
    }

    private function validateAudit(Request $request): array
    {
        $data = $request->validate([
            'digital_ethics_audit_type_id' => ['required', 'integer', 'exists:digital_ethics_audit_types,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'learner_id' => ['nullable', 'integer', 'exists:learners,id'],
            'assignment_title' => ['required', 'string', 'max:200'],
            'audited_on' => ['required', 'date'],
            'free_of_violations' => ['boolean'],
            'violation_category' => ['nullable', 'string', 'max:100'],
            'detector_tool' => ['nullable', 'string', 'max:100'],
            'findings' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['free_of_violations'] = $request->boolean('free_of_violations', true);

        if ($data['free_of_violations']) {
            $data['violation_category'] = null;
        }

        return $data;
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isLeadership()
            || $user->isHoD()
            || $user->isTeacher()
            || $user->isIctCoordinator();
    }
}
