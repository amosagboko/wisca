<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherAssignmentController extends AdminController
{
    public function index(): View
    {
        $assignments = TeacherAssignment::whereHas('academicSession', fn ($q) => $q->where('school_id', $this->schoolId()))
            ->with(['schoolClass', 'subject', 'teacher', 'academicSession'])
            ->latest()
            ->get();

        return view('admin.assignments.index', compact('assignments'));
    }

    public function create(): View
    {
        return view('admin.assignments.form', [
            'assignment' => new TeacherAssignment(['status' => 'active']),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAssignment($request);
        $this->ensureFormRelationsBelongToSchool($validated);

        TeacherAssignment::create($validated);

        return redirect()->route('admin.assignments.index')->with('success', 'Teacher assignment created.');
    }

    public function edit(TeacherAssignment $assignment): View
    {
        $this->ensureAssignmentBelongsToSchool($assignment);

        return view('admin.assignments.form', [
            'assignment' => $assignment,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, TeacherAssignment $assignment): RedirectResponse
    {
        $this->ensureAssignmentBelongsToSchool($assignment);

        $validated = $this->validateAssignment($request, $assignment);
        $this->ensureFormRelationsBelongToSchool($validated);

        $assignment->update($validated);

        return redirect()->route('admin.assignments.index')->with('success', 'Teacher assignment updated.');
    }

    public function destroy(TeacherAssignment $assignment): RedirectResponse
    {
        $this->ensureAssignmentBelongsToSchool($assignment);
        $assignment->delete();

        return redirect()->route('admin.assignments.index')->with('success', 'Teacher assignment removed.');
    }

    /** @return array<string, mixed> */
    protected function formOptions(): array
    {
        $classes = SchoolClass::where('school_id', $this->schoolId())
            ->with('offeredSubjects')
            ->orderBy('name')
            ->get();

        return [
            'sessions' => AcademicSession::where('school_id', $this->schoolId())->orderByDesc('start_date')->get(),
            'classes' => $classes,
            'subjects' => Subject::where('school_id', $this->schoolId())->orderBy('name')->get(),
            'offeredByClass' => $classes->mapWithKeys(fn (SchoolClass $class) => [
                (string) $class->id => $class->offeredSubjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ])->all(),
            'teachers' => User::role('teacher')->where('school_id', $this->schoolId())->orderBy('name')->get(),
        ];
    }

    protected function validateAssignment(Request $request, ?TeacherAssignment $assignment = null): array
    {
        return $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:users,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    protected function ensureFormRelationsBelongToSchool(array $validated): void
    {
        abort_unless(SchoolClass::where('id', $validated['school_class_id'])->where('school_id', $this->schoolId())->exists(), 404);
        abort_unless(Subject::where('id', $validated['subject_id'])->where('school_id', $this->schoolId())->exists(), 404);
        abort_unless(AcademicSession::where('id', $validated['academic_session_id'])->where('school_id', $this->schoolId())->exists(), 404);
        abort_unless(User::role('teacher')->where('id', $validated['teacher_id'])->where('school_id', $this->schoolId())->exists(), 404);

        $class = SchoolClass::find($validated['school_class_id']);
        if ($class && $class->offeredSubjects()->exists() && ! $class->offeredSubjects()->whereKey($validated['subject_id'])->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => 'Choose a subject that is offered in the selected class.',
            ]);
        }
    }

    protected function ensureAssignmentBelongsToSchool(TeacherAssignment $assignment): void
    {
        abort_unless($assignment->academicSession?->school_id === $this->schoolId(), 404);
    }
}
