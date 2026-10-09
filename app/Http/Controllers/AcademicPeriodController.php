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

        $sessions = AcademicSession::where('school_id', $user->school_id)
            ->with(['terms' => fn ($q) => $q->orderBy('sequence')])
            ->orderByDesc('start_date')
            ->get();

        $currentSession = $this->periods->currentSession((int) $user->school_id);
        $currentTerm = $currentSession ? $this->periods->currentTerm($currentSession->id) : null;
        $nextTerm = $currentTerm ? $this->periods->nextTerm($currentTerm) : null;
        $isLastTerm = $currentSession && $currentTerm
            && $this->periods->lastTerm($currentSession)?->is($currentTerm);
        $canActivate = $user->canActivateAcademicPeriod();
        $canRollover = (bool) ($canActivate && $currentSession && $currentTerm);

        $targetSessions = $sessions->filter(fn (AcademicSession $session) => ! $session->is_current && $session->status !== 'closed' && $session->terms->isNotEmpty());

        $transitions = AcademicPeriodTransition::where('school_id', $user->school_id)
            ->with(['performer', 'previousSession', 'previousTerm', 'newSession', 'newTerm'])
            ->latest('performed_at')
            ->limit(10)
            ->get();

        return view('academic-period.show', [
            'sessions' => $sessions,
            'currentSession' => $currentSession,
            'currentTerm' => $currentTerm,
            'nextTerm' => $nextTerm,
            'canRollover' => $canRollover,
            'canActivateAnytime' => $canActivate,
            'isLastTerm' => (bool) $isLastTerm,
            'targetSessions' => $targetSessions,
            'transitions' => $transitions,
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAcademicPeriod(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        AcademicSession::create([
            ...$validated,
            'school_id' => auth()->user()->school_id,
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        return back()->with('success', 'Future academic session created. Add its terms. System Admin activates it when the school is ready.');
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAcademicPeriod(), 403);

        $validated = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'sequence' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $session = AcademicSession::where('id', $validated['academic_session_id'])
            ->where('school_id', auth()->user()->school_id)
            ->firstOrFail();

        $sequence = $validated['sequence'] ?? ((int) $session->terms()->max('sequence') + 1);

        if (Term::where('academic_session_id', $session->id)->where('sequence', $sequence)->exists()) {
            return back()->withErrors(['sequence' => 'That sequence is already used in this academic session.']);
        }

        Term::create([
            'academic_session_id' => $session->id,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'sequence' => max(1, $sequence),
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        return back()->with('success', 'Term configured on '.$session->name.'.');
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
