<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use App\Services\AcademicPeriodService;
use App\Services\CurriculumCoverageKpiService;
use App\Services\CurriculumDashboardService;
use App\Services\HodScope;
use App\Services\LessonPlanCalculationService;
use App\Services\LessonPlanReview;
use App\Services\PlanningPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LessonPlanController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() || $user->isHoD() || $user->isLeadership() || $user->isAdmin(), 403);

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

        $scope = app(HodScope::class);

        $query = LessonPlan::whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->whereHas('topic.schemeOfWork', function ($q) use ($session, $termId) {
                $q->where('academic_session_id', $session->id);
                if ($termId) {
                    $q->where('term_id', $termId);
                }
            })
            ->when(! $isStaff,       fn ($q) => $q->where('teacher_id', $user->id))
            ->when($user->isHoD() && ! $user->isHoS() && ! $user->isAdmin(), fn ($q) => $scope->constrainBySubject($q, $user))
            ->when($filterClassId,   fn ($q) => $q->where('school_class_id', $filterClassId))
            ->when($filterSubjectId, fn ($q) => $q->where('subject_id', $filterSubjectId))
            ->when($filterTeacherId, fn ($q) => $q->where('teacher_id', $filterTeacherId))
            ->when($filterStatus !== '', fn ($q) => $q->where('status', $filterStatus))
            ->when($filterTiming === 'on_time', fn ($q) => $q->where('on_time', true))
            ->when($filterTiming === 'late',    fn ($q) => $q->where('on_time', false)->whereNotNull('submitted_at'))
            ->when($search !== '', fn ($q) => $q->whereHas('topic', fn ($tq) => $tq->where('title', 'like', '%'.$search.'%')));

        $planSummary = [
            'submitted' => (clone $query)->where('status', 'submitted')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'on_time' => (clone $query)->where('on_time', true)->whereNotNull('submitted_at')->count(),
            'late' => (clone $query)->where('on_time', false)->whereNotNull('submitted_at')->count(),
        ];

        $query = $query->with(['topic.schemeOfWork', 'teacher', 'schoolClass', 'subject']);

        $query = match ($sortBy) {
            'date_asc'  => $query->orderBy('submitted_at'),
            'topic_asc' => $query->orderBy('school_class_id')->orderBy('subject_id'),
            default     => $query->latest('submitted_at'),
        };

        $plans = $query->paginate(25)->withQueryString();

        $allClasses = SchoolClass::where('school_id', $user->school_id)->orderBy('name')->get();
        $allSubjects = Subject::where('school_id', $user->school_id)
            ->when($user->isHoD() && ! $user->isHoS() && ! $user->isAdmin(), fn ($q) => $scope->constrainBySubject($q, $user, 'id'))
            ->orderBy('name')
            ->get();
        $allTeachers = $isStaff
            ? User::where('school_id', $user->school_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'teacher'))
                ->orderBy('name')
                ->get()
            : collect();

        $filters = compact(
            'sessionId', 'termId', 'filterClassId', 'filterSubjectId',
            'filterTeacherId', 'filterStatus', 'filterTiming', 'search', 'sortBy'
        );

        return view('lesson-plans.index', [
            'plans' => $plans,
            'planSummary' => $planSummary,
            'session' => $session,
            'isStaff' => $isStaff,
            'allSessions' => $allSessions,
            'allTerms' => $allTerms,
            'allClasses' => $allClasses,
            'allSubjects' => $allSubjects,
            'allTeachers' => $allTeachers,
            'filters' => $filters,
            'dueWeekdayName' => app(PlanningPolicy::class)->lessonPlanDueWeekdayName($session->school),
        ]);
    }

    public function create(Request $request, CurriculumDashboardService $curriculum): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $schemes = $curriculum->schemesForTeacherPlanning($user, $session->id);
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
            'dueWeekdayName' => app(PlanningPolicy::class)->lessonPlanDueWeekdayName($session->school),
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
        $schemes = $curriculum->schemesForTeacherPlanning($user, $session->id);

        return view('lesson-plans.form', [
            'schemes' => $schemes,
            'selectedTopic' => $lessonPlan->topic,
            'plan' => $lessonPlan,
            'session' => $session,
            'dueWeekdayName' => app(PlanningPolicy::class)->lessonPlanDueWeekdayName($session?->school),
        ]);
    }

    public function update(Request $request, LessonPlan $lessonPlan, CurriculumDashboardService $curriculum, LessonPlanCalculationService $calculator): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() && $lessonPlan->teacher_id === $user->id, 403);
        abort_unless($lessonPlan->isEditable(), 403);

        return $this->persist($request, $curriculum, $calculator, $lessonPlan);
    }

    public function approve(Request $request, LessonPlan $lessonPlan, LessonPlanCalculationService $calculator, HodScope $scope, LessonPlanReview $review): RedirectResponse
    {
        abort_unless(auth()->user()->isHoD() || auth()->user()->isHoS(), 403);
        $scope->assertCanReviewLessonPlan(auth()->user(), $lessonPlan);
        abort_unless($lessonPlan->status === 'submitted', 422, 'Only submitted plans can be approved.');

        $snapshot = $review->snapshot(auth()->user(), $request->validate($review->rules())['checklist']);
        if (! $review->allPassed($snapshot)) {
            throw ValidationException::withMessages([
                'checklist' => 'Approve only when every quality item passes. Return the plan if any item fails.',
            ]);
        }

        $lessonPlan->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
            'review_checklist' => $snapshot,
        ]);

        $this->recalculate($lessonPlan, $calculator);

        return back()->with('success', 'Lesson plan approved against the quality checklist. This is Planned only — the teacher must still record delivery for HoD verification.');
    }

    public function reject(Request $request, LessonPlan $lessonPlan, LessonPlanCalculationService $calculator, HodScope $scope, LessonPlanReview $review): RedirectResponse
    {
        abort_unless(auth()->user()->isHoD() || auth()->user()->isHoS(), 403);
        $scope->assertCanReviewLessonPlan(auth()->user(), $lessonPlan);
        abort_unless($lessonPlan->status === 'submitted', 422, 'Only submitted plans can be rejected.');

        $validated = $request->validate(array_merge($review->rules(), [
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]));
        $snapshot = $review->snapshot(auth()->user(), $validated['checklist']);

        $lessonPlan->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
            'review_checklist' => $snapshot,
        ]);

        $this->recalculate($lessonPlan, $calculator);

        return back()->with('success', 'Lesson plan returned for revision with the quality checklist.');
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
            'objectives' => ['required'],
            'activities' => ['required', 'string', 'max:5000'],
            'assessment' => ['required', 'string', 'max:5000'],
            'resources' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $topic = Topic::with(['schemeOfWork.term', 'schemeOfWork.academicSession', 'lessonPlans', 'latestLessonPlan'])->findOrFail($validated['topic_id']);
        abort_unless($curriculum->teacherOwnsTopic($user, $topic, $session->id), 403);
        abort_unless($plan->exists || $topic->schemeOfWork?->isActive(), 422, 'Lesson plans must use topics from the Active scheme of work.');

        app(AcademicPeriodService::class)->assertAcceptsNewActivity($topic->schemeOfWork?->academicSession, $topic->schemeOfWork?->term);

        $objectives = $this->approvedObjectivesFromInput($topic, $validated['objectives']);

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
            'objectives' => $objectives,
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
            'review_checklist' => null,
        ]);

        if ($request->hasFile('document')) {
            $plan->file_path = $request->file('document')->store('lesson-plans', 'public');
        }

        $plan->save();
        $this->recalculate($plan, $calculator);

        $timing = $plan->on_time
            ? 'on time'
            : 'after the planning-policy deadline (late)';

        return redirect()->route('dashboard')->with('success', "Lesson plan submitted {$timing}. This is a plan only — it does not count as delivery or verified coverage.");
    }

    protected function recalculate(LessonPlan $plan, LessonPlanCalculationService $calculator): void
    {
        $plan->loadMissing('topic.schemeOfWork.academicSession', 'topic.schemeOfWork.term');
        $scheme = $plan->topic?->schemeOfWork;
        if (! $scheme?->academicSession) {
            return;
        }

        $calculator->recalculateForSession($scheme->academicSession, $scheme->term);
        app(CurriculumCoverageKpiService::class)->recalculateForTerm($scheme->academicSession, $scheme->term);
    }

    protected function approvedObjectivesFromInput(Topic $topic, mixed $input): string
    {
        $allowed = $topic->approvedLearningObjectives();
        $selected = is_array($input)
            ? array_values(array_filter(array_map(fn ($line) => trim((string) $line), $input)))
            : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $input) ?: [])));

        if ($selected === [] || array_diff($selected, $allowed) !== []) {
            throw ValidationException::withMessages([
                'objectives' => 'Learning objectives must be selected from the Active Scheme of Work topic. Teachers cannot introduce unapproved curriculum objectives.',
            ]);
        }

        return implode("\n", $selected);
    }
}
