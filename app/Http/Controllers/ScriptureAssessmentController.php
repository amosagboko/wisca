<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\ScriptureAssessment;
use App\Models\Term;
use App\Services\ScriptureCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScriptureAssessmentController extends Controller
{
    public function index(Request $request, ScriptureCalculationService $service): View
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

        $passages = $service->activePassagesForSchool($user->school_id);
        $passageId = $request->integer('passage_id');

        $summary = $service->sessionSummary($session, $term ?: null);
        $classSummaries = $service->classSummaries($session, $term ?: null);
        $rows = $service->assessmentRows($session, $term ?: null, $passageId ?: null);

        $filters = compact('sessionId', 'termId', 'passageId');
        $activeFilters = (bool) ($sessionId || $termId || $passageId);

        return view('scripture.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'passages',
            'summary',
            'classSummaries',
            'rows',
            'filters',
            'activeFilters'
        ));
    }

    public function create(Request $request, ScriptureCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $passages = $service->activePassagesForSchool($user->school_id);
        abort_unless($passages->isNotEmpty(), 403, 'No active scripture passages configured.');

        $classIds = $this->accessibleClassIds($user, $session->id);
        $classes = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        $selectedClassId = $request->integer('class', $classes->first()?->id ?? 0);
        $selectedPassageId = $request->integer('passage', $passages->first()?->id ?? 0);
        abort_unless(in_array($selectedClassId, $classIds, true), 403);

        $learners = Learner::where('school_class_id', $selectedClassId)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        $existing = ScriptureAssessment::query()
            ->where('school_id', $user->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->where('scripture_passage_id', $selectedPassageId)
            ->whereIn('learner_id', $learners->pluck('id'))
            ->get()
            ->keyBy('learner_id');

        return view('scripture.form', compact(
            'session',
            'term',
            'classes',
            'passages',
            'selectedClassId',
            'selectedPassageId',
            'learners',
            'existing'
        ));
    }

    public function store(Request $request, ScriptureCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);

        $classIds = $this->accessibleClassIds($user, $session->id);
        $classId = (int) $request->input('school_class_id');
        abort_unless(in_array($classId, $classIds, true), 403);

        $request->validate([
            'scripture_passage_id' => ['required', 'integer', 'exists:scripture_passages,id'],
            'rows' => ['required', 'array'],
            'rows.*.learner_id' => ['required', 'integer', 'exists:learners,id'],
            'rows.*.recites_correctly' => ['nullable', 'boolean'],
            'rows.*.explains_contextually' => ['nullable', 'boolean'],
            'rows.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        $passageId = (int) $request->input('scripture_passage_id');

        foreach ($request->input('rows') as $row) {
            ScriptureAssessment::updateOrCreate(
                [
                    'learner_id' => (int) $row['learner_id'],
                    'scripture_passage_id' => $passageId,
                    'academic_session_id' => $session->id,
                    'term_id' => $term?->id,
                ],
                [
                    'school_id' => $user->school_id,
                    'school_class_id' => $classId,
                    'assessed_on' => now()->toDateString(),
                    'recites_correctly' => (bool) ($row['recites_correctly'] ?? false),
                    'explains_contextually' => (bool) ($row['explains_contextually'] ?? false),
                    'notes' => $row['notes'] ?? null,
                    'assessed_by' => $user->id,
                ]
            );
        }

        $service->recalculateForSession($session, $term);

        return redirect()->route('scripture.index')->with('success', 'Scripture assessments saved and CE-06 recalculated.');
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isLeadership()
            || $user->isHoD()
            || $user->isTeacher()
            || $user->hasRole('chaplain')
            || $user->hasRole('student_life_coordinator');
    }

    private function accessibleClassIds($user, int $sessionId): array
    {
        if ($user->isAdmin() || $user->isHoS() || $user->hasRole('chaplain') || $user->hasRole('student_life_coordinator')) {
            return SchoolClass::where('school_id', $user->school_id)->pluck('id')->all();
        }

        if ($user->isHoD()) {
            return SchoolClass::where('school_id', $user->school_id)
                ->where('department_id', $user->department_id)
                ->pluck('id')->all();
        }

        return \App\Models\TeacherAssignment::where('teacher_id', $user->id)
            ->where('academic_session_id', $sessionId)
            ->pluck('school_class_id')
            ->unique()
            ->values()
            ->all();
    }
}
