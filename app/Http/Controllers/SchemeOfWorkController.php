<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchemeOfWork;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
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

        $query = SchemeOfWork::query()
            ->where('academic_session_id', $session->id)
            ->with(['subject', 'schoolClass', 'term', 'academicSession', 'hosApprover', 'boardApprover', 'topics'])
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->when($filterClassId, fn ($q) => $q->where('school_class_id', $filterClassId))
            ->when($filterSubjectId, fn ($q) => $q->where('subject_id', $filterSubjectId));

        if ($user->isTeacher() && ! $user->isHoD() && ! $this->schemes->canOversee($user)) {
            $assignments = TeacherAssignment::where('teacher_id', $user->id)
                ->where('academic_session_id', $session->id)
                ->where('status', 'active')
                ->get();

            $query->whereIn('status', ['approved', 'active'])->where(function ($inner) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $inner->orWhere(function ($pair) use ($assignment) {
                        $pair->where('school_class_id', $assignment->school_class_id)
                            ->where('subject_id', $assignment->subject_id);
                    });
                }

                if ($assignments->isEmpty()) {
                    $inner->whereRaw('1 = 0');
                }
            });
        } elseif ($user->isHoD() && ! $this->schemes->canOversee($user)) {
            $query->whereHas('subject', fn ($q) => $q->where('school_id', $user->school_id));
        }

        if ($filterStatus === 'submitted') {
            $query->where('status', 'draft')->whereNotNull('submitted_at');
        } elseif ($filterStatus !== '') {
            $query->where('status', $filterStatus);
            if ($filterStatus === 'draft') {
                $query->whereNull('submitted_at');
            }
        }

        $schemes = $query
            ->orderByDesc('version')
            ->orderBy('school_class_id')
            ->orderBy('subject_id')
            ->get();

        $allClasses = SchoolClass::where('school_id', $user->school_id)->orderBy('display_order')->orderBy('name')->get();
        $allSubjects = Subject::where('school_id', $user->school_id)->orderBy('name')->get();

        return view('schemes.index', [
            'schemes' => $schemes,
            'session' => $session,
            'allSessions' => $allSessions,
            'allTerms' => $allTerms,
            'allClasses' => $allClasses,
            'allSubjects' => $allSubjects,
            'canPrepare' => $user->isHoD(),
            'filters' => [
                'sessionId' => $session->id,
                'termId' => $termId,
                'filterClassId' => $filterClassId,
                'filterSubjectId' => $filterSubjectId,
                'filterStatus' => $filterStatus,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->isHoD(), 403);

        return view('schemes.form', $this->formData($user, new SchemeOfWork(['status' => 'draft']), $request));
    }

    public function template(): StreamedResponse
    {
        abort_unless(auth()->user()->isHoD(), 403);

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
        abort_unless($user->isHoD(), 403);

        $validated = $this->validatedDraft($request);
        $scheme = $this->schemes->createDraft($user, $validated, $validated['topics'], $request->file('document'));

        if ($request->input('_action') === 'submit') {
            $this->schemes->submit($user, $scheme);

            return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work submitted for HoS review.');
        }

        return redirect()->route('schemes.edit', $scheme)->with('success', 'Draft scheme of work saved.');
    }

    public function show(SchemeOfWork $scheme): View
    {
        $user = auth()->user();
        $scheme->load(['subject', 'schoolClass', 'term', 'academicSession', 'topics', 'uploadedBy', 'submittedBy', 'hosApprover', 'boardApprover', 'rejectedBy', 'replaces']);
        abort_unless($this->schemes->canView($user, $scheme), 403);

        return view('schemes.show', [
            'scheme' => $scheme,
            'canPrepare' => $user->isHoD(),
            'canHosApprove' => $user->isHoS() && $scheme->canHosApprove(),
            'canBoardApprove' => $user->isBoard() && $scheme->canBoardApprove(),
            'canActivate' => $user->isBoard() && $scheme->canActivate(),
            'canReject' => ($user->isHoS() || $user->isBoard()) && $scheme->canReject(),
            'canEdit' => $user->isHoD() && $scheme->isEditableDraft(),
            'canSubmit' => $user->isHoD() && $scheme->canBeSubmitted(),
            'canClone' => $user->isHoD(),
            'canDelete' => $user->isHoD() && $scheme->canBePermanentlyDeleted(),
        ]);
    }

    public function edit(SchemeOfWork $scheme, Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->isHoD(), 403);
        abort_unless($scheme->isEditableDraft(), 403, 'Only unsubmitted drafts can be edited.');
        abort_unless($this->schemes->canView($user, $scheme), 403);

        return view('schemes.form', $this->formData($user, $scheme, $request));
    }

    public function update(Request $request, SchemeOfWork $scheme): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isHoD(), 403);

        $validated = $this->validatedDraft($request);
        $scheme = $this->schemes->updateDraft($user, $scheme, $validated, $validated['topics'], $request->file('document'));

        if ($request->input('_action') === 'submit') {
            $this->schemes->submit($user, $scheme);

            return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work submitted for HoS review.');
        }

        return redirect()->route('schemes.edit', $scheme)->with('success', 'Draft scheme of work updated.');
    }

    public function submit(SchemeOfWork $scheme): RedirectResponse
    {
        $this->schemes->submit(auth()->user(), $scheme);

        return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work submitted for HoS review.');
    }

    public function clone(SchemeOfWork $scheme): RedirectResponse
    {
        $copy = $this->schemes->clone(auth()->user(), $scheme);

        return redirect()->route('schemes.edit', $copy)->with('success', 'Cloned as a new draft. Edit and submit when ready.');
    }

    public function approve(SchemeOfWork $scheme): RedirectResponse
    {
        $scheme = $this->schemes->approve(auth()->user(), $scheme);
        $message = $scheme->isApproved()
            ? 'Board approval recorded. The scheme is approved and ready to activate.'
            : 'HoS approval recorded. Waiting for Board approval.';

        return redirect()->route('schemes.show', $scheme)->with('success', $message);
    }

    public function reject(Request $request, SchemeOfWork $scheme): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->schemes->reject(auth()->user(), $scheme, $validated['rejection_reason']);

        return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work returned to draft. The Head of Department can revise and resubmit.');
    }

    public function activate(SchemeOfWork $scheme): RedirectResponse
    {
        $this->schemes->activate(auth()->user(), $scheme);

        return redirect()->route('schemes.show', $scheme)->with('success', 'Scheme of work is now Active. Teachers will plan against this version.');
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
        $classes = SchoolClass::where('school_id', $user->school_id)
            ->where('status', 'active')
            ->with('offeredSubjects')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
        $subjects = Subject::where('school_id', $user->school_id)->where('status', 'active')->orderBy('name')->get();
        $offeredByClass = $classes->mapWithKeys(fn (SchoolClass $class) => [
            (string) $class->id => $class->offeredSubjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ])->all();

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
            'topicRows' => array_values($topicRows),
            'session' => $session,
            'allTerms' => $allTerms,
            'termsBySession' => $termsBySession,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId ? (int) $selectedTermId : null,
        ];
    }
}
