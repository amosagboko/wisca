<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicSessionController extends AdminController
{
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
            'status' => ['required', 'in:upcoming,active,closed'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $session = AcademicSession::create([
            ...$validated,
            'school_id' => $this->schoolId(),
            'is_current' => $request->boolean('is_current'),
        ]);

        if ($session->is_current) {
            $session->markAsCurrent();
        }

        return redirect()->route('admin.sessions.index')->with('success', 'Academic session created.');
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
            'status' => ['required', 'in:upcoming,active,closed'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $session->update([
            ...$validated,
            'is_current' => $request->boolean('is_current'),
        ]);

        if ($session->is_current) {
            $session->markAsCurrent();
        }

        return redirect()->route('admin.sessions.index')->with('success', 'Academic session updated.');
    }

    public function destroy(AcademicSession $session): RedirectResponse
    {
        abort_unless($session->school_id === $this->schoolId(), 404);

        if ($session->is_current) {
            return back()->withErrors(['session' => 'Cannot delete the current academic session. Set another session as current first.']);
        }

        $session->delete();

        return redirect()->route('admin.sessions.index')->with('success', 'Academic session deleted.');
    }

    public function setCurrent(AcademicSession $session): RedirectResponse
    {
        abort_unless($session->school_id === $this->schoolId(), 404);

        $session->markAsCurrent();

        return back()->with('success', "{$session->name} is now the current session.");
    }
}
