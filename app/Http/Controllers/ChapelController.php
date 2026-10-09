<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ChapelActivityType;
use App\Models\ChapelAttendance;
use App\Models\ChapelSession;
use App\Models\Learner;
use App\Models\Term;
use App\Services\ChapelCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChapelController extends Controller
{
    public function index(Request $request, ChapelCalculationService $service): View
    {
        $user = auth()->user();
        abort_unless(
            $user->isAdmin() || $user->isLeadership() || $user->isHoD()
                || $user->isTeacher() || $user->isChaplain() || $user->isAdminOfficer()
                || $user->isStudentLifeCoordinator(),
            403
        );

        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')->get();

        $sessionId = $request->integer('session_id');
        $session   = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);

        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $termId   = $request->integer('term_id');
        $term     = $termId ? $allTerms->firstWhere('id', $termId) : Term::currentForSession($session->id);

        $activityTypes = ChapelActivityType::where('school_id', $user->school_id)
            ->where('is_active', true)->orderBy('display_order')->get();

        $typeId       = $request->integer('type_id');
        $filterStatus = $request->query('status', '');

        $summary  = $service->sessionSummary($session, $term ?: null);
        $rows     = $service->sessionRows($session, $term ?: null, $typeId ?: null);

        // Further status filter
        if ($filterStatus !== '') {
            $rows = $rows->filter(fn ($r) => $r['session']->status === $filterStatus)->values();
        }

        $activeFilters = $sessionId || $termId || $typeId || $filterStatus !== '';
        $filters = compact('sessionId', 'termId', 'typeId', 'filterStatus');

        return view('chapel.index', compact(
            'session', 'term', 'allSessions', 'allTerms', 'activityTypes',
            'summary', 'rows', 'filters', 'activeFilters'
        ));
    }

    /**
     * Show the roll-taking form for a single chapel session.
     */
    public function roll(ChapelSession $chapelSession): View
    {
        $user = auth()->user();
        abort_unless(
            $user->isAdmin() || $user->isLeadership() || $user->isHoD()
                || $user->isTeacher() || $user->isChaplain() || $user->isAdminOfficer()
                || $user->isStudentLifeCoordinator(),
            403
        );
        abort_unless($chapelSession->school_id === $user->school_id, 403);

        // All enrolled learners (school-wide for school_wide types, otherwise by class)
        $learners = Learner::where('school_id', $user->school_id)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        // Existing attendance rows keyed by learner_id
        $existing = ChapelAttendance::where('chapel_session_id', $chapelSession->id)
            ->get()->keyBy('learner_id');

        $chapelSession->load(['activityType', 'term', 'leader']);

        return view('chapel.roll', compact('chapelSession', 'learners', 'existing'));
    }

    /**
     * Save the roll for a session and recalculate CE-01.
     */
    public function saveRoll(Request $request, ChapelSession $chapelSession, ChapelCalculationService $service): RedirectResponse
    {
        $user = auth()->user();
        abort_unless(
            $user->isAdmin() || $user->isLeadership() || $user->isHoD()
                || $user->isTeacher() || $user->isChaplain() || $user->isAdminOfficer()
                || $user->isStudentLifeCoordinator(),
            403
        );
        abort_unless($chapelSession->school_id === $user->school_id, 403);

        $request->validate([
            'rows'                          => ['required', 'array'],
            'rows.*.learner_id'             => ['required', 'integer', 'exists:learners,id'],
            'rows.*.status'                 => ['required', 'in:present,absent,late'],
            'rows.*.participation_level'    => ['required', 'in:passive,active,leading'],
            'rows.*.notes'                  => ['nullable', 'string', 'max:300'],
        ]);

        foreach ($request->input('rows') as $row) {
            ChapelAttendance::updateOrCreate(
                [
                    'chapel_session_id' => $chapelSession->id,
                    'learner_id'        => (int) $row['learner_id'],
                ],
                [
                    'status'              => $row['status'],
                    'participation_level' => $row['participation_level'],
                    'notes'               => $row['notes'] ?? null,
                    'recorded_by'         => $user->id,
                ]
            );
        }

        // Mark session as held once roll is taken
        if ($chapelSession->status === 'scheduled') {
            $chapelSession->update(['status' => 'held']);
        }

        $service->recalculateForSession(
            $chapelSession->academicSession,
            $chapelSession->term
        );

        return redirect()->route('chapel.index')
            ->with('success', "Roll saved for {$chapelSession->session_date->format('d M Y')} · CE-01 recalculated.");
    }
}
