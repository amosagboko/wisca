<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use App\Models\Term;
use App\Services\AcademicPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TermController extends AdminController
{
    public function __construct(protected AcademicPeriodService $periods) {}

    public function index(): View
    {
        $terms = Term::whereHas('academicSession', fn ($q) => $q->where('school_id', $this->schoolId()))
            ->with('academicSession')
            ->orderBy('academic_session_id')
            ->orderBy('sequence')
            ->get();

        $sessions = AcademicSession::where('school_id', $this->schoolId())->orderByDesc('start_date')->get();

        return view('admin.terms.index', compact('terms', 'sessions'));
    }

    public function create(): View
    {
        return view('admin.terms.form', [
            'term' => new Term(['status' => 'upcoming', 'sequence' => 1]),
            'sessions' => AcademicSession::where('school_id', $this->schoolId())->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTerm($request);
        $this->ensureSessionBelongsToSchool($validated['academic_session_id']);

        $session = AcademicSession::findOrFail($validated['academic_session_id']);
        $this->periods->createUpcomingTerm(
            $session,
            (int) $validated['sequence'],
            $validated['start_date'],
            $validated['end_date'],
        );

        return redirect()->route('academic-period.show')->with('success', $this->periods->termLabel((int) $validated['sequence']).' configured on '.$session->name.'.');
    }

    public function edit(Term $term): View
    {
        $this->ensureTermBelongsToSchool($term);

        return view('admin.terms.form', [
            'term' => $term,
            'sessions' => AcademicSession::where('school_id', $this->schoolId())->orderByDesc('start_date')->get(),
        ]);
    }

    public function update(Request $request, Term $term): RedirectResponse
    {
        $this->ensureTermBelongsToSchool($term);

        $validated = $this->validateTerm($request, $term);
        $this->ensureSessionBelongsToSchool($validated['academic_session_id']);

        if ((int) $validated['academic_session_id'] !== (int) $term->academic_session_id
            && ($term->is_current || $this->periods->termHasDependents($term))) {
            return back()->withErrors(['academic_session_id' => 'Cannot move the current term or a term that has historical records to another session.']);
        }

        $session = AcademicSession::findOrFail($validated['academic_session_id']);
        $this->periods->assertCanonicalTerm(
            $session,
            (int) $validated['sequence'],
            $validated['start_date'],
            $validated['end_date'],
            $term->id,
        );

        $term->update([
            'academic_session_id' => $session->id,
            'sequence' => (int) $validated['sequence'],
            'name' => $this->periods->termLabel((int) $validated['sequence']),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return redirect()->route('admin.terms.index')->with('success', 'Term updated. Current-period changes are made from Academic Period.');
    }

    public function destroy(Term $term): RedirectResponse
    {
        $this->ensureTermBelongsToSchool($term);

        if ($this->periods->termHasDependents($term)) {
            return back()->withErrors(['term' => 'Cannot delete the current term or a term that has historical academic records.']);
        }

        $term->delete();

        return redirect()->route('admin.terms.index')->with('success', 'Term deleted.');
    }

    /**
     * @return array{academic_session_id: int, sequence: int, start_date: string, end_date: string}
     */
    protected function validateTerm(Request $request, ?Term $term = null): array
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'sequence' => ['required', 'integer', 'in:1,2,3'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        if ($term && (int) $validated['sequence'] !== (int) $term->sequence) {
            $this->assertUniqueSequence(
                AcademicSession::findOrFail($validated['academic_session_id']),
                (int) $validated['sequence'],
                $term->id,
            );
        }

        return $validated;
    }

    protected function ensureSessionBelongsToSchool(int $sessionId): void
    {
        abort_unless(
            AcademicSession::where('id', $sessionId)->where('school_id', $this->schoolId())->exists(),
            404
        );
    }

    protected function ensureTermBelongsToSchool(Term $term): void
    {
        abort_unless($term->academicSession?->school_id === $this->schoolId(), 404);
    }

    protected function assertUniqueSequence(AcademicSession $session, int $sequence, ?int $exceptId = null): void
    {
        $exists = Term::query()
            ->where('academic_session_id', $session->id)
            ->where('sequence', $sequence)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sequence' => 'That sequence is already used in this academic session.',
            ]);
        }
    }
}
