<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\DigitalCompetencyRating;
use App\Models\Term;
use App\Services\DigitalCompetencyCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DigitalCompetencyController extends Controller
{
    public function index(Request $request, DigitalCompetencyCalculationService $service): View
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

        $areas = $service->activeAreasForSchool($user->school_id);
        $summary = $service->sessionSummary($session, $term ?: null);
        $marksheet = $service->staffMarksheet($session, $term ?: null, $areas);

        $filters = compact('sessionId', 'termId');
        $activeFilters = (bool) ($sessionId || $termId);

        return view('competency.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'areas',
            'summary',
            'marksheet',
            'filters',
            'activeFilters'
        ));
    }

    public function create(DigitalCompetencyCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $areas = $service->activeAreasForSchool($user->school_id);
        abort_unless($areas->isNotEmpty(), 403, 'No active digital competency areas configured.');

        $marksheet = $service->staffMarksheet($session, $term, $areas);

        return view('competency.form', compact('session', 'term', 'areas', 'marksheet'));
    }

    public function store(Request $request, DigitalCompetencyCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'rows.*.ratings' => ['required', 'array'],
            'rows.*.ratings.*.area_id' => ['required', 'integer', 'exists:digital_competency_areas,id'],
            'rows.*.ratings.*.level' => ['nullable', 'integer', 'min:1', 'max:4'],
            'rows.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        foreach ($data['rows'] as $row) {
            foreach ($row['ratings'] as $ratingRow) {
                $level = $ratingRow['level'] ?? null;
                if ($level === null || $level === '') {
                    continue;
                }

                DigitalCompetencyRating::updateOrCreate(
                    [
                        'user_id' => (int) $row['user_id'],
                        'digital_competency_area_id' => (int) $ratingRow['area_id'],
                        'academic_session_id' => $session->id,
                        'term_id' => $term?->id,
                    ],
                    [
                        'school_id' => $user->school_id,
                        'level' => (int) $level,
                        'assessed_on' => now()->toDateString(),
                        'notes' => $row['notes'] ?? null,
                        'assessed_by' => $user->id,
                    ]
                );
            }
        }

        $service->recalculateForSession($session, $term);

        return redirect()->route('competency.index')
            ->with('success', 'Digital competency ratings saved and DI-04 recalculated.');
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isHoS()
            || $user->isItConsultant();
    }
}
