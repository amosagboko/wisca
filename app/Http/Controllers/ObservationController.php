<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Observation;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Services\ObservationCalculationService;
use App\Support\ObservationRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ObservationController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->canViewObservations(), 403);

        // All sessions for this school
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')->get();

        // Resolve selected session
        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        // Terms for selected session
        $allTerms = Term::where('academic_session_id', $session->id)
            ->orderBy('start_date')->get();

        $termId      = $request->integer('term_id');
        $filterClass = $request->integer('class_id');
        $filterTeacher = $request->integer('teacher_id');
        $filterStatus  = $request->query('status', '');   // 'completed','follow_up_required','scheduled',''
        $filterOutcome = $request->query('outcome', '');  // 'effective','needs_work',''
        $search        = trim((string) $request->query('search', ''));

        $query = Observation::where('academic_session_id', $session->id)
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when($termId,         fn ($q) => $q->where('term_id', $termId))
            ->when($filterClass,    fn ($q) => $q->where('school_class_id', $filterClass))
            ->when($filterTeacher,  fn ($q) => $q->where('teacher_id', $filterTeacher))
            ->when($filterStatus !== '', fn ($q) => $q->where('status', $filterStatus))
            ->when($search !== '', fn ($q) => $q->whereHas('teacher', fn ($tq) => $tq
                ->where('name', 'like', '%'.$search.'%')))
            ->with(['teacher', 'observer', 'schoolClass', 'subject'])
            ->latest('observation_date');

        // Teachers can only see their own observations
        if ($user->isTeacher() && ! $user->canConductObservation()) {
            $query->where('teacher_id', $user->id);
        }

        $observations = $query->get();

        // Outcome filter applied in-memory (requires loaded overall_score)
        if ($filterOutcome === 'effective') {
            $observations = $observations->filter->isEffective()->values();
        } elseif ($filterOutcome === 'needs_work') {
            $observations = $observations->reject->isEffective()->filter->isCompleted()->values();
        }

        // Build dropdown options from ALL observations in this session (unfiltered) for staff
        $isStaff   = $user->canConductObservation();
        $allForSession = $isStaff
            ? Observation::where('academic_session_id', $session->id)
                ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
                ->with(['teacher', 'schoolClass', 'subject'])->get()
            : collect();

        $allTeachers = $allForSession->pluck('teacher')->filter()->unique('id')->sortBy('name')->values();
        $allClasses  = $allForSession->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();

        $filters = compact(
            'sessionId', 'termId', 'filterClass', 'filterTeacher',
            'filterStatus', 'filterOutcome', 'search'
        );

        return view('observations.index', [
            'observations' => $observations,
            'session'      => $session,
            'canConduct'   => $isStaff,
            'allSessions'  => $allSessions,
            'allTerms'     => $allTerms,
            'allTeachers'  => $allTeachers,
            'allClasses'   => $allClasses,
            'filters'      => $filters,
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();
        abort_unless($user->canConductObservation(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        return view('observations.form', [
            'observation' => new Observation([
                'observation_date' => now()->toDateString(),
                'teacher_id' => request('teacher'),
                'school_class_id' => request('class'),
                'subject_id' => request('subject'),
            ]),
            'assignments' => $this->assignments($session->id),
            'session' => $session,
            'standards' => ObservationRubric::standards(),
            'scale' => ObservationRubric::SCALE,
        ]);
    }

    public function store(Request $request, ObservationCalculationService $calculator): RedirectResponse
    {
        return $this->persist($request, $calculator, new Observation);
    }

    public function show(Observation $observation): View
    {
        $this->assertCanView($observation);
        $observation->load(['teacher', 'observer', 'schoolClass', 'subject']);

        return view('observations.show', [
            'observation' => $observation,
            'standards' => ObservationRubric::standards(),
            'scale' => ObservationRubric::SCALE,
            'canConduct' => auth()->user()->canConductObservation(),
        ]);
    }

    public function edit(Observation $observation): View
    {
        $this->assertCanWrite($observation);

        $session = AcademicSession::currentForSchool(auth()->user()->school_id);

        return view('observations.form', [
            'observation' => $observation,
            'assignments' => $this->assignments($session->id),
            'session' => $session,
            'standards' => ObservationRubric::standards(),
            'scale' => ObservationRubric::SCALE,
        ]);
    }

    public function update(Request $request, Observation $observation, ObservationCalculationService $calculator): RedirectResponse
    {
        $this->assertCanWrite($observation);

        return $this->persist($request, $calculator, $observation);
    }

    public function destroy(Observation $observation, ObservationCalculationService $calculator): RedirectResponse
    {
        $this->assertCanWrite($observation);

        $session = $observation->academicSession;
        $term = $observation->term;
        $observation->delete();

        if ($session) {
            $calculator->recalculateForSession($session, $term);
        }

        return redirect()->route('observations.index')->with('success', 'Observation removed.');
    }

    protected function persist(Request $request, ObservationCalculationService $calculator, Observation $observation): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canConductObservation(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $assignmentRows = $this->assignments($session->id);
        $assignmentKeys = $assignmentRows->map(fn ($row) => $this->assignmentKey($row))->all();
        $complete = $request->input('intent') === 'complete';

        $scoreRules = [];
        foreach (array_keys(ObservationRubric::standards()) as $key) {
            $scoreRules["scores.$key"] = [$complete ? 'required' : 'nullable', 'integer', 'min:1', 'max:4'];
        }

        $validated = $request->validate([
            'assignment' => ['required', 'string', Rule::in($assignmentKeys)],
            'observation_date' => ['required', 'date'],
            'strengths' => ['nullable', 'string', 'max:4000'],
            'areas_for_improvement' => ['nullable', 'string', 'max:4000'],
            'action_plan' => ['nullable', 'string', 'max:4000'],
            'scores' => ['nullable', 'array'],
            ...$scoreRules,
        ]);

        [$teacherId, $classId, $subjectId] = array_map('intval', explode(':', $validated['assignment']));
        abort_unless($teacherId !== (int) $user->id, 403, 'You cannot observe your own lesson.');

        $scores = $validated['scores'] ?? [];
        $overall = ObservationRubric::overall($scores);
        $term = Term::currentForSession($session->id);

        if ($complete && $overall === null) {
            return back()->withErrors([
                'scores' => 'Score all 12 standards to complete this observation.',
            ])->withInput();
        }

        $status = 'scheduled';
        if ($complete && $overall !== null) {
            $status = ObservationRubric::isEffective($overall) ? 'completed' : 'follow_up_required';
        }

        $observation->fill([
            'teacher_id' => $teacherId,
            'school_class_id' => $classId,
            'subject_id' => $subjectId,
            'observation_date' => $validated['observation_date'],
            'strengths' => $validated['strengths'] ?? null,
            'areas_for_improvement' => $validated['areas_for_improvement'] ?? null,
            'action_plan' => $validated['action_plan'] ?? null,
            'rubric_scores' => $complete ? $scores : ($overall ? $scores : null),
            'overall_score' => $complete ? $overall : null,
            'observer_id' => $observation->exists ? $observation->observer_id : $user->id,
            'academic_session_id' => $session->id,
            'term_id' => $term?->id,
            'status' => $status,
        ])->save();

        $calculator->recalculateForSession($session, $term);

        $message = $complete
            ? 'Observation completed. AE-06 updated.'
            : 'Observation scheduled.';

        return redirect()->route('observations.show', $observation)->with('success', $message);
    }

    protected function assertCanView(Observation $observation): void
    {
        $user = auth()->user();
        abort_unless($user->canViewObservations(), 403);
        $observation->loadMissing('schoolClass');
        abort_unless((int) $observation->schoolClass?->school_id === (int) $user->school_id, 403);

        if ($user->isTeacher() && ! $user->canConductObservation()) {
            abort_unless((int) $observation->teacher_id === (int) $user->id, 403);
        }
    }

    protected function assertCanWrite(Observation $observation): void
    {
        $user = auth()->user();
        abort_unless($user->canConductObservation(), 403);
        $observation->loadMissing('schoolClass');
        abort_unless((int) $observation->schoolClass?->school_id === (int) $user->school_id, 403);
    }

    protected function assignments(int $sessionId)
    {
        return TeacherAssignment::where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->where('teacher_id', '!=', auth()->id())
            ->with(['teacher', 'schoolClass', 'subject'])
            ->get()
            ->sortBy(fn ($row) => $row->teacher?->name.' '.$row->schoolClass?->name);
    }

    protected function assignmentKey(TeacherAssignment $row): string
    {
        return $row->teacher_id.':'.$row->school_class_id.':'.$row->subject_id;
    }
}
