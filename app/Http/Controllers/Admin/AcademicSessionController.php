<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use App\Models\Term;
use App\Services\AcademicPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcademicSessionController extends AdminController
{
    public function __construct(protected AcademicPeriodService $periods) {}

    public function index(): View
    {
        $sessions = AcademicSession::where('school_id', $this->schoolId())
            ->withCount('terms')
            ->orderByDesc('start_date')
            ->get();

        return view('admin.sessions.index', compact('sessions'));
    }

    public function create(): View
    {
        return view('admin.sessions.form', ['session' => new AcademicSession(['status' => 'upcoming'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'activate' => ['sometimes', 'boolean'],
            'first_term_name' => ['nullable', 'string', 'max:255'],
        ]);

        $activate = $request->boolean('activate');

        $session = DB::transaction(function () use ($validated, $activate) {
            $session = AcademicSession::create([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'school_id' => $this->schoolId(),
                'is_current' => false,
                'status' => 'upcoming',
            ]);

            if ($activate) {
                $term = Term::create([
                    'academic_session_id' => $session->id,
                    'name' => $validated['first_term_name'] ?: 'First Term',
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'sequence' => 1,
                    'status' => 'upcoming',
                    'is_current' => false,
                ]);

                $this->periods->activateSession(auth()->user(), $session, $term);
            }

            return $session;
        });

        if ($activate) {
            return redirect()->route('admin.sessions.index')
                ->with('success', $session->name.' is now the current academic session. Add further terms if this year has more than one.');
        }

        return redirect()->route('admin.sessions.index')
            ->with('success', 'Future academic session created. Add terms, then activate it — admin does not need another role to approve.');
    }

    public function activate(AcademicSession $session): RedirectResponse
    {
        abort_unless($session->school_id === $this->schoolId(), 404);

        $this->periods->activateSession(auth()->user(), $session);

        return redirect()->route('admin.sessions.index')->with('success', $session->name.' is now the current academic session.');
    }

    public function edit(AcademicSession $session): View
    {
        abort_unless($session->school_id === $this->schoolId(), 404);

        return view('admin.sessions.form', compact('session'));
    }

    public function update(Request $request, AcademicSession $session): RedirectResponse
    {
        abort_unless($session->school_id === $this->schoolId(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $session->update($validated);

        return redirect()->route('admin.sessions.index')->with('success', 'Academic session updated.');
    }

    public function destroy(AcademicSession $session): RedirectResponse
    {
        abort_unless($session->school_id === $this->schoolId(), 404);

        if ($session->is_current || $this->periods->sessionHasDependents($session)) {
            return back()->withErrors(['session' => 'Cannot delete the current session or a session that has historical academic records.']);
        }

        $session->delete();

        return redirect()->route('admin.sessions.index')->with('success', 'Academic session deleted.');
    }
}
