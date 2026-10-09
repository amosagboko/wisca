<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\HomeworkLog;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Services\CaptureLogReview;
use App\Services\HomeworkCalculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomeworkLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless(
            $user->isTeacher() || $user->isHoD() || $user->isLeadership() || $user->isAdmin(),
            403
        );

        $isStaff = $user->isHoD() || $user->isHoS() || $user->isAdmin();

        // All sessions for this school
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')->get();

        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)
            ->orderBy('start_date')->get();

        $termId          = $request->integer('term_id');
        $filterClassId   = $request->integer('class_id');
        $filterSubjectId = $request->integer('subject_id');
        $filterTeacherId = $request->integer('teacher_id');
        $search          = trim((string) $request->query('search', ''));
        $sortBy          = $request->query('sort', 'date_desc');

        $query = HomeworkLog::where('academic_session_id', $session->id)
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when(! $isStaff, fn ($q) => $q->where('teacher_id', $user->id))
            ->when($termId,          fn ($q) => $q->where('term_id', $termId))
            ->when($filterClassId,   fn ($q) => $q->where('school_class_id', $filterClassId))
            ->when($filterSubjectId, fn ($q) => $q->where('subject_id', $filterSubjectId))
            ->when($filterTeacherId, fn ($q) => $q->where('teacher_id', $filterTeacherId))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->with(['teacher', 'schoolClass', 'subject']);

        $query = match ($sortBy) {
            'date_asc'   => $query->orderBy('given_date'),
            'rate_desc'  => $query->orderByRaw('completed_on_time_count / NULLIF(given_count,0) DESC')->orderByDesc('given_date'),
            'rate_asc'   => $query->orderByRaw('completed_on_time_count / NULLIF(given_count,0) ASC')->orderByDesc('given_date'),
            default      => $query->orderByDesc('given_date'),
        };

        $logs = $query->get();

        // Dropdown options from all logs in this session (unfiltered)
        $allLogs = HomeworkLog::where('academic_session_id', $session->id)
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when(! $isStaff, fn ($q) => $q->where('teacher_id', $user->id))
            ->with(['teacher', 'schoolClass', 'subject'])->get();

        $allTeachers = $isStaff
            ? $allLogs->pluck('teacher')->filter()->unique('id')->sortBy('name')->values()
            : collect();
        $allClasses  = $allLogs->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();
        $allSubjects = $allLogs->pluck('subject')->filter()->unique('id')->sortBy('name')->values();

        $filters = compact(
            'sessionId', 'termId', 'filterClassId', 'filterSubjectId',
            'filterTeacherId', 'search', 'sortBy'
        );

        return view('homework.index', compact(
            'logs', 'session', 'isStaff',
            'allSessions', 'allTerms', 'allClasses', 'allSubjects', 'allTeachers',
            'filters'
        ));
    }

    public function create(HomeworkCalculationService $calculator): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $term = Term::currentForSession($session->id);
        $given = $term ? $calculator->suggestedGivenDate($session, $term) : now()->toDateString();

        return view('homework.form', [
            'log' => new HomeworkLog([
                'given_date' => $given,
                'due_date' => Carbon::parse($given)->addDay()->toDateString(),
            ]),
            'assignments' => $this->assignments($user, $session->id),
            'session' => $session,
        ]);
    }

    public function store(Request $request, HomeworkCalculationService $calculator): RedirectResponse
    {
        return $this->persist($request, $calculator, new HomeworkLog);
    }

    public function edit(HomeworkLog $homeworkLog): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() && $homeworkLog->teacher_id === $user->id, 403);
        abort_unless($homeworkLog->isEditableByTeacher(), 422, 'Verified homework cannot be edited. Ask the HOD to return it first.');

        $session = AcademicSession::currentForSchool($user->school_id);

        return view('homework.form', [
            'log' => $homeworkLog,
            'assignments' => $this->assignments($user, $session->id),
            'session' => $session,
        ]);
    }

    public function update(Request $request, HomeworkLog $homeworkLog, HomeworkCalculationService $calculator): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() && $homeworkLog->teacher_id === $user->id, 403);

        return $this->persist($request, $calculator, $homeworkLog);
    }

    public function destroy(HomeworkLog $homeworkLog, HomeworkCalculationService $calculator): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() && $homeworkLog->teacher_id === $user->id, 403);
        abort_unless($homeworkLog->isEditableByTeacher(), 422, 'Verified homework cannot be removed.');

        $session = $homeworkLog->academicSession;
        $term = $homeworkLog->term;
        $homeworkLog->delete();

        if ($session) {
            $calculator->recalculateForSession($session, $term);
        }

        return redirect()->route('homework.index')->with('success', 'Homework log removed.');
    }

    protected function persist(Request $request, HomeworkCalculationService $calculator, HomeworkLog $log): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isTeacher(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $assignmentRows = $this->assignments($user, $session->id);
        $assignmentKeys = $assignmentRows->map(fn ($row) => $row->school_class_id.':'.$row->subject_id)->all();

        $validated = $request->validate([
            'assignment' => ['required', 'string', Rule::in($assignmentKeys)],
            'title' => ['required', 'string', 'max:255'],
            'given_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:given_date'],
            'given_count' => ['required', 'integer', 'min:1'],
            'completed_on_time_count' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ((int) $validated['completed_on_time_count'] > (int) $validated['given_count']) {
            return back()->withErrors([
                'completed_on_time_count' => 'On-time completions cannot exceed assignments given.',
            ])->withInput();
        }

        [$classId, $subjectId] = array_map('intval', explode(':', $validated['assignment']));
        $term = Term::currentForSession($session->id);

        if ($log->exists && $log->isVerified()) {
            return back()->withErrors([
                'title' => 'Verified homework cannot be edited. Ask the HOD to return it first.',
            ])->withInput();
        }

        $log->fill([
            'school_class_id' => $classId,
            'subject_id' => $subjectId,
            'title' => $validated['title'],
            'given_date' => $validated['given_date'],
            'due_date' => $validated['due_date'],
            'given_count' => $validated['given_count'],
            'completed_on_time_count' => $validated['completed_on_time_count'],
            'notes' => $validated['notes'] ?? null,
            'teacher_id' => $user->id,
            'academic_session_id' => $session->id,
            'term_id' => $term?->id,
        ]);
        app(CaptureLogReview::class)->markSubmitted($log);
        $log->save();

        $calculator->recalculateForSession($session, $term);

        return redirect()->route('homework.index')->with('success', 'Homework log saved. AE-03 uses given vs on-time counts. HOD still verifies the log.');
    }

    public function verify(HomeworkLog $homeworkLog, CaptureLogReview $review): RedirectResponse
    {
        $review->verify(auth()->user(), $homeworkLog);

        return back()->with('success', 'HOD verified homework evidence. Executive AE-03 still uses given vs on-time counts.');
    }

    public function reject(Request $request, HomeworkLog $homeworkLog, CaptureLogReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);
        $review->reject(auth()->user(), $homeworkLog, $validated['rejection_reason']);

        return back()->with('success', 'Homework returned for revision. The teacher can update the log.');
    }

    protected function assignments($user, int $sessionId)
    {
        return TeacherAssignment::where('teacher_id', $user->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->with(['schoolClass', 'subject'])
            ->get();
    }
}
