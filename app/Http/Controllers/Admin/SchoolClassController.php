<?php

namespace App\Http\Controllers\Admin;

use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolClassController extends AdminController
{
    public function index(): View
    {
        $classes = SchoolClass::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.classes.index', compact('classes'));
    }

    public function create(): View
    {
        return view('admin.classes.form', ['class' => new SchoolClass(['status' => 'active', 'display_order' => 0])]);
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

        return view('admin.classes.form', ['class' => $class]);
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
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
