<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriodTransition;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Services\AcademicPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicPeriodController extends Controller
{
    public function __construct(protected AcademicPeriodService $periods) {}

    public function show(): View
    {
        $user = auth()->user();
        abort_unless($user->canManageAcademicPeriod(), 403);

        $currentSession = $this->periods->currentSession((int) $user->school_id);
        if ($currentSession) {
            $currentSession->load(['terms' => fn ($q) => $q->orderBy('sequence')]);
        }

        $closedSessions = AcademicSession::where('school_id', $user->school_id)
            ->where('status', 'closed')
            ->when($currentSession, fn ($q) => $q->where('id', '!=', $currentSession->id))
            ->with(['terms' => fn ($q) => $q->orderBy('sequence')])
            ->orderByDesc('start_date')
            ->paginate(1, ['*'], 'sessions_page')
            ->withQueryString()
            ->fragment('sessions');

        $currentTerm = $currentSession ? $this->periods->currentTerm($currentSession->id) : null;
        $nextTerm = $currentTerm ? $this->periods->nextTerm($currentTerm) : null;
        $nextAvailable = $currentSession ? $this->periods->nextAvailableTerm($currentSession) : null;

        $transitions = AcademicPeriodTransition::where('school_id', $user->school_id)
            ->with(['performer', 'previousSession', 'previousTerm', 'newSession', 'newTerm'])
            ->latest('performed_at')
            ->paginate(3, ['*'], 'transitions_page')
            ->withQueryString()
            ->fragment('transitions');

        return view('academic-period.show', [
            'closedSessions' => $closedSessions,
            'currentSession' => $currentSession,
            'currentTerm' => $currentTerm,
            'nextTerm' => $nextTerm,
            'nextAvailable' => $nextAvailable,
            'canStartYear' => $user->isAdmin(),
            'transitions' => $transitions,
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canActivateAcademicPeriod(), 403);

        return redirect()->route('admin.sessions.create');
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAcademicPeriod(), 403);

        $validated = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'sequence' => ['required', 'integer', 'in:1,2,3'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $session = AcademicSession::where('id', $validated['academic_session_id'])
            ->where('school_id', auth()->user()->school_id)
            ->firstOrFail();

        $this->periods->createUpcomingTerm(
            $session,
            (int) $validated['sequence'],
            $validated['start_date'],
            $validated['end_date'],
        );

        return back()->with('success', $this->periods->termLabel((int) $validated['sequence']).' configured on '.$session->name.'.');
    }

    public function transition(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAcademicPeriod(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->periods->transitionToNextTerm(auth()->user(), $validated['notes'] ?? null);

        return redirect()->route('academic-period.show')->with('success', 'The school has moved to the next term. Historical records remain on their original period.');
    }

    public function rollover(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canActivateAcademicPeriod(), 403);

        $validated = $request->validate([
            'target_session_id' => ['required', 'exists:academic_sessions,id'],
            'starting_term_id' => ['nullable', 'exists:terms,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = AcademicSession::where('id', $validated['target_session_id'])
            ->where('school_id', auth()->user()->school_id)
            ->firstOrFail();

        $start = isset($validated['starting_term_id'])
            ? Term::where('id', $validated['starting_term_id'])->where('academic_session_id', $target->id)->firstOrFail()
            : null;

        $this->periods->rolloverToSession(auth()->user(), $target, $start, $validated['notes'] ?? null);

        return redirect()->route('academic-period.show')->with('success', 'Session rollover complete. Historical records remain on their original academic period.');
    }

    public function openInitial(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canActivateAcademicPeriod(), 403);

        $validated = $request->validate([
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = AcademicSession::where('id', $validated['session_id'])
            ->where('school_id', auth()->user()->school_id)
            ->firstOrFail();
        $term = Term::where('id', $validated['term_id'])
            ->where('academic_session_id', $session->id)
            ->firstOrFail();

        $this->periods->openInitialPeriod(auth()->user(), $session, $term, $validated['notes'] ?? null);

        return redirect()->route('academic-period.show')->with('success', 'Academic period opened. Historical records were not rewritten.');
    }
}
