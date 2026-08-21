<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\LessonPlan;
use App\Models\Term;
use App\Models\Topic;
use App\Services\CurriculumDashboardService;
use App\Services\LessonPlanCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LessonPlanController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() || $user->isHoD() || $user->isHoS() || $user->isAdmin(), 403);

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
        $filterStatus    = $request->query('status', '');   // draft|submitted|approved|rejected|''
        $filterTiming    = $request->query('timing', '');   // on_time|late|''
        $search          = trim((string) $request->query('search', ''));
        $sortBy          = $request->query('sort', 'date_desc');

        $query = LessonPlan::whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when(! $isStaff,       fn ($q) => $q->where('teacher_id', $user->id))
            ->when($filterClassId,   fn ($q) => $q->where('school_class_id', $filterClassId))
            ->when($filterSubjectId, fn ($q) => $q->where('subject_id', $filterSubjectId))
            ->when($filterTeacherId, fn ($q) => $q->where('teacher_id', $filterTeacherId))
            ->when($filterStatus !== '', fn ($q) => $q->where('status', $filterStatus))
            ->when($filterTiming === 'on_time', fn ($q) => $q->where('on_time', true))
            ->when($filterTiming === 'late',    fn ($q) => $q->where('on_time', false)->whereNotNull('submitted_at'))
            ->when($termId, fn ($q) => $q->whereHas('topic.schemeOfWork', fn ($sq) => $sq->where('term_id', $termId)))
            ->when($search !== '', fn ($q) => $q->whereHas('topic', fn ($tq) => $tq->where('title', 'like', '%'.$search.'%')))
            ->with(['topic.schemeOfWork', 'teacher', 'schoolClass', 'subject']);

        $query = match ($sortBy) {
            'date_asc'  => $query->orderBy('submitted_at'),
            'topic_asc' => $query->orderBy('school_class_id')->orderBy('subject_id'),
            default     => $query->latest('submitted_at'),
        };

        $plans = $query->get();

        // Dropdown options (unfiltered)
        $allPlans = LessonPlan::whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when(! $isStaff, fn ($q) => $q->where('teacher_id', $user->id))
            ->with(['teacher', 'schoolClass', 'subject'])->get();

        $allTeachers = $isStaff
            ? $allPlans->pluck('teacher')->filter()->unique('id')->sortBy('name')->values()
            : collect();
        $allClasses  = $allPlans->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();
        $allSubjects = $allPlans->pluck('subject')->filter()->unique('id')->sortBy('name')->values();

        $filters = compact(
            'sessionId', 'termId', 'filterClassId', 'filterSubjectId',
            'filterTeacherId', 'filterStatus', 'filterTiming', 'search', 'sortBy'
        );

        return view('lesson-plans.index', compact(
            'plans', 'session', 'isStaff',
            'allSessions', 'allTerms', 'allClasses', 'allSubjects', 'allTeachers',
            'filters'
        ));
    }

    public function create(Request $request, CurriculumDashboardService $curriculum): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $schemes = $curriculum->schemesForTeacher($user, $session->id);
        $selectedTopic = null;
        $plan = new LessonPlan(['status' => 'draft']);

        if ($request->filled('topic')) {
            $selectedTopic = Topic::with(['schemeOfWork.term', 'latestLessonPlan'])->find($request->integer('topic'));
            abort_unless($selectedTopic && $curriculum->teacherOwnsTopic($user, $selectedTopic, $session->id), 403);

            if ($selectedTopic->latestLessonPlan?->isEditable()) {
                $plan = $selectedTopic->latestLessonPlan;
            }
        }

        return view('lesson-plans.form', [
            'schemes' => $schemes,
            'selectedTopic' => $selectedTopic,
            'plan' => $plan,
            'session' => $session,
        ]);
    }

    public function store(Request $request, CurriculumDashboardService $curriculum, LessonPlanCalculationService $calculator): RedirectResponse
    {
        return $this->persist($request, $curriculum, $calculator, new LessonPlan);
    }

    public function edit(LessonPlan $lessonPlan, CurriculumDashboardService $curriculum): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() && $lessonPlan->teacher_id === $user->id, 403);
        abort_unless($lessonPlan->isEditable(), 403, 'Only draft or rejected plans can be edited.');

        $session = AcademicSession::currentForSchool($user->school_id);
        $schemes = $curriculum->schemesForTeacher($user, $session->id);

        return view('lesson-plans.form', [
            'schemes' => $schemes,
            'selectedTopic' => $lessonPlan->topic,
            'plan' => $lessonPlan,
            'session' => $session,
        ]);
    }

    public function update(Request $request, LessonPlan $lessonPlan, CurriculumDashboardService $curriculum, LessonPlanCalculationService $calculator): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() && $lessonPlan->teacher_id === $user->id, 403);
        abort_unless($lessonPlan->isEditable(), 403);

        return $this->persist($request, $curriculum, $calculator, $lessonPlan);
    }

    public function approve(Request $request, LessonPlan $lessonPlan, LessonPlanCalculationService $calculator): RedirectResponse
    {
        abort_unless(auth()->user()->isHoD() || auth()->user()->isHoS(), 403);
        abort_unless($lessonPlan->status === 'submitted', 422, 'Only submitted plans can be approved.');

        $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $lessonPlan->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->recalculate($lessonPlan, $calculator);

        return back()->with('success', 'Lesson plan approved. The teacher can now log coverage for this topic.');
    }

    public function reject(Request $request, LessonPlan $lessonPlan, LessonPlanCalculationService $calculator): RedirectResponse
    {
        abort_unless(auth()->user()->isHoD() || auth()->user()->isHoS(), 403);
        abort_unless($lessonPlan->status === 'submitted', 422, 'Only submitted plans can be rejected.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $lessonPlan->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $this->recalculate($lessonPlan, $calculator);

        return back()->with('success', 'Lesson plan returned for revision.');
    }

    protected function persist(
        Request $request,
        CurriculumDashboardService $curriculum,
        LessonPlanCalculationService $calculator,
        LessonPlan $plan,
    ): RedirectResponse {
        $user = auth()->user();
        abort_unless($user->isTeacher(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $validated = $request->validate([
            'topic_id' => ['required', 'exists:topics,id'],
            'objectives' => ['required', 'string', 'max:5000'],
            'activities' => ['required', 'string', 'max:5000'],
            'assessment' => ['required', 'string', 'max:5000'],
            'resources' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $topic = Topic::with(['schemeOfWork.term', 'lessonPlans', 'latestLessonPlan'])->findOrFail($validated['topic_id']);
        abort_unless($curriculum->teacherOwnsTopic($user, $topic, $session->id), 403);

        if ($topic->hasApprovedLessonPlan() && $plan->status !== 'approved') {
            return back()->withErrors(['topic_id' => 'This topic already has an approved lesson plan.'])->withInput();
        }

        if ($topic->latestLessonPlan?->status === 'submitted' && $plan->id !== $topic->latestLessonPlan->id) {
            return back()->withErrors(['topic_id' => 'A lesson plan for this topic is already awaiting HoD approval.'])->withInput();
        }

        if ($plan->exists && $plan->isEditable() && $request->hasFile('document') && $plan->file_path) {
            Storage::disk('public')->delete($plan->file_path);
        }

        $dueAt = $calculator->dueAtForTopic($topic);
        $submittedAt = now();
        $scheme = $topic->schemeOfWork;

        $plan->fill([
            'topic_id' => $topic->id,
            'teacher_id' => $user->id,
            'school_class_id' => $scheme->school_class_id,
            'subject_id' => $scheme->subject_id,
            'objectives' => $validated['objectives'],
            'activities' => $validated['activities'],
            'assessment' => $validated['assessment'],
            'resources' => $validated['resources'] ?? null,
            'status' => 'submitted',
            'submitted_at' => $submittedAt,
            'due_at' => $dueAt,
            'on_time' => $submittedAt->lt($dueAt),
            'rejection_reason' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        if ($request->hasFile('document')) {
            $plan->file_path = $request->file('document')->store('lesson-plans', 'public');
        }

        $plan->save();
        $this->recalculate($plan, $calculator);

        $timing = $plan->on_time ? 'on time' : 'after the Monday deadline (late for AE-05)';

        return redirect()->route('dashboard')->with('success', "Lesson plan submitted {$timing} for HoD approval.");
    }

    protected function recalculate(LessonPlan $plan, LessonPlanCalculationService $calculator): void
    {
        $plan->loadMissing('topic.schemeOfWork.academicSession', 'topic.schemeOfWork.term');
        $scheme = $plan->topic?->schemeOfWork;
        if (! $scheme?->academicSession) {
            return;
        }

        $calculator->recalculateForSession($scheme->academicSession, $scheme->term);
    }
}
