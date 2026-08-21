<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\ReadingAssessment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\ReadingCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReadingAssessmentController extends Controller
{
    public function index(Request $request, ReadingCalculationService $service): View
    {
        $user = auth()->user();
        abort_unless($user->canViewReading(), 403);

        // All sessions for this school (for the session filter dropdown)
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')
            ->get();

        // Resolve selected session (defaults to current)
        $sessionId = (int) $request->query('session_id', 0);
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);

        abort_unless($session, 403, 'No active academic session configured.');

        // Terms for the selected session
        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();

        // Resolve selected term (null = all terms in session)
        $termId = (int) $request->query('term_id', 0);
        $term = $termId ? $allTerms->firstWhere('id', $termId) : Term::currentForSession($session->id);

        // All classes accessible to this user
        $allClassIds = $this->classIds($user, $session->id);
        $allClasses  = SchoolClass::whereIn('id', $allClassIds)->orderBy('name')->get();

        // Resolve selected class (null = all classes)
        $classId = (int) $request->query('class_id', 0);
        $filteredClassIds = ($classId && in_array($classId, $allClassIds, true))
            ? [$classId]
            : $allClassIds;

        $summaries    = $service->classSummaries($session, $filteredClassIds, null, $term);
        $schoolSummary = $service->sessionSummary($session, null, $term);

        $filters = compact('sessionId', 'termId', 'classId');

        return view('reading.index', compact(
            'summaries', 'schoolSummary', 'session', 'term',
            'allSessions', 'allTerms', 'allClasses',
            'filters'
        ));
    }

    public function create(Request $request, ReadingCalculationService $service): View
    {
        $user = auth()->user();
        abort_unless($user->canRecordReading(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403);

        $classIds = $this->classIds($user, $session->id);
        $selectedClassId = (int) $request->query('class', $classIds[0] ?? 0);
        abort_unless(in_array($selectedClassId, $classIds, true), 403);

        $classes = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        $unassessed = $service->unassessedLearners($session, $selectedClassId);
        $assessments = ReadingAssessment::where('academic_session_id', $session->id)
            ->where('school_class_id', $selectedClassId)
            ->with('learner')
            ->orderBy('learner_id')
            ->get()
            ->keyBy('learner_id');

        $enrolled = Learner::where('school_class_id', $selectedClassId)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        return view('reading.form', compact(
            'session', 'classes', 'selectedClassId', 'enrolled', 'assessments', 'unassessed'
        ));
    }

    public function store(Request $request, ReadingCalculationService $service): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canRecordReading(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403);
        $term = Term::currentForSession($session->id);

        $classIds = $this->classIds($user, $session->id);
        $classId = (int) $request->input('school_class_id');
        abort_unless(in_array($classId, $classIds, true), 403);

        $validated = $request->validate([
            'school_class_id'          => ['required', 'integer'],
            'rows'                     => ['required', 'array'],
            'rows.*.learner_id'        => ['required', 'integer', 'exists:learners,id'],
            'rows.*.baseline_level'    => ['nullable', 'numeric', 'min:0', 'max:20'],
            'rows.*.followup_level'    => ['nullable', 'numeric', 'min:0', 'max:20'],
            'rows.*.baseline_date'     => ['nullable', 'date'],
            'rows.*.followup_date'     => ['nullable', 'date'],
            'rows.*.notes'             => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated['rows'] as $row) {
            $learnerId = (int) $row['learner_id'];
            ReadingAssessment::updateOrCreate(
                [
                    'learner_id'          => $learnerId,
                    'academic_session_id' => $session->id,
                ],
                [
                    'school_class_id'      => $classId,
                    'term_id'              => $term?->id,
                    'recorded_by'          => $user->id,
                    'baseline_level'       => $row['baseline_level'] ?? null,
                    'followup_level'       => $row['followup_level'] ?? null,
                    'baseline_checkpoint'  => 'start_of_session',
                    'followup_checkpoint'  => 'end_of_session',
                    'baseline_date'        => $row['baseline_date'] ?? null,
                    'followup_date'        => $row['followup_date'] ?? null,
                    'notes'                => $row['notes'] ?? null,
                ]
            );
        }

        $service->recalculateForSession($session, $term);

        return redirect()->route('reading.index')->with('status', 'Reading assessments saved and AE-08 recalculated.');
    }

    /**
     * Return accessible class IDs for the current user.
     *
     * @return int[]
     */
    protected function classIds($user, int $sessionId): array
    {
        if ($user->isAdmin() || $user->isHoS() || $user->isLiteracyCoordinator()) {
            return SchoolClass::where('school_id', $user->school_id)->pluck('id')->all();
        }

        if ($user->isHoD()) {
            return SchoolClass::where('school_id', $user->school_id)
                ->where('department_id', $user->department_id)
                ->pluck('id')->all();
        }

        // teacher: only their assigned classes
        return \App\Models\TeacherAssignment::where('teacher_id', $user->id)
            ->where('academic_session_id', $sessionId)
            ->pluck('school_class_id')
            ->unique()->values()->all();
    }
}
