<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ExamResult;
use App\Models\Learner;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Services\ExamCalculationService;
use App\Services\ExamSittingReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamResultController extends Controller
{
    public function index(Request $request, ExamCalculationService $exams): View
    {
        $user = auth()->user();
        abort_unless($user->canViewExamResults(), 403);

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

        $termId = $request->integer('term_id');
        $term = $termId
            ? $allTerms->firstWhere('id', $termId)
            : Term::currentForSession($session->id);

        // Filters
        $filterClassId   = $request->integer('class_id');
        $filterSubjectId = $request->integer('subject_id');
        $sortBy          = $request->query('sort', '');  // 'rate_asc', 'rate_desc', ''

        $assignments = $this->assignments($user, $session->id);

        // Apply class / subject filters
        if ($filterClassId) {
            $assignments = $assignments->filter(
                fn ($a) => (int) $a->school_class_id === $filterClassId
            )->values();
        }
        if ($filterSubjectId) {
            $assignments = $assignments->filter(
                fn ($a) => (int) $a->subject_id === $filterSubjectId
            )->values();
        }

        $sittings = $assignments->map(function (TeacherAssignment $assignment) use ($exams, $session, $term) {
            $summary = $term
                ? $exams->sittingSummary($session, $term, $assignment->school_class_id, $assignment->subject_id)
                : ['enrolled' => 0, 'recorded' => 0, 'passed' => 0, 'rate' => 0.0];

            return ['assignment' => $assignment, ...$summary];
        });

        // Sort by rate
        $sittings = match ($sortBy) {
            'rate_asc'  => $sittings->sortBy('rate')->values(),
            'rate_desc' => $sittings->sortByDesc('rate')->values(),
            default     => $sittings,
        };

        $termSummary = $term
            ? $exams->termSummary($session, $term)
            : ['enrolled' => 0, 'passed' => 0, 'rate' => 0.0, 'pass_mark' => 50];

        // Build class/subject lists for filter dropdowns (from all assignments, unfiltered)
        $allAssignments  = $this->assignments($user, $session->id);
        $allClasses  = $allAssignments->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();
        $allSubjects = $allAssignments->pluck('subject')->filter()->unique('id')->sortBy('name')->values();

        $filters = compact('sessionId', 'termId', 'filterClassId', 'filterSubjectId', 'sortBy');

        return view('exam-results.index', compact(
            'sittings', 'session', 'term', 'termSummary',
            'allSessions', 'allTerms', 'allClasses', 'allSubjects', 'filters'
        ));
    }

    public function edit(Request $request, ExamCalculationService $exams): View
    {
        $user = auth()->user();
        abort_unless($user->canEnterExamResults(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $assignments = $this->assignments($user, $session->id);
        $keys = $assignments->map(fn ($row) => $row->school_class_id.':'.$row->subject_id);
        $selected = (string) $request->query('assignment', $keys->first() ?? '');
        abort_unless($selected === '' || $keys->contains($selected), 403);

        $sitting = null;
        $assignment = null;
        if ($selected !== '') {
            [$classId, $subjectId] = array_map('intval', explode(':', $selected));
            $assignment = $assignments->first(
                fn ($row) => (int) $row->school_class_id === $classId && (int) $row->subject_id === $subjectId
            );
            $sitting = $exams->sittingSummary($session, $term, $classId, $subjectId);
        }

        return view('exam-results.marksheet', [
            'assignments' => $assignments,
            'selected' => $selected,
            'assignment' => $assignment,
            'sitting' => $sitting,
            'session' => $session,
            'term' => $term,
        ]);
    }

    public function update(Request $request, ExamCalculationService $exams): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canEnterExamResults(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $assignments = $this->assignments($user, $session->id);
        $keys = $assignments->map(fn ($row) => $row->school_class_id.':'.$row->subject_id)->all();

        $validated = $request->validate([
            'assignment' => ['required', 'string', Rule::in($keys)],
            'scores' => ['nullable', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        [$classId, $subjectId] = array_map('intval', explode(':', $validated['assignment']));
        $current = $exams->sittingSummary($session, $term, $classId, $subjectId);
        if ($current['locked']) {
            return back()->withErrors([
                'assignment' => 'Verified marksheets cannot be edited. Ask the HOD to return it first.',
            ])->withInput();
        }

        $learnerIds = Learner::where('school_class_id', $classId)
            ->where('school_id', $user->school_id)
            ->where('status', 'enrolled')
            ->pluck('id')
            ->all();

        $scores = $validated['scores'] ?? [];
        $assessmentName = $term->name.' examination';

        foreach ($learnerIds as $learnerId) {
            $raw = $scores[$learnerId] ?? null;
            if ($raw === null || $raw === '') {
                ExamResult::where('learner_id', $learnerId)
                    ->where('subject_id', $subjectId)
                    ->where('academic_session_id', $session->id)
                    ->where('term_id', $term->id)
                    ->where('assessment_key', ExamCalculationService::ASSESSMENT_KEY)
                    ->delete();

                continue;
            }

            ExamResult::updateOrCreate(
                [
                    'learner_id' => $learnerId,
                    'subject_id' => $subjectId,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'assessment_key' => ExamCalculationService::ASSESSMENT_KEY,
                ],
                [
                    'school_class_id' => $classId,
                    'recorded_by' => $user->id,
                    'assessment_name' => $assessmentName,
                    'score' => $raw,
                ]
            );
        }

        $exams->recalculateForSession($session, $term);

        $saved = $exams->sittingSummary($session, $term, $classId, $subjectId);
        if ($saved['enrolled'] > 0 && $saved['recorded'] >= $saved['enrolled']) {
            app(ExamSittingReview::class)->markSittingSubmitted($session, $term, $classId, $subjectId);
        }

        return redirect()
            ->route('exam-results.edit', ['assignment' => $validated['assignment']])
            ->with('success', 'Marksheet saved. AE-02 uses enrolled learners, including those without a score. HOD still verifies a complete sitting.');
    }

    public function verify(Request $request, ExamSittingReview $review): RedirectResponse
    {
        [$session, $term, $classId, $subjectId] = $this->sittingFromRequest($request);
        $review->verify(auth()->user(), $session, $term, $classId, $subjectId);

        return back()->with('success', 'HOD verified the marksheet. Below-pass learners are queued on the at-risk register. Executive AE-02 still uses scores vs the enrolled roll.');
    }

    public function reject(Request $request, ExamSittingReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);
        [$session, $term, $classId, $subjectId] = $this->sittingFromRequest($request);
        $review->reject(auth()->user(), $session, $term, $classId, $subjectId, $validated['rejection_reason']);

        return back()->with('success', 'Marksheet returned for revision. The teacher can update scores.');
    }

    /**
     * @return array{0: AcademicSession, 1: Term, 2: int, 3: int}
     */
    protected function sittingFromRequest(Request $request): array
    {
        $user = auth()->user();
        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $assignments = $this->assignments($user, $session->id);
        $keys = $assignments->map(fn ($row) => $row->school_class_id.':'.$row->subject_id)->all();
        $validated = $request->validate([
            'assignment' => ['required', 'string', Rule::in($keys)],
        ]);
        [$classId, $subjectId] = array_map('intval', explode(':', $validated['assignment']));

        return [$session, $term, $classId, $subjectId];
    }

    protected function assignments($user, int $sessionId)
    {
        $query = TeacherAssignment::where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->whereHas('schoolClass', fn ($inner) => $inner->where('school_id', $user->school_id))
            ->with(['teacher', 'schoolClass', 'subject']);

        if ($user->isTeacher() && ! $user->isHoD() && ! $user->isHoS() && ! $user->isAdmin()) {
            $query->where('teacher_id', $user->id);
        }

        if ($user->isHoD() && ! $user->isHoS() && ! $user->isAdmin()) {
            app(\App\Services\HodScope::class)->constrainBySubject($query, $user);
        }

        return $query->get()->sortBy(fn ($row) => $row->schoolClass?->name.' '.$row->subject?->name);
    }
}
