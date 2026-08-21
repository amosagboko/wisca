<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use App\Models\ChapelActivityType;
use App\Models\ChapelSession;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChapelSessionAdminController extends AdminController
{
    public function index(Request $request): View
    {
        $allSessions = AcademicSession::where('school_id', $this->schoolId())
            ->orderByDesc('start_date')->get();

        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($this->schoolId());

        $allTerms   = $session ? Term::where('academic_session_id', $session->id)->orderBy('start_date')->get() : collect();
        $termId     = $request->integer('term_id');
        $typeId     = $request->integer('type_id');
        $filterStatus = $request->query('status', '');

        $activityTypes = ChapelActivityType::where('school_id', $this->schoolId())
            ->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();

        $sessions = ChapelSession::where('school_id', $this->schoolId())
            ->when($session,      fn ($q) => $q->where('academic_session_id', $session->id))
            ->when($termId,       fn ($q) => $q->where('term_id', $termId))
            ->when($typeId,       fn ($q) => $q->where('chapel_activity_type_id', $typeId))
            ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
            ->with(['activityType', 'leader', 'term'])
            ->orderByDesc('session_date')
            ->get();

        $filters = compact('sessionId', 'termId', 'typeId', 'filterStatus');

        return view('admin.chapel.sessions.index', compact(
            'sessions', 'session', 'allSessions', 'allTerms', 'activityTypes', 'filters'
        ));
    }

    public function create(Request $request): View
    {
        $session = AcademicSession::currentForSchool($this->schoolId());

        $activityTypes = ChapelActivityType::where('school_id', $this->schoolId())
            ->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();

        $terms = $session
            ? Term::where('academic_session_id', $session->id)->orderBy('start_date')->get()
            : collect();

        $staff = $this->staffUsers();

        return view('admin.chapel.sessions.form', [
            'chapelSession' => new ChapelSession(['status' => 'scheduled', 'session_date' => now()->toDateString()]),
            'activityTypes' => $activityTypes,
            'session'       => $session,
            'terms'         => $terms,
            'staff'         => $staff,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSession($request);

        ChapelSession::create([
            ...$validated,
            'school_id'           => $this->schoolId(),
            'academic_session_id' => AcademicSession::currentForSchool($this->schoolId())?->id,
        ]);

        return redirect()->route('admin.chapel-sessions.index')
            ->with('success', 'Session scheduled.');
    }

    public function edit(ChapelSession $chapelSession): View
    {
        abort_unless($chapelSession->school_id === $this->schoolId(), 404);

        $activityTypes = ChapelActivityType::where('school_id', $this->schoolId())
            ->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();

        $session = $chapelSession->academicSession;
        $terms   = $session
            ? Term::where('academic_session_id', $session->id)->orderBy('start_date')->get()
            : collect();

        $staff = $this->staffUsers();

        return view('admin.chapel.sessions.form', [
            'chapelSession' => $chapelSession,
            'activityTypes' => $activityTypes,
            'session'       => $session,
            'terms'         => $terms,
            'staff'         => $staff,
        ]);
    }

    public function update(Request $request, ChapelSession $chapelSession): RedirectResponse
    {
        abort_unless($chapelSession->school_id === $this->schoolId(), 404);

        $chapelSession->update($this->validateSession($request));

        return redirect()->route('admin.chapel-sessions.index')
            ->with('success', 'Session updated.');
    }

    public function destroy(ChapelSession $chapelSession): RedirectResponse
    {
        abort_unless($chapelSession->school_id === $this->schoolId(), 404);

        $chapelSession->delete();

        return redirect()->route('admin.chapel-sessions.index')
            ->with('success', 'Session deleted.');
    }

    private function validateSession(Request $request): array
    {
        return $request->validate([
            'chapel_activity_type_id' => ['required', 'exists:chapel_activity_types,id'],
            'term_id'                 => ['nullable', 'exists:terms,id'],
            'session_date'            => ['required', 'date'],
            'theme'                   => ['nullable', 'string', 'max:200'],
            'scripture_reference'     => ['nullable', 'string', 'max:100'],
            'led_by'                  => ['nullable', 'exists:users,id'],
            'status'                  => ['required', 'in:scheduled,held,cancelled'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
