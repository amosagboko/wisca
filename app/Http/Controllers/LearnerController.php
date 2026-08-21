<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LearnerController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->canViewLearners(), 403);

        // All sessions for this school
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')
            ->get();

        // Resolve selected session (default: current)
        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);

        // Terms for the selected session
        $allTerms = $session
            ? Term::where('academic_session_id', $session->id)->orderBy('start_date')->get()
            : collect();

        $termId = $request->integer('term_id');
        $term = $termId ? $allTerms->firstWhere('id', $termId) : null;

        // Accessible class IDs for the resolved session
        $classIds = $this->classIds($user, $session?->id);

        // When a term is selected, narrow classes to those with active assignments in that term
        $classes = SchoolClass::where('school_id', $user->school_id)
            ->when($classIds !== null, fn ($q) => $q->whereIn('id', $classIds ?: [0]))
            ->when($term, fn ($q) => $q->whereHas('teacherAssignments', fn ($a) => $a
                ->where('academic_session_id', $session->id)
            ))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $selectedClass = $request->integer('class') ?: $classes->first()?->id;
        $search        = trim((string) $request->query('search', ''));
        $status        = $request->query('status', 'enrolled'); // 'enrolled', 'withdrawn', or 'all'

        $learners = Learner::where('school_id', $user->school_id)
            ->when($classIds !== null, fn ($q) => $q->whereIn('school_class_id', $classIds ?: [0]))
            ->when($selectedClass, fn ($q) => $q->where('school_class_id', $selectedClass))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                  ->orWhere('admission_no', 'like', '%'.$search.'%');
            }))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        return view('learners.index', [
            'learners'       => $learners,
            'classes'        => $classes,
            'selectedClass'  => $selectedClass,
            'canManage'      => $user->canManageLearners(),
            'allSessions'    => $allSessions,
            'allTerms'       => $allTerms,
            'session'        => $session,
            'term'           => $term,
            'filters'        => [
                'sessionId' => $session?->id ?? 0,
                'termId'    => $termId,
                'class'     => $selectedClass,
                'search'    => $search,
                'status'    => $status,
            ],
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();
        abort_unless($user->canManageLearners(), 403);

        return view('learners.form', [
            'learner' => new Learner(['status' => 'enrolled']),
            'classes' => $this->classes($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persist($request, new Learner);
    }

    public function edit(Learner $learner): View
    {
        $this->assertSchool($learner);
        abort_unless(auth()->user()->canManageLearners(), 403);

        return view('learners.form', [
            'learner' => $learner,
            'classes' => $this->classes(auth()->user()),
        ]);
    }

    public function update(Request $request, Learner $learner): RedirectResponse
    {
        $this->assertSchool($learner);

        return $this->persist($request, $learner);
    }

    public function destroy(Learner $learner): RedirectResponse
    {
        $this->assertSchool($learner);
        abort_unless(auth()->user()->canManageLearners(), 403);

        $learner->delete();

        return redirect()->route('learners.index')->with('success', 'Learner removed from the roll.');
    }

    protected function persist(Request $request, Learner $learner): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canManageLearners(), 403);

        $classIds = $this->classes($user)->pluck('id')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'admission_no' => ['nullable', 'string', 'max:50'],
            'school_class_id' => ['required', 'integer', Rule::in($classIds)],
            'gender' => ['nullable', 'in:male,female'],
            'status' => ['required', 'in:enrolled,withdrawn'],
        ]);

        $learner->fill([
            ...$validated,
            'school_id' => $user->school_id,
        ])->save();

        return redirect()->route('learners.index', ['class' => $learner->school_class_id])
            ->with('success', 'Learner saved.');
    }

    protected function assertSchool(Learner $learner): void
    {
        abort_unless((int) $learner->school_id === (int) auth()->user()->school_id, 403);
    }

    protected function classes($user)
    {
        return SchoolClass::where('school_id', $user->school_id)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, int>|null */
    protected function classIds($user, ?int $sessionId): ?array
    {
        if ($user->canManageLearners() || $user->isHoD() || $user->isHoS() || $user->isLearningSupport()) {
            return null;
        }

        if (! $sessionId) {
            return [];
        }

        return TeacherAssignment::where('teacher_id', $user->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->pluck('school_class_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
