<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherAssignmentController extends AdminController
{
    public function index(Request $request): View
    {
        $schoolId = $this->schoolId();
        $sectionId = $request->integer('section_id');
        $teacherId = $request->integer('teacher_id');
        $classId = $request->integer('class_id');
        $subjectId = $request->integer('subject_id');
        $hodId = $request->integer('hod_id');
        $sort = (string) $request->query('sort', 'teacher');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sort, ['teacher', 'section', 'hod', 'class', 'subject', 'session', 'status'], true)) {
            $sort = 'teacher';
        }

        $query = TeacherAssignment::query()
            ->whereHas('academicSession', fn ($q) => $q->where('school_id', $schoolId))
            ->with(['schoolClass.schoolSection.hod', 'subject', 'teacher', 'academicSession'])
            ->when($sectionId, fn ($q) => $q->whereHas('schoolClass', fn ($inner) => $inner->where('school_section_id', $sectionId)))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($hodId, fn ($q) => $q->whereHas('schoolClass.schoolSection', fn ($inner) => $inner->where('hod_id', $hodId)));

        $this->applyAssignmentSort($query, $sort, $direction);

        $assignments = $query->paginate(15)->withQueryString();

        $filters = [
            'sectionId' => $sectionId,
            'teacherId' => $teacherId,
            'classId' => $classId,
            'subjectId' => $subjectId,
            'hodId' => $hodId,
            'sort' => $sort,
            'direction' => $direction,
        ];

        return view('admin.assignments.index', [
            'assignments' => $assignments,
            'filters' => $filters,
            'sections' => SchoolSection::where('school_id', $schoolId)->orderBy('name')->get(),
            'teachers' => User::role('teacher')->where('school_id', $schoolId)->orderBy('name')->get(),
            'classes' => SchoolClass::where('school_id', $schoolId)->orderBy('display_order')->orderBy('name')->get(),
            'subjects' => Subject::where('school_id', $schoolId)->orderBy('name')->get(),
            'hods' => User::role('head_of_department')->where('school_id', $schoolId)->orderBy('name')->get(),
        ]);
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
        $subjectIds = array_values(array_unique(array_map('intval', $validated['subject_ids'])));
        $this->ensureFormRelationsBelongToSchool($validated, $subjectIds);

        DB::transaction(function () use ($validated, $subjectIds) {
            foreach ($subjectIds as $subjectId) {
                TeacherAssignment::create([
                    'school_class_id' => $validated['school_class_id'],
                    'subject_id' => $subjectId,
                    'teacher_id' => $validated['teacher_id'],
                    'academic_session_id' => $validated['academic_session_id'],
                    'status' => $validated['status'],
                ]);
            }
        });

        $count = count($subjectIds);

        return redirect()->route('admin.assignments.index')->with(
            'success',
            $count === 1 ? 'Teacher assignment created.' : $count.' teacher assignments created.'
        );
    }

    public function edit(TeacherAssignment $assignment): View
    {
        $this->ensureAssignmentBelongsToSchool($assignment);
        $assignment->load('schoolClass.schoolSection');

        return view('admin.assignments.form', [
            'assignment' => $assignment,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, TeacherAssignment $assignment): RedirectResponse
    {
        $this->ensureAssignmentBelongsToSchool($assignment);

        $validated = $this->validateAssignment($request, $assignment);
        $this->ensureFormRelationsBelongToSchool($validated, [(int) $validated['subject_id']], $assignment->id);

        $assignment->update([
            'school_class_id' => $validated['school_class_id'],
            'subject_id' => $validated['subject_id'],
            'teacher_id' => $validated['teacher_id'],
            'academic_session_id' => $validated['academic_session_id'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.assignments.index')->with('success', 'Teacher assignment updated.');
    }

    public function destroy(TeacherAssignment $assignment): RedirectResponse
    {
        $this->ensureAssignmentBelongsToSchool($assignment);
        $assignment->delete();

        return redirect()->route('admin.assignments.index')->with('success', 'Teacher assignment removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $classes = SchoolClass::where('school_id', $this->schoolId())
            ->with(['offeredSubjects', 'schoolSection.hod'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $sections = SchoolSection::where('school_id', $this->schoolId())
            ->with('hod')
            ->orderBy('name')
            ->get();

        return [
            'sessions' => AcademicSession::where('school_id', $this->schoolId())->orderByDesc('start_date')->get(),
            'sections' => $sections,
            'classes' => $classes,
            'subjects' => Subject::where('school_id', $this->schoolId())->where('status', 'active')->orderBy('name')->get(),
            'offeredByClass' => $classes->mapWithKeys(fn (SchoolClass $class) => [
                (string) $class->id => $class->offeredSubjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ])->all(),
            'classesBySection' => $classes->groupBy(fn (SchoolClass $class) => (string) ($class->school_section_id ?: ''))
                ->map(fn ($group) => $group->pluck('id')->map(fn ($id) => (int) $id)->values()->all())
                ->all(),
            'hodsBySection' => $sections->mapWithKeys(fn (SchoolSection $section) => [
                (string) $section->id => $section->hod?->name,
            ])->all(),
            'teachers' => User::role('teacher')->where('school_id', $this->schoolId())->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateAssignment(Request $request, ?TeacherAssignment $assignment = null): array
    {
        $rules = [
            'school_section_id' => [
                $assignment ? 'nullable' : 'required',
                Rule::exists('school_sections', 'id')->where(fn ($query) => $query->where('school_id', $this->schoolId())),
            ],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'teacher_id' => ['required', 'exists:users,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'status' => ['required', 'in:active,inactive'],
        ];

        if ($assignment) {
            $rules['subject_id'] = ['required', 'exists:subjects,id'];
        } else {
            $rules['subject_ids'] = ['required', 'array', 'min:1'];
            $rules['subject_ids.*'] = ['integer', 'exists:subjects,id'];
        }

        return $request->validate($rules, [
            'school_section_id.required' => 'Choose a school section.',
            'subject_ids.required' => 'Choose at least one subject for this teacher.',
            'subject_ids.min' => 'Choose at least one subject for this teacher.',
        ]);
    }

    /**
     * @param  list<int>  $subjectIds
     */
    protected function ensureFormRelationsBelongToSchool(array $validated, array $subjectIds, ?int $exceptId = null): void
    {
        abort_unless(SchoolClass::where('id', $validated['school_class_id'])->where('school_id', $this->schoolId())->exists(), 404);
        abort_unless(AcademicSession::where('id', $validated['academic_session_id'])->where('school_id', $this->schoolId())->exists(), 404);
        abort_unless(User::role('teacher')->where('id', $validated['teacher_id'])->where('school_id', $this->schoolId())->exists(), 404);

        foreach ($subjectIds as $subjectId) {
            abort_unless(Subject::where('id', $subjectId)->where('school_id', $this->schoolId())->exists(), 404);
        }

        $class = SchoolClass::with('schoolSection')->find($validated['school_class_id']);
        $section = $class?->schoolSection;
        $requestedSectionId = (int) ($validated['school_section_id'] ?? 0);

        if (! $section) {
            if (! $exceptId) {
                throw ValidationException::withMessages([
                    'school_class_id' => 'Choose a class that belongs to a school section with a Head of Department.',
                ]);
            }
        } else {
            if (! $section->hod_id) {
                throw ValidationException::withMessages([
                    'school_section_id' => 'This school section has no Head of Department. Assign a HOD on School Sections before creating the assignment.',
                ]);
            }

            if ($requestedSectionId !== (int) $section->id) {
                throw ValidationException::withMessages([
                    'school_class_id' => 'Choose a class that belongs to the selected school section.',
                ]);
            }
        }

        foreach ($subjectIds as $subjectId) {
            if ($class && $class->offeredSubjects()->exists() && ! $class->offeredSubjects()->whereKey($subjectId)->exists()) {
                throw ValidationException::withMessages([
                    'subject_ids' => 'Choose subjects that are offered in the selected class.',
                    'subject_id' => 'Choose a subject that is offered in the selected class.',
                ]);
            }

            $duplicate = TeacherAssignment::query()
                ->where('school_class_id', $validated['school_class_id'])
                ->where('subject_id', $subjectId)
                ->where('academic_session_id', $validated['academic_session_id'])
                ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'subject_ids' => 'This class, subject, and session already have a teacher assignment.',
                    'subject_id' => 'This class, subject, and session already have a teacher assignment.',
                ]);
            }
        }
    }

    protected function ensureAssignmentBelongsToSchool(TeacherAssignment $assignment): void
    {
        abort_unless($assignment->academicSession?->school_id === $this->schoolId(), 404);
    }

    protected function applyAssignmentSort($query, string $sort, string $direction): void
    {
        match ($sort) {
            'section' => $query->orderBy(
                SchoolSection::select('name')
                    ->join('school_classes', 'school_classes.school_section_id', '=', 'school_sections.id')
                    ->whereColumn('school_classes.id', 'class_subject_teacher.school_class_id'),
                $direction,
            ),
            'hod' => $query->orderBy(
                User::select('name')
                    ->join('school_sections', 'school_sections.hod_id', '=', 'users.id')
                    ->join('school_classes', 'school_classes.school_section_id', '=', 'school_sections.id')
                    ->whereColumn('school_classes.id', 'class_subject_teacher.school_class_id'),
                $direction,
            ),
            'class' => $query->orderBy(
                SchoolClass::select('name')->whereColumn('school_classes.id', 'class_subject_teacher.school_class_id'),
                $direction,
            ),
            'subject' => $query->orderBy(
                Subject::select('name')->whereColumn('subjects.id', 'class_subject_teacher.subject_id'),
                $direction,
            ),
            'session' => $query->orderBy(
                AcademicSession::select('name')->whereColumn('academic_sessions.id', 'class_subject_teacher.academic_session_id'),
                $direction,
            ),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderBy(
                User::select('name')->whereColumn('users.id', 'class_subject_teacher.teacher_id'),
                $direction,
            ),
        };
    }
}
