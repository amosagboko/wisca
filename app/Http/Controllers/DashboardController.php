<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Services\AtRiskCalculationService;
use App\Services\AttendanceCalculationService;
use App\Services\CurriculumDashboardService;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        CurriculumDashboardService $curriculum,
        AttendanceCalculationService $attendance,
        AtRiskCalculationService $atRisk,
    ): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // All sessions for this school, newest first
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')
            ->get();

        // Resolve selected session (defaults to current active one)
        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);

        if (! $session) {
            abort(403, 'No active academic session configured.');
        }

        session(['active_academic_session_id' => $session->id]);

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isParentRelationsLead()) {
            return redirect()->route('partnership.index');
        }

        if ($user->isStemCoordinator()) {
            return redirect()->route('stem.index');
        }

        if ($user->isItConsultant()) {
            return redirect()->route('lms.index');
        }

        if ($user->isBoard() || $user->isHoS()) {
            return view('dashboard.executive', [
                'summary'     => $dashboard->executiveSummary($user->school_id, $session->id),
                'session'     => $session,
                'allSessions' => $allSessions,
            ]);
        }

        if ($user->isHoD()) {
            // Additional server-side inbox filters
            $filterTeacherId = $request->integer('teacher_id');
            $filterClassId   = $request->integer('class_id');
            $filterSubjectId = $request->integer('subject_id');
            $filterStatus    = $request->query('status', ''); // submitted|verified|rejected|''

            $allTerms = Term::where('academic_session_id', $session->id)
                ->orderBy('start_date')->get();
            $termId = $request->integer('term_id');

            $data = $curriculum->hodOperations($user->school_id, $session);

            // Apply in-memory filters to each collection
            if ($filterTeacherId) {
                $data['pending']       = $data['pending']->where('teacher_id', $filterTeacherId)->values();
                $data['pending_plans'] = $data['pending_plans']->where('teacher_id', $filterTeacherId)->values();
                $data['homework_logs'] = $data['homework_logs']->where('teacher_id', $filterTeacherId)->values();
                $data['observations']  = $data['observations']->where('teacher_id', $filterTeacherId)->values();
                $data['coverage_rows'] = $data['coverage_rows']->filter(fn ($r) => $r['teacher_id'] === $filterTeacherId)->values();
            }
            if ($filterClassId) {
                $data['pending']       = $data['pending']->where('school_class_id', $filterClassId)->values();
                $data['pending_plans'] = $data['pending_plans']->where('school_class_id', $filterClassId)->values();
                $data['homework_logs'] = $data['homework_logs']->where('school_class_id', $filterClassId)->values();
                $data['attendance_logs'] = $data['attendance_logs']->where('school_class_id', $filterClassId)->values();
                $data['observations']  = $data['observations']->where('school_class_id', $filterClassId)->values();
                $data['coverage_rows'] = $data['coverage_rows']->filter(fn ($r) => $r['class_id'] === $filterClassId)->values();
            }
            if ($filterSubjectId) {
                $data['pending']       = $data['pending']->where('subject_id', $filterSubjectId)->values();
                $data['pending_plans'] = $data['pending_plans']->where('subject_id', $filterSubjectId)->values();
                $data['homework_logs'] = $data['homework_logs']->where('subject_id', $filterSubjectId)->values();
                $data['observations']  = $data['observations']->where('subject_id', $filterSubjectId)->values();
                $data['coverage_rows'] = $data['coverage_rows']->filter(fn ($r) => $r['subject_id'] === $filterSubjectId)->values();
            }

            // Re-group after filtering
            $data['pending_by_teacher'] = $data['pending']->groupBy('teacher_id');
            $data['pending_by_class']   = $data['pending']->sortBy(fn ($l) => $l->schoolClass->name)->groupBy('school_class_id');
            $data['plans_by_teacher']   = $data['pending_plans']->groupBy('teacher_id');
            $data['plans_by_class']     = $data['pending_plans']->sortBy(fn ($p) => $p->schoolClass->name)->groupBy('school_class_id');

            $hodFilters = compact(
                'sessionId', 'termId', 'filterTeacherId', 'filterClassId', 'filterSubjectId', 'filterStatus'
            );
            $hodActiveFilters = (bool) array_filter([
                $filterTeacherId, $filterClassId, $filterSubjectId, $filterStatus,
                $sessionId && $sessionId !== ($allSessions->first()?->id ?? 0),
            ]);

            return view('dashboard.hod', [
                ...$data,
                'session'          => $session,
                'allSessions'      => $allSessions,
                'allTerms'         => $allTerms,
                'hodFilters'       => $hodFilters,
                'hodActiveFilters' => $hodActiveFilters,
            ]);
        }

        if ($user->isAdminOfficer()) {
            return view('dashboard.officer', [
                ...$attendance->officerWeek($session),
                'session'     => $session,
                'allSessions' => $allSessions,
            ]);
        }

        if ($user->isLearningSupport()) {
            $allTerms = Term::where('academic_session_id', $session->id)
                ->orderBy('start_date')->get();
            $termId = $request->integer('term_id');
            $term   = $termId
                ? $allTerms->firstWhere('id', $termId)
                : Term::currentForSession($session->id);

            return view('dashboard.support', [
                'at_risk'     => $term
                    ? $atRisk->monthSummary($session, $term)
                    : ['identified' => 0, 'with_plan' => 0, 'without_plan' => 0, 'rate' => 0.0, 'records' => collect()],
                'unflagged'   => $term ? $atRisk->belowPassUnflagged($session, $term) : collect(),
                'session'     => $session,
                'term'        => $term,
                'allSessions' => $allSessions,
                'allTerms'    => $allTerms,
                'termId'      => $termId,
                'sessionId'   => $sessionId,
            ]);
        }

        if ($user->isTeacher()) {
            return view('dashboard.teacher', [
                ...$curriculum->teacherWeek($user, $session),
                'session'     => $session,
                'allSessions' => $allSessions,
            ]);
        }

        return view('dashboard.executive', [
            'summary'     => $dashboard->executiveSummary($user->school_id, $session->id),
            'session'     => $session,
            'allSessions' => $allSessions,
        ]);
    }
}
