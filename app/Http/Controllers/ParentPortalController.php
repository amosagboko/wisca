<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\ParentPortalEngagement;
use App\Models\Term;
use App\Services\ParentPortalCalculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ParentPortalController extends Controller
{
    public function index(Request $request, ParentPortalCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $allSessions = AcademicSession::where('school_id', $user->school_id)->orderByDesc('start_date')->get();
        $sessionId = $request->integer('session_id');
        $session = $sessionId ? $allSessions->firstWhere('id', $sessionId) : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $term = Term::currentForSession($session->id);

        $monthStart = $request->string('month_start')->toString();
        if ($monthStart === '') {
            $monthStart = now()->startOfMonth()->toDateString();
        } else {
            $monthStart = Carbon::parse($monthStart)->startOfMonth()->toDateString();
        }

        $summary = $service->monthSummary($session, $monthStart);
        $rows = $service->familyRows($session, $monthStart);

        return view('portal-engagement.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'monthStart',
            'summary',
            'rows'
        ));
    }

    public function create(Request $request, ParentPortalCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $monthStart = $request->string('month_start')->toString();
        if ($monthStart === '') {
            $monthStart = now()->startOfMonth()->toDateString();
        } else {
            $monthStart = Carbon::parse($monthStart)->startOfMonth()->toDateString();
        }

        $rows = $service->familyRows($session, $monthStart);

        return view('portal-engagement.form', compact('session', 'term', 'monthStart', 'rows'));
    }

    public function store(Request $request, ParentPortalCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $data = $request->validate([
            'month_start_date' => ['required', 'date'],
            'rows' => ['required', 'array'],
            'rows.*.parent_id' => ['required', 'integer', 'exists:parents,id'],
            'rows.*.login_count' => ['nullable', 'integer', 'min:0'],
            'rows.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        $monthStart = Carbon::parse($data['month_start_date'])->startOfMonth()->toDateString();

        foreach ($data['rows'] as $row) {
            $guardian = Guardian::where('school_id', $user->school_id)->find($row['parent_id']);
            if (! $guardian) {
                continue;
            }

            $loginCount = (int) ($row['login_count'] ?? 0);
            $active = $loginCount > 0;

            ParentPortalEngagement::updateOrCreate(
                [
                    'parent_id' => $guardian->id,
                    'month_start_date' => $monthStart,
                ],
                [
                    'school_id' => $user->school_id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term?->id,
                    'login_count' => $loginCount,
                    'is_active_monthly' => $active,
                    'last_login_at' => $active ? now() : null,
                    'notes' => $row['notes'] ?? null,
                    'recorded_by' => $user->id,
                ]
            );
        }

        $service->recalculateForMonth($session, $monthStart, $term);

        return redirect()->route('portal-engagement.index', ['month_start' => $monthStart])
            ->with('success', 'Parent portal engagement saved and DI-06 recalculated.');
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isHoS()
            || $user->isItConsultant()
            || $user->isParentRelationsLead();
    }
}
