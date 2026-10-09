<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubjectController extends AdminController
{
    public function index(): View
    {
        $subjects = Subject::where('school_id', $this->schoolId())
            ->with(['department', 'classes' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.subjects.index', compact('subjects'));
    }

    public function create(Request $request): View
    {
        $classes = $this->schoolClasses();

        return view('admin.subjects.form', [
            'subject' => new Subject(['status' => 'active']),
            'classes' => $classes,
            'departments' => $this->schoolDepartments(),
            'selectedClassId' => old('school_class_id', $request->integer('class_id') ?: null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSubject($request, true);
        $class = SchoolClass::query()
            ->where('school_id', $this->schoolId())
            ->findOrFail($validated['school_class_id']);

        $subject = Subject::query()
            ->where('school_id', $this->schoolId())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->first();

        if (! $subject) {
            $subject = Subject::create([
                'school_id' => $this->schoolId(),
                'department_id' => $validated['department_id'] ?? null,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'status' => $validated['status'],
            ]);
        }

        if ($class->offeredSubjects()->whereKey($subject->id)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This class already has that subject.',
            ]);
        }

        $class->offeredSubjects()->attach($subject->id);

        return redirect()->route('admin.subjects.index')->with('success', 'Subject added to '.$class->name.'.');
    }

    public function edit(Subject $subject): View
    {
        abort_unless($subject->school_id === $this->schoolId(), 404);
        $subject->load(['classes' => fn ($query) => $query->orderBy('name')]);

        return view('admin.subjects.form', [
            'subject' => $subject,
            'classes' => $this->schoolClasses(),
            'departments' => $this->schoolDepartments(),
            'selectedClassId' => old('school_class_id', $subject->classes->first()?->id),
        ]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($subject->school_id === $this->schoolId(), 404);

        $validated = $this->validateSubject($request, false);
        $subject->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'status' => $validated['status'],
            'department_id' => $validated['department_id'] ?? null,
        ]);

        if (! empty($validated['school_class_id'])) {
            $class = SchoolClass::query()
                ->where('school_id', $this->schoolId())
                ->findOrFail($validated['school_class_id']);
            $class->offeredSubjects()->syncWithoutDetaching([$subject->id]);
        }

        return redirect()->route('admin.subjects.index')->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        abort_unless($subject->school_id === $this->schoolId(), 404);

        $subject->delete();

        return redirect()->route('admin.subjects.index')->with('success', 'Subject deleted.');
    }

    protected function validateSubject(Request $request, bool $classRequired): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'code' => filled($request->input('code')) ? trim((string) $request->input('code')) : null,
        ]);

        return $request->validate([
            'school_class_id' => [
                $classRequired ? 'required' : 'nullable',
                Rule::exists('school_classes', 'id')->where(fn ($query) => $query
                    ->where('school_id', $this->schoolId())
                    ->where('status', 'active')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:active,inactive'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('school_id', $this->schoolId())
                    ->where('status', 'active')),
            ],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    protected function schoolClasses()
    {
        return SchoolClass::where('school_id', $this->schoolId())
            ->where('status', 'active')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Department>
     */
    protected function schoolDepartments()
    {
        return Department::where('school_id', $this->schoolId())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
