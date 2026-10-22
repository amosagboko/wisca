<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchemeOfWork;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Services\HodScope;
use App\Services\SchemeOfWorkBulkUpload;
use App\Services\SchemeOfWorkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchemeOfWorkController extends Controller
{
    public function __construct(
        protected SchemeOfWorkService $schemes,
        protected SchemeOfWorkBulkUpload $bulk,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($this->canAccessIndex($user), 403);

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
        $filterClassId = $request->integer('class_id');
        $filterSubjectId = $request->integer('subject_id');
        $filterStatus = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $queue = (string) $request->query('queue', '');
        $sort = (string) $request->query('sort', 'updated_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, ['updated_at', 'session', 'class', 'subject', 'status'], true)) {
            $sort = 'updated_at';
        }

        $query = SchemeOfWork::query()
            ->where('academic_session_id', $session->id)
            ->with(['subject', 'schoolClass', 'term.academicSession.school', 'academicSession.school'])
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->when($filterClassId, fn ($q) => $q->where('school_class_id', $filterClassId))
            ->when($filterSubjectId, fn ($q) => $q->where('subject_id', $filterSubjectId));

        if ($this->schemes->canOversee($user)) {
            // School-wide read for HoS / Board / Admin / AH.
        } elseif ($user->isHoD()) {
            app(HodScope::class)->constrainByClass($query, $user);
        } elseif ($user->isTeacher()) {
            $assignments = TeacherAssignment::where('teacher_id', $user->id)
                ->where('academic_session_id', $session->id)
                ->where('status', 'active')
                ->get();

            $query->where(function ($inner) use ($user, $assignments) {
                $inner->where('uploaded_by', $user->id);

                foreach ($assignments as $assignment) {
                    $inner->orWhere(function ($pair) use ($assignment) {
                        $pair->where('school_class_id', $assignment->school_class_id)
                            ->where('subject_id', $assignment->subject_id);
                    });
                }
            });
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->whereHas('schoolClass', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('subject', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('academicSession', fn ($q) => $q->where('name', 'like', $like));
            });
        }

        if ($queue === 'review' && $user->isHoD()) {
            $query->where('status', 'draft')->whereNotNull('submitted_at');
        }

        if ($filterStatus === 'submitted') {
            $query->where('status', 'draft')->whereNotNull('submitted_at');
        } elseif ($filterStatus === 'returned') {
            $query->where('status', 'draft')->whereNull('submitted_at')->whereNotNull('rejection_reason');
        } elseif ($filterStatus === 'draft') {
            $query->where('status', 'draft')->whereNull('submitted_at')->whereNull('rejection_reason');
        } elseif ($filterStatus !== '') {
            $query->where('status', $filterStatus);
        }

        $activeCount = (clone $query)->where('status', 'active')->count();
        $inFlight = (clone $query)->whereIn('status', ['draft', 'approved'])->count();

        $this->applySchemeSort($query, $sort, $direction);

        $schemes = $query->paginate(15)->withQueryString();

        $allClasses = SchoolClass::where('school_id', $user->school_id)->orderBy('display_order')->orderBy('name')->get();
        $allSubjects = Subject::where('school_id', $user->school_id)->orderBy('name')->get();

        return view('schemes.index', [
            'schemes' => $schemes,
            'session' => $session,
            'allSessions' => $allSessions,
            'allTerms' => $allTerms,
            'allClasses' => $allClasses,
            'allSubjects' => $allSubjects,
            'canPrepare' => $this->schemes->canPrepare($user),
            'canOversee' => $this->schemes->canOversee($user),
            'canReviewQueue' => $user->isHoD(),
            'activeCount' => $activeCount,
            'inFlight' => $inFlight,
            'filters' => [
                'sessionId' => $session->id,
                'termId' => $termId,
                'filterClassId' => $filterClassId,
                'filterSubjectId' => $filterSubjectId,
                'filterStatus' => $filterStatus,
                'search' => $search,
                'queue' => $queue,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $user = auth()->user();
        abort_unless($this->schemes->canPrepare($user), 403);

        return view('schemes.form', $this->formData($user, new SchemeOfWork(['status' => 'draft']), $request));
    }

    public function template(): StreamedResponse
    {
        abort_unless($this->schemes->canPrepare(auth()->user()), 403);

        return response()->streamDownload(
            function () {
                echo $this->bulk->templateCsv();
            },
            SchemeOfWorkBulkUpload::FILENAME,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->schemes->canPrepare($user), 403);

        $validated = $this->validatedDraft($request);
        $scheme = $this->schemes->createDraft($user, $validated, $validated['topics'], $request->file('document'));

        if ($request->input('_action') === 'submit') {
            $this->schemes->submit($user, $scheme);

            return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work submitted for section HOD review.');
        }

        return redirect()->route('schemes.edit', $scheme)->with('success', 'Draft scheme of work saved.');
    }

    public function show(SchemeOfWork $scheme): View
    {
        $user = auth()->user();
        $scheme->load(['subject', 'schoolClass.schoolSection', 'term.academicSession.school', 'academicSession.school', 'topics.latestLessonPlan', 'uploadedBy', 'submittedBy', 'approvedBy', 'hosApprover', 'boardApprover', 'rejectedBy', 'replaces']);
        abort_unless($this->schemes->canView($user, $scheme), 403);

        return view('schemes.show', [
            'scheme' => $scheme,
            'canPrepare' => $this->schemes->canPrepare($user),
            'canHodApprove' => $this->schemes->canApproveScheme($user, $scheme),
            'canReject' => $this->schemes->canRejectScheme($user, $scheme),
            'canEdit' => $this->schemes->canEditDraft($user, $scheme),
            'canSubmit' => $this->schemes->canEditDraft($user, $scheme) && $scheme->canBeSubmitted(),
            'canClone' => $this->schemes->canPrepare($user) && $this->schemes->teacherHasAssignment(
                $user,
                (int) $scheme->academic_session_id,
                (int) $scheme->school_class_id,
                (int) $scheme->subject_id,
            ),
            'canDelete' => $this->schemes->canEditDraft($user, $scheme) && $scheme->canBePermanentlyDeleted(),
            'canPlanLessons' => $this->schemes->canPlanLessons($user, $scheme),
        ]);
    }

    public function edit(SchemeOfWork $scheme, Request $request): View
    {
        $user = auth()->user();
        abort_unless($this->schemes->canEditDraft($user, $scheme), 403, 'Only the teacher who uploaded an unsubmitted draft can edit it.');

        return view('schemes.form', $this->formData($user, $scheme, $request));
    }

    public function update(Request $request, SchemeOfWork $scheme): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->schemes->canEditDraft($user, $scheme), 403);

        $validated = $this->validatedDraft($request);
        $scheme = $this->schemes->updateDraft($user, $scheme, $validated, $validated['topics'], $request->file('document'));

        if ($request->input('_action') === 'submit') {
            $this->schemes->submit($user, $scheme);

            return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work submitted for section HOD review.');
        }

        return redirect()->route('schemes.edit', $scheme)->with('success', 'Draft scheme of work updated.');
    }

    public function submit(SchemeOfWork $scheme): RedirectResponse
    {
        $this->schemes->submit(auth()->user(), $scheme);

        return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work submitted for section HOD review.');
    }

    public function clone(SchemeOfWork $scheme): RedirectResponse
    {
        $copy = $this->schemes->clone(auth()->user(), $scheme);

        return redirect()->route('schemes.edit', $copy)->with('success', 'Cloned as a new draft. Edit and submit when ready.');
    }

    public function cloneSessionForm(SchemeOfWork $scheme): View
    {
        $user = auth()->user();
        abort_unless($this->schemes->canPrepare($user), 403);
        abort_unless($this->schemes->canView($user, $scheme), 403);

        $scheme->load(['subject', 'schoolClass', 'term', 'academicSession']);
        $sessions = AcademicSession::where('school_id', $user->school_id)->orderByDesc('start_date')->get();
        $terms = Term::query()
            ->whereIn('academic_session_id', $sessions->pluck('id')->filter())
            ->orderBy('sequence')
            ->orderBy('start_date')
            ->get(['id', 'academic_session_id', 'name', 'sequence', 'is_current']);

        return view('schemes.clone-session', compact('scheme', 'sessions', 'terms'));
    }

    public function cloneSession(Request $request, SchemeOfWork $scheme): RedirectResponse
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'exists:terms,id'],
        ]);

        $copy = $this->schemes->cloneToSession(
            auth()->user(),
            $scheme,
            (int) $validated['academic_session_id'],
            (int) $validated['term_id'],
        );

        return redirect()->route('schemes.edit', $copy)->with('success', 'Cloned into the destination session as a new draft. Edit and submit when ready.');
    }

    public function approve(SchemeOfWork $scheme): RedirectResponse
    {
        $scheme = $this->schemes->approve(auth()->user(), $scheme);

        return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work approved and set Active. Teachers can plan against this version.');
    }

    public function reject(Request $request, SchemeOfWork $scheme): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->schemes->reject(auth()->user(), $scheme, $validated['rejection_reason']);

        return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work returned to draft. The teacher can revise and resubmit.');
    }

    public function destroy(SchemeOfWork $scheme): RedirectResponse
    {
        $this->schemes->deleteErroneousVersion(auth()->user(), $scheme);

        return redirect()->route('schemes.index')->with('success', 'Scheme of work version permanently deleted.');
    }

    public function file(SchemeOfWork $scheme): StreamedResponse
    {
        abort_unless($this->schemes->canView(auth()->user(), $scheme), 403);
        abort_unless($scheme->file_path && Storage::disk('public')->exists($scheme->file_path), 404);

        return Storage::disk('public')->download($scheme->file_path);
    }

    protected function canAccessIndex($user): bool
    {
        return $user->isHoD()
            || $user->isTeacher()
            || $this->schemes->canOversee($user);
    }

    protected function applySchemeSort($query, string $sort, string $direction): void
    {
        match ($sort) {
            'session' => $query->orderBy(
                AcademicSession::select('name')->whereColumn('academic_sessions.id', 'schemes_of_work.academic_session_id'),
                $direction,
            ),
            'class' => $query->orderBy(
                SchoolClass::select('name')->whereColumn('school_classes.id', 'schemes_of_work.school_class_id'),
                $direction,
            ),
            'subject' => $query->orderBy(
                Subject::select('name')->whereColumn('subjects.id', 'schemes_of_work.subject_id'),
                $direction,
            ),
            'status' => $query->orderBy('status', $direction)->orderBy('submitted_at', $direction),
            default => $query->orderBy('updated_at', $direction),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedDraft(Request $request): array
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => [
                'required',
                Rule::exists('terms', 'id')->where(
                    fn ($query) => $query->where('academic_session_id', $request->integer('academic_session_id'))
                ),
            ],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'topics' => ['nullable', 'array'],
            'topics.*.week_number' => ['nullable'],
            'topics.*.title' => ['nullable', 'string', 'max:255'],
            'topics.*.learning_objectives' => ['nullable', 'string', 'max:5000'],
            'bulk_template' => ['nullable', 'file', 'max:5120'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ], [
            'term_id.exists' => 'Choose a term that belongs to the selected session.',
        ]);

        if ($request->hasFile('bulk_template')) {
            $validated['topics'] = $this->bulk->parse($request->file('bulk_template'));
        } else {
            $validated['topics'] = $this->filledTopicRows($validated['topics'] ?? []);
        }

        validator($validated, [
            'topics' => ['required', 'array', 'min:1'],
            'topics.*.week_number' => ['required', 'integer', 'min:1', 'max:52'],
            'topics.*.title' => ['required', 'string', 'max:255'],
            'topics.*.learning_objectives' => ['nullable', 'string', 'max:5000'],
        ], [
            'topics.required' => 'Add weekly topics one by one, or upload the bulk-upload CSV template.',
            'topics.min' => 'Add weekly topics one by one, or upload the bulk-upload CSV template.',
        ])->validate();

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function filledTopicRows(array $rows): array
    {
        return array_values(array_filter($rows, function ($row) {
            return trim((string) ($row['title'] ?? '')) !== ''
                || trim((string) ($row['learning_objectives'] ?? '')) !== '';
        }));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData($user, SchemeOfWork $scheme, Request $request): array
    {
        $sessions = AcademicSession::where('school_id', $user->school_id)->orderByDesc('start_date')->get();
        $fallbackSession = $scheme->academicSession
            ?: ($request->integer('session_id') ? $sessions->firstWhere('id', $request->integer('session_id')) : AcademicSession::currentForSchool($user->school_id))
            ?: $sessions->first();

        $selectedSessionId = (int) old('academic_session_id', $scheme->academic_session_id ?? $fallbackSession?->id);
        $session = $sessions->firstWhere('id', $selectedSessionId) ?: $fallbackSession;

        $allTerms = Term::query()
            ->whereIn('academic_session_id', $sessions->pluck('id')->filter())
            ->orderBy('sequence')
            ->orderBy('start_date')
            ->get(['id', 'academic_session_id', 'name', 'is_current']);

        $termsBySession = $allTerms
            ->groupBy(fn (Term $term) => (string) $term->academic_session_id)
            ->map(fn ($group) => $group->map(fn (Term $term) => [
                'id' => (int) $term->id,
                'name' => $term->name,
                'is_current' => (bool) $term->is_current,
            ])->values())
            ->toArray();

        $terms = $allTerms->where('academic_session_id', $session?->id)->values();
        $selectedTermId = old('term_id', $scheme->term_id);
        if ($selectedTermId && ! $terms->contains('id', (int) $selectedTermId)) {
            $selectedTermId = null;
        }
        $selectedTermId = $selectedTermId
            ?? $terms->firstWhere('is_current', true)?->id
            ?? $terms->first()?->id;
        $assignments = TeacherAssignment::where('teacher_id', $user->id)
            ->where('status', 'active')
            ->get(['academic_session_id', 'school_class_id', 'subject_id']);

        $assignedClassIds = $assignments->pluck('school_class_id')->unique()->filter()->all();
        $assignedSubjectIds = $assignments->pluck('subject_id')->unique()->filter()->all();

        $classes = SchoolClass::where('school_id', $user->school_id)
            ->where('status', 'active')
            ->whereIn('id', $assignedClassIds ?: [0])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
        $subjects = Subject::where('school_id', $user->school_id)
            ->where('status', 'active')
            ->whereIn('id', $assignedSubjectIds ?: [0])
            ->orderBy('name')
            ->get();

        $assignmentsBySession = [];
        foreach ($assignments as $assignment) {
            $sessionKey = (string) $assignment->academic_session_id;
            $classKey = (string) $assignment->school_class_id;
            $assignmentsBySession[$sessionKey][$classKey] ??= [];
            $assignmentsBySession[$sessionKey][$classKey][] = (int) $assignment->subject_id;
        }

        $offeredByClass = $assignmentsBySession[(string) $selectedSessionId] ?? [];

        $topicRows = old('topics');
        if (! is_array($topicRows)) {
            $topicRows = $scheme->exists
                ? $scheme->topics->map(fn ($topic) => [
                    'week_number' => $topic->week_number,
                    'title' => $topic->title,
                    'learning_objectives' => implode("\n", $topic->learning_objectives ?? []),
                ])->all()
                : [['week_number' => 1, 'title' => '', 'learning_objectives' => '']];
        }

        return [
            'scheme' => $scheme,
            'sessions' => $sessions,
            'terms' => $terms,
            'classes' => $classes,
            'subjects' => $subjects,
            'offeredByClass' => $offeredByClass,
            'assignmentsBySession' => $assignmentsBySession,
            'topicRows' => array_values($topicRows),
            'session' => $session,
            'allTerms' => $allTerms,
            'termsBySession' => $termsBySession,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId ? (int) $selectedTermId : null,
        ];
    }
}
