<?php

namespace App\Http\Controllers\Admin;

use App\Models\SchoolClass;
use App\Models\SchoolSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolClassController extends AdminController
{
    public function index(): View
    {
        $classes = SchoolClass::where('school_id', $this->schoolId())
            ->with('schoolSection')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.classes.index', compact('classes'));
    }

    public function create(): View
    {
        return view('admin.classes.form', [
            'class' => new SchoolClass(['status' => 'active']),
            'sections' => $this->sections(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateClass($request);

        SchoolClass::create([
            ...$validated,
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.classes.index')->with('success', 'Class created.');
    }

    public function edit(SchoolClass $class): View
    {
        abort_unless($class->school_id === $this->schoolId(), 404);

        return view('admin.classes.form', [
            'class' => $class,
            'sections' => $this->sections(),
        ]);
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        abort_unless($class->school_id === $this->schoolId(), 404);

        $class->update($this->validateClass($request));

        return redirect()->route('admin.classes.index')->with('success', 'Class updated.');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        abort_unless($class->school_id === $this->schoolId(), 404);

        $class->delete();

        return redirect()->route('admin.classes.index')->with('success', 'Class deleted.');
    }

    protected function validateClass(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'school_section_id' => [
                'nullable',
                Rule::exists('school_sections', 'id')->where(fn ($query) => $query->where('school_id', $this->schoolId())),
            ],
        ]);

        $validated['school_section_id'] = $validated['school_section_id'] ?: null;

        return $validated;
    }

    protected function sections()
    {
        return SchoolSection::where('school_id', $this->schoolId())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
