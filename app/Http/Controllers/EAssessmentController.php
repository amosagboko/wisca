<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Subject;
use App\Models\SubjectDigitalAssessment;
use App\Models\Term;
use App\Services\EAssessmentCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EAssessmentController extends Controller
{
    public function index(Request $request, EAssessmentCalculationService $service): View
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

        $summary = $service->sessionSummary($session, $term ?: null);
        $rows = $service->subjectRows($session, $term ?: null);

        $filters = compact('sessionId', 'termId');
        $activeFilters = (bool) ($sessionId || $termId);

        return view('eassessment.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'summary',
            'rows',
            'filters',
            'activeFilters'
        ));
    }

    public function create(EAssessmentCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $rows = $service->subjectRows($session, $term);

        return view('eassessment.form', compact('session', 'term', 'rows'));
    }

    public function store(Request $request, EAssessmentCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'rows.*.uses_e_assessment' => ['nullable', 'boolean'],
            'rows.*.uses_e_portfolio' => ['nullable', 'boolean'],
            'rows.*.primary_tool' => ['nullable', 'string', 'max:100'],
            'rows.*.evidence_notes' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data['rows'] as $row) {
            $subject = Subject::where('school_id', $user->school_id)->find($row['subject_id']);
            if (! $subject) {
                continue;
            }

            $usesAssessment = (bool) ($row['uses_e_assessment'] ?? false);
            $usesPortfolio = (bool) ($row['uses_e_portfolio'] ?? false);

            SubjectDigitalAssessment::updateOrCreate(
                [
                    'subject_id' => $subject->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term?->id,
                ],
                [
                    'school_id' => $user->school_id,
                    'uses_e_assessment' => $usesAssessment,
                    'uses_e_portfolio' => $usesPortfolio,
                    'primary_tool' => $row['primary_tool'] ?? null,
                    'evidence_notes' => $row['evidence_notes'] ?? null,
                    'verified_on' => ($usesAssessment || $usesPortfolio) ? now()->toDateString() : null,
                    'verified_by' => ($usesAssessment || $usesPortfolio) ? $user->id : null,
                ]
            );
        }

        $service->recalculateForSession($session, $term);

        return redirect()->route('eassessment.index')
            ->with('success', 'Digital assessment usage saved and DI-05 recalculated.');
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isLeadership()
            || $user->isIctCoordinator();
    }
}
