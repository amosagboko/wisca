<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\LmsUsageLog;
use App\Models\Term;
use App\Models\User;
use App\Services\LmsAdoptionCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LmsUsageController extends Controller
{
    public function index(Request $request, LmsAdoptionCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $allSessions = AcademicSession::where('school_id', $user->school_id)->orderByDesc('start_date')->get();
        $sessionId = $request->integer('session_id');
        $session = $sessionId ? $allSessions->firstWhere('id', $sessionId) : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $term = Term::currentForSession($session->id);

        $weekStartDate = $request->string('week_start')->toString();
        if ($weekStartDate === '') {
            $weekStartDate = now()->startOfWeek()->toDateString();
        }

        $summary = $service->weekSummary($session, $weekStartDate);
        $rows = $service->weeklyRows($session, $weekStartDate);

        return view('lms.index', compact(
            'session',
            'allSessions',
            'allTerms',
            'term',
            'weekStartDate',
            'summary',
            'rows'
        ));
    }

    public function create(Request $request): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $weekStartDate = $request->string('week_start')->toString();
        if ($weekStartDate === '') {
            $weekStartDate = now()->startOfWeek()->toDateString();
        }

        $staff = User::where('school_id', $user->school_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $learners = Learner::where('school_id', $user->school_id)
            ->where('status', 'enrolled')
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        $existing = LmsUsageLog::query()
            ->where('school_id', $user->school_id)
            ->where('academic_session_id', $session->id)
            ->where('week_start_date', $weekStartDate)
            ->get()
            ->keyBy(fn (LmsUsageLog $row) => $row->actor_type.':'.($row->user_id ?? $row->learner_id));

        return view('lms.form', compact('session', 'weekStartDate', 'staff', 'learners', 'existing'));
    }

    public function store(Request $request, LmsAdoptionCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $data = $request->validate([
            'week_start_date' => ['required', 'date'],
            'staff_rows' => ['required', 'array'],
            'staff_rows.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'staff_rows.*.login_count' => ['nullable', 'integer', 'min:0'],
            'staff_rows.*.activity_count' => ['nullable', 'integer', 'min:0'],
            'staff_rows.*.notes' => ['nullable', 'string', 'max:300'],
            'learner_rows' => ['required', 'array'],
            'learner_rows.*.learner_id' => ['required', 'integer', 'exists:learners,id'],
            'learner_rows.*.login_count' => ['nullable', 'integer', 'min:0'],
            'learner_rows.*.activity_count' => ['nullable', 'integer', 'min:0'],
            'learner_rows.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        foreach ($data['staff_rows'] as $row) {
            $loginCount = (int) ($row['login_count'] ?? 0);
            $activityCount = (int) ($row['activity_count'] ?? 0);

            LmsUsageLog::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'week_start_date' => $data['week_start_date'],
                    'user_id' => (int) $row['user_id'],
                ],
                [
                    'school_id' => $user->school_id,
                    'term_id' => $term?->id,
                    'actor_type' => 'staff',
                    'learner_id' => null,
                    'login_count' => $loginCount,
                    'activity_count' => $activityCount,
                    'is_active_weekly' => ($loginCount + $activityCount) > 0,
                    'notes' => $row['notes'] ?? null,
                    'recorded_by' => $user->id,
                ]
            );
        }

        foreach ($data['learner_rows'] as $row) {
            $loginCount = (int) ($row['login_count'] ?? 0);
            $activityCount = (int) ($row['activity_count'] ?? 0);

            LmsUsageLog::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'week_start_date' => $data['week_start_date'],
                    'learner_id' => (int) $row['learner_id'],
                ],
                [
                    'school_id' => $user->school_id,
                    'term_id' => $term?->id,
                    'actor_type' => 'learner',
                    'user_id' => null,
                    'login_count' => $loginCount,
                    'activity_count' => $activityCount,
                    'is_active_weekly' => ($loginCount + $activityCount) > 0,
                    'notes' => $row['notes'] ?? null,
                    'recorded_by' => $user->id,
                ]
            );
        }

        $service->recalculateForWeek($session, $data['week_start_date'], $term);

        return redirect()->route('lms.index', ['week_start' => $data['week_start_date']])
            ->with('success', 'LMS usage logs saved and DI-01 recalculated.');
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isLeadership()
            || $user->isIctCoordinator()
            || $user->isAdminManager();
    }
}
