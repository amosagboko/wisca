<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\StemProjectCompletion;
use App\Models\Term;
use App\Services\StemProjectCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StemProjectController extends Controller
{
    public function index(Request $request, StemProjectCalculationService $service): View
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

        $types = $service->activeTypesForSchool($user->school_id);
        $typeId = $request->integer('type_id');
        $status = (string) $request->query('status', '');

        $summary = $service->sessionSummary($session, $term ?: null);
        $classSummaries = $service->classSummaries($session, $term ?: null);
        $rows = $service->completionRows($session, $term ?: null, $typeId ?: null, $status);

        $filters = compact('sessionId', 'termId', 'typeId', 'status');
        $activeFilters = (bool) ($sessionId || $termId || $typeId || $status !== '');

        return view('stem.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'types',
            'summary',
            'classSummaries',
            'rows',
            'filters',
            'activeFilters'
        ));
    }

    public function create(Request $request, StemProjectCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $types = $service->activeTypesForSchool($user->school_id);
        abort_unless($types->isNotEmpty(), 403, 'No active STEM project types configured.');

        $classIds = $this->accessibleClassIds($user, $session->id);
        $classes = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        $selectedClassId = $request->integer('class', $classes->first()?->id ?? 0);
        $selectedTypeId = $request->integer('type', $types->first()?->id ?? 0);
        abort_unless(in_array($selectedClassId, $classIds, true), 403);

        $learners = Learner::where('school_class_id', $selectedClassId)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        $existing = StemProjectCompletion::query()
            ->where('school_id', $user->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->where('stem_project_type_id', $selectedTypeId)
            ->whereIn('learner_id', $learners->pluck('id'))
            ->get()
            ->keyBy('learner_id');

        return view('stem.form', compact(
            'session',
            'term',
            'classes',
            'types',
            'selectedClassId',
            'selectedTypeId',
            'learners',
            'existing'
        ));
    }

    public function store(Request $request, StemProjectCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $classIds = $this->accessibleClassIds($user, $session->id);
        $classId = (int) $request->input('school_class_id');
        abort_unless(in_array($classId, $classIds, true), 403);

        $data = $request->validate([
            'stem_project_type_id' => ['required', 'integer', 'exists:stem_project_types,id'],
            'rows' => ['required', 'array'],
            'rows.*.learner_id' => ['required', 'integer', 'exists:learners,id'],
            'rows.*.status' => ['required', 'in:not_started,in_progress,completed'],
            'rows.*.score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rows.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        foreach ($data['rows'] as $row) {
            $learner = Learner::where('school_id', $user->school_id)
                ->where('school_class_id', $classId)
                ->find($row['learner_id']);

            if (! $learner) {
                continue;
            }

            $status = $row['status'];

            StemProjectCompletion::updateOrCreate(
                [
                    'learner_id' => $learner->id,
                    'stem_project_type_id' => (int) $data['stem_project_type_id'],
                    'academic_session_id' => $session->id,
                    'term_id' => $term?->id,
                ],
                [
                    'school_id' => $user->school_id,
                    'school_class_id' => $classId,
                    'status' => $status,
                    'completed_on' => $status === 'completed' ? now()->toDateString() : null,
                    'score' => $row['score'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'assessed_by' => $user->id,
                ]
            );
        }

        $service->recalculateForSession($session, $term);

        return redirect()->route('stem.index')
            ->with('success', 'STEM project completions saved and DI-02 recalculated.');
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isLeadership()
            || $user->isHoD()
            || $user->isTeacher()
            || $user->isIctCoordinator()
            || $user->isStemCoordinator();
    }

    /**
     * @return array<int, int>
     */
    private function accessibleClassIds($user, int $sessionId): array
    {
        if ($user->isAdmin() || $user->isLeadership() || $user->isHoD() || $user->isIctCoordinator() || $user->isStemCoordinator()) {
            return SchoolClass::where('school_id', $user->school_id)->pluck('id')->all();
        }

        return $user->teacherAssignments()
            ->where('academic_session_id', $sessionId)
            ->pluck('school_class_id')
            ->unique()
            ->values()
            ->all();
    }
}
