<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\DisciplineIncident;
use App\Models\DisciplineIncidentType;
use App\Models\Learner;
use App\Models\Term;
use App\Services\RestorativeDisciplineCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DisciplineIncidentController extends Controller
{
    public function index(Request $request, RestorativeDisciplineCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $allSessions = AcademicSession::where('school_id', $user->school_id)->orderByDesc('start_date')->get();
        $sessionId = $request->integer('session_id');
        $session = $sessionId ? $allSessions->firstWhere('id', $sessionId) : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $termId = $request->integer('term_id');
        $term = $termId ? $allTerms->firstWhere('id', $termId) : Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $types = DisciplineIncidentType::where('school_id', $user->school_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $typeId = $request->integer('type_id');
        $status = (string) $request->query('status', '');
        $restorativeStatus = (string) $request->query('restorative_status', '');

        $summary = $service->monthSummary($session, $term);
        $rows = $service->incidentRows($session, $term, $typeId ?: null, $status, $restorativeStatus);

        $filters = compact('sessionId', 'termId', 'typeId', 'status', 'restorativeStatus');
        $activeFilters = (bool) ($sessionId || $termId || $typeId || $status !== '' || $restorativeStatus !== '');

        return view('discipline.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'types',
            'summary',
            'rows',
            'filters',
            'activeFilters'
        ));
    }

    public function create(): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $terms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $types = DisciplineIncidentType::where('school_id', $user->school_id)->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
        $learners = Learner::where('school_id', $user->school_id)->where('status', 'enrolled')->orderBy('name')->get();

        return view('discipline.form', [
            'incident' => new DisciplineIncident([
                'incident_date' => now()->toDateString(),
                'severity' => 'medium',
                'status' => 'open',
                'restorative_status' => 'pending',
            ]),
            'terms' => $terms,
            'types' => $types,
            'learners' => $learners,
            'session' => $session,
        ]);
    }

    public function store(Request $request, RestorativeDisciplineCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $data = $this->validateIncident($request, $user->school_id);
        $data['school_id'] = $user->school_id;
        $data['academic_session_id'] = $session->id;
        $data['reported_by'] = $user->id;
        if ($data['restorative_status'] === 'completed') {
            $data['restorative_completed_at'] = now();
            $data['status'] = 'resolved';
            $data['resolved_by'] = $user->id;
        }

        DisciplineIncident::create($data);
        $service->recalculateForSession($session, Term::find($data['term_id']));

        return redirect()->route('discipline.index')->with('success', 'Incident logged and CE-04 recalculated.');
    }

    public function edit(DisciplineIncident $disciplineIncident): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($disciplineIncident->school_id === $user->school_id, 404);

        $terms = Term::where('academic_session_id', $disciplineIncident->academic_session_id)->orderBy('start_date')->get();
        $types = DisciplineIncidentType::where('school_id', $user->school_id)->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
        $learners = Learner::where('school_id', $user->school_id)->where('status', 'enrolled')->orderBy('name')->get();

        return view('discipline.form', [
            'incident' => $disciplineIncident,
            'terms' => $terms,
            'types' => $types,
            'learners' => $learners,
            'session' => $disciplineIncident->academicSession,
        ]);
    }

    public function update(Request $request, DisciplineIncident $disciplineIncident, RestorativeDisciplineCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);
        abort_unless($disciplineIncident->school_id === $user->school_id, 404);

        $data = $this->validateIncident($request, $user->school_id);
        if ($data['restorative_status'] === 'completed' && $disciplineIncident->restorative_completed_at === null) {
            $data['restorative_completed_at'] = now();
            $data['resolved_by'] = $user->id;
        } elseif ($data['restorative_status'] !== 'completed') {
            $data['restorative_completed_at'] = null;
        }

        if ($data['status'] === 'resolved' && $disciplineIncident->status !== 'resolved') {
            $data['resolved_by'] = $user->id;
        }

        $disciplineIncident->update($data);
        $service->recalculateForSession($disciplineIncident->academicSession, $disciplineIncident->term);

        return redirect()->route('discipline.index')->with('success', 'Incident updated and CE-04 recalculated.');
    }

    private function validateIncident(Request $request, int $schoolId): array
    {
        return $request->validate([
            'discipline_incident_type_id' => ['required', 'integer', 'exists:discipline_incident_types,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'learner_id' => ['nullable', 'integer', 'exists:learners,id'],
            'incident_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:open,in_review,resolved'],
            'restorative_status' => ['required', 'in:not_required,pending,in_progress,completed'],
            'restorative_agreement' => ['nullable', 'string', 'max:1000'],
            'restorative_actions' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isLeadership()
            || $user->isHoD()
            || $user->isTeacher()
            || $user->hasRole('student_life_coordinator')
            || $user->hasRole('chaplain');
    }
}
