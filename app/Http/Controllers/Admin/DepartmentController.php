<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends AdminController
{
    public function index(): View
    {
        $departments = Department::where('school_id', $this->schoolId())
            ->withCount(['subjects', 'heads'])
            ->orderBy('name')
            ->get();

        return view('admin.departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('admin.departments.form', [
            'department' => new Department(['status' => 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create([
            ...$this->validateDepartment($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department): View
    {
        abort_unless($department->school_id === $this->schoolId(), 404);

        return view('admin.departments.form', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        abort_unless($department->school_id === $this->schoolId(), 404);

        $department->update($this->validateDepartment($request, $department));

        return redirect()->route('admin.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        abort_unless($department->school_id === $this->schoolId(), 404);

        if ($department->subjects()->exists() || $department->heads()->exists()) {
            return back()->withErrors(['department' => 'Reassign subjects and HODs before deleting this department.']);
        }

        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', 'Department deleted.');
    }

    /**
     * @return array{name: string, status: string}
     */
    protected function validateDepartment(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('departments', 'name')
                    ->where(fn ($query) => $query->where('school_id', $this->schoolId()))
                    ->ignore($department?->id),
            ],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
