<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TermController extends AdminController
{
    public function index(): View
    {
        $terms = Term::whereHas('academicSession', fn ($q) => $q->where('school_id', $this->schoolId()))
            ->with('academicSession')
            ->orderByDesc('start_date')
            ->get();

        $sessions = AcademicSession::where('school_id', $this->schoolId())->orderByDesc('start_date')->get();

        return view('admin.terms.index', compact('terms', 'sessions'));
    }

    public function create(): View
    {
        return view('admin.terms.form', [
            'term' => new Term(['status' => 'upcoming']),
            'sessions' => AcademicSession::where('school_id', $this->schoolId())->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTerm($request);
        $this->ensureSessionBelongsToSchool($validated['academic_session_id']);

        Term::create($validated);

        return redirect()->route('admin.terms.index')->with('success', 'Term created.');
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

        $validated = $this->validateTerm($request);
        $this->ensureSessionBelongsToSchool($validated['academic_session_id']);

        $term->update($validated);

        return redirect()->route('admin.terms.index')->with('success', 'Term updated.');
    }

    public function destroy(Term $term): RedirectResponse
    {
        $this->ensureTermBelongsToSchool($term);
        $term->delete();

        return redirect()->route('admin.terms.index')->with('success', 'Term deleted.');
    }

    protected function validateTerm(Request $request): array
    {
        return $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:upcoming,active,closed'],
        ]);
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
}
