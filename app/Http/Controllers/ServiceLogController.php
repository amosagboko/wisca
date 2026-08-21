<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ServiceActivityType;
use App\Models\ServiceLog;
use App\Models\Term;
use App\Services\ServiceHoursCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ServiceLogController extends Controller
{
    public function index(Request $request, ServiceHoursCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')
            ->get();

        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);

        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $termId = $request->integer('term_id');
        $term = $termId ? $allTerms->firstWhere('id', $termId) : Term::currentForSession($session->id);

        $activityTypes = ServiceActivityType::where('school_id', $user->school_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $typeId = $request->integer('type_id');
        $filterStatus = (string) $request->query('status', '');

        $summary = $service->sessionSummary($session, $term ?: null);
        $rows = $service->logRows($session, $term ?: null, $typeId ?: null, $filterStatus);
        $typeTotals = $service->activityTypeTotals($session, $term ?: null);

        $activeFilters = (bool) ($sessionId || $termId || $typeId || $filterStatus !== '');
        $filters = compact('sessionId', 'termId', 'typeId', 'filterStatus');
        $canVerify = $this->canVerify($user);

        return view('service.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'activityTypes',
            'summary',
            'rows',
            'typeTotals',
            'filters',
            'activeFilters',
            'canVerify'
        ));
    }

    public function create(): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $terms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $activityTypes = ServiceActivityType::where('school_id', $user->school_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('service.form', [
            'log' => new ServiceLog([
                'service_date' => now()->toDateString(),
                'participant_count' => 0,
                'verified_hours' => 0,
                'status' => 'draft',
            ]),
            'session' => $session,
            'terms' => $terms,
            'activityTypes' => $activityTypes,
            'canVerify' => $this->canVerify($user),
        ]);
    }

    public function store(Request $request, ServiceHoursCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $validated = $this->validateLog($request);
        $status = $request->input('action') === 'submit' ? 'submitted' : 'draft';

        ServiceLog::create([
            ...$validated,
            'school_id' => $user->school_id,
            'academic_session_id' => $session->id,
            'submitted_by' => $user->id,
            'status' => $status,
        ]);

        $service->recalculateForSession($session, $validated['term_id'] ? Term::find($validated['term_id']) : null);

        return redirect()->route('service.index')
            ->with('success', $status === 'submitted' ? 'Service log submitted for verification.' : 'Service log saved as draft.');
    }

    public function edit(ServiceLog $serviceLog): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($serviceLog->school_id === $user->school_id, 404);

        $session = $serviceLog->academicSession;
        $terms = $session ? Term::where('academic_session_id', $session->id)->orderBy('start_date')->get() : collect();
        $activityTypes = ServiceActivityType::where('school_id', $user->school_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('service.form', [
            'log' => $serviceLog,
            'session' => $session,
            'terms' => $terms,
            'activityTypes' => $activityTypes,
            'canVerify' => $this->canVerify($user),
        ]);
    }

    public function update(Request $request, ServiceLog $serviceLog, ServiceHoursCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($serviceLog->school_id === $user->school_id, 404);

        $validated = $this->validateLog($request);
        $status = $request->input('action') === 'submit' ? 'submitted' : 'draft';

        if ($serviceLog->isVerified() && ! $this->canVerify($user)) {
            return back()->withErrors(['log' => 'Verified service logs can only be edited by a verifier.']);
        }

        $serviceLog->update([
            ...$validated,
            'status' => $status,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        $service->recalculateForSession($serviceLog->academicSession, $serviceLog->term);

        return redirect()->route('service.index')
            ->with('success', $status === 'submitted' ? 'Service log resubmitted for verification.' : 'Service log updated.');
    }

    public function verify(ServiceLog $serviceLog, ServiceHoursCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canVerify($user), 403);
        abort_unless($serviceLog->school_id === $user->school_id, 404);

        $serviceLog->update([
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $service->recalculateForSession($serviceLog->academicSession, $serviceLog->term);

        return redirect()->route('service.index')
            ->with('success', 'Service log verified and CE-03 recalculated.');
    }

    public function reject(ServiceLog $serviceLog, ServiceHoursCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canVerify($user), 403);
        abort_unless($serviceLog->school_id === $user->school_id, 404);

        $serviceLog->update([
            'status' => 'rejected',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $service->recalculateForSession($serviceLog->academicSession, $serviceLog->term);

        return redirect()->route('service.index')
            ->with('success', 'Service log rejected.');
    }

    private function validateLog(Request $request): array
    {
        return $request->validate([
            'service_activity_type_id' => ['required', 'exists:service_activity_types,id'],
            'term_id' => ['nullable', 'exists:terms,id'],
            'service_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'participant_count' => ['required', 'integer', 'min:0'],
            'verified_hours' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isHoS()
            || $user->isHoD()
            || $user->isTeacher()
            || $user->hasRole('student_life_coordinator');
    }

    private function canVerify($user): bool
    {
        return $user->isAdmin()
            || $user->isHoS()
            || $user->hasRole('student_life_coordinator');
    }
}
