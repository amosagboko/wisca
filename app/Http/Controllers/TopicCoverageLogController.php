<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Topic;
use App\Models\TopicCoverageLog;
use App\Services\AcademicPeriodService;
use App\Services\CoverageCalculationService;
use App\Services\CurriculumCoverageKpiService;
use App\Services\CurriculumDashboardService;
use App\Services\HodScope;
use App\Services\TopicCatchUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TopicCoverageLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->isTeacher() || $user->isHoD() || $user->isLeadership() || $user->isAdmin() || $user->isSubjectLead(), 403);

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

        $termId         = $request->integer('term_id');
        $filterClassId  = $request->integer('class_id');
        $filterSubjectId= $request->integer('subject_id');
        $filterStatus   = $request->query('status', '');
        $search         = trim((string) $request->query('search', ''));

        $query = TopicCoverageLog::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when($termId, function ($q) use ($termId, $session) {
                // coverage_date falls within the term's date range
                $term = Term::find($termId);
                if ($term) {
                    $q->whereBetween('coverage_date', [$term->start_date, $term->end_date]);
                }
            })
            ->when($filterClassId,   fn ($q) => $q->where('school_class_id', $filterClassId))
            ->when($filterSubjectId, fn ($q) => $q->where('subject_id', $filterSubjectId))
            ->when($filterStatus !== '', fn ($q) => $q->where('status', $filterStatus))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('teacher', fn ($tq) => $tq->where('name', 'like', '%'.$search.'%'))
                  ->orWhereHas('topic', fn ($tq) => $tq->where('title', 'like', '%'.$search.'%'));
            }))
            ->with(['teacher', 'schoolClass', 'subject', 'topic', 'verifier'])
            ->orderByDesc('submitted_at');

        // Teachers only see their own logs
        if ($user->isTeacher() && ! $user->isHoD() && ! $user->isHoS()) {
            $query->where('teacher_id', $user->id);
        }

        $logs = $query->get();

        // Build filter option lists from all logs in this session (unfiltered)
        $allLogs = TopicCoverageLog::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when($user->isTeacher() && ! $user->isHoD() && ! $user->isHoS(),
                fn ($q) => $q->where('teacher_id', $user->id))
            ->with(['schoolClass', 'subject'])->get();

        $allClasses  = $allLogs->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();
        $allSubjects = $allLogs->pluck('subject')->filter()->unique('id')->sortBy('name')->values();

        $filters = compact(
            'sessionId', 'termId', 'filterClassId', 'filterSubjectId',
            'filterStatus', 'search'
        );

        return view('coverage-logs.index', compact(
            'logs', 'session', 'allSessions', 'allTerms',
            'allClasses', 'allSubjects', 'filters'
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
        if ($request->filled('topic')) {
            $selectedTopic = Topic::with(['schemeOfWork', 'lessonPlans', 'latestLessonPlan'])->find($request->integer('topic'));
            if ($selectedTopic && ! $curriculum->teacherOwnsTopic($user, $selectedTopic, $session->id)) {
                abort(403);
            }
        }

        return view('coverage-logs.create', [
            'schemes' => $schemes,
            'selectedTopic' => $selectedTopic,
            'needsPlan' => $selectedTopic && ! $selectedTopic->hasApprovedLessonPlan(),
        ]);
    }

    public function store(Request $request, CurriculumDashboardService $curriculum): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isTeacher(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $validated = $request->validate([
            'topic_id' => ['required', 'exists:topics,id'],
            'workbook_reference' => ['required', 'string', 'max:255'],
            'coverage_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $topic = Topic::with(['schemeOfWork.academicSession', 'schemeOfWork.term', 'latestCoverageLog', 'lessonPlans'])->findOrFail($validated['topic_id']);

        if (! $curriculum->teacherOwnsTopic($user, $topic, $session->id)) {
            abort(403);
        }

        app(AcademicPeriodService::class)->assertAcceptsNewActivity($topic->schemeOfWork?->academicSession, $topic->schemeOfWork?->term);

        if (! $topic->isLoggable()) {
            return back()->withErrors(['topic_id' => 'This topic already has coverage submitted or verified.'])->withInput();
        }

        $plan = $topic->approvedLessonPlan();
        if (! $plan) {
            return back()->withErrors([
                'topic_id' => 'An approved lesson plan is required before this topic can be logged as covered (AE-05).',
            ])->withInput();
        }

        $scheme = $topic->schemeOfWork;

        TopicCoverageLog::create([
            'topic_id' => $topic->id,
            'teacher_id' => $user->id,
            'school_class_id' => $scheme->school_class_id,
            'subject_id' => $scheme->subject_id,
            'lesson_plan_id' => $plan->id,
            'coverage_date' => $validated['coverage_date'],
            'workbook_reference' => $validated['workbook_reference'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $topic->update(['status' => 'in_progress']);

        return redirect()->route('coverage-logs.index')->with('success', 'Delivery recorded. This is not verified coverage until the HOD verifies the log.');
    }

    public function verify(Request $request, TopicCoverageLog $coverageLog, CoverageCalculationService $coverageService): RedirectResponse
    {
        abort_unless(auth()->user()->isHoD(), 403, 'Only a Head of Department can verify curriculum coverage.');
        app(HodScope::class)->assertCanReviewCoverage(auth()->user(), $coverageLog);
        abort_unless($coverageLog->status === 'submitted', 422, 'Only submitted logs can be verified.');

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $coverageLog->loadMissing(['topic.schemeOfWork', 'topic.lessonPlans']);
        abort_unless($coverageLog->topic?->hasApprovedLessonPlan(), 422, 'This topic does not have an approved lesson plan.');

        $coverageLog->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $this->appendComment($coverageLog->notes, $validated['comment'] ?? null),
        ]);

        $coverageLog->topic->update(['status' => 'covered']);
        app(TopicCatchUpService::class)->markAddressedFromVerifiedLog($coverageLog);
        $coverageService->recalculateForScheme($coverageLog->topic->schemeOfWork);
        $scheme = $coverageLog->topic->schemeOfWork?->loadMissing(['academicSession', 'term']);
        if ($scheme?->academicSession && $scheme->term) {
            app(CurriculumCoverageKpiService::class)->recalculateForTerm($scheme->academicSession, $scheme->term);
        }

        return back()->with('success', 'HOD verified delivery. This topic now counts as verified coverage. Executive AE-01 still uses the existing formula.');
    }

    public function reject(Request $request, TopicCoverageLog $coverageLog): RedirectResponse
    {
        abort_unless(auth()->user()->isHoD(), 403, 'Only a Head of Department can reject a delivery log.');
        app(HodScope::class)->assertCanReviewCoverage(auth()->user(), $coverageLog);
        abort_unless($coverageLog->status === 'submitted', 422, 'Only submitted logs can be rejected.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $coverageLog->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $coverageLog->topic->update(['status' => 'planned']);

        return back()->with('success', 'Coverage log rejected. Teacher can resubmit with a workbook reference.');
    }

    protected function appendComment(?string $notes, ?string $comment): ?string
    {
        if (! filled($comment)) {
            return $notes;
        }

        $line = 'HoD: '.$comment;

        return filled($notes) ? $notes."\n".$line : $line;
    }
}
