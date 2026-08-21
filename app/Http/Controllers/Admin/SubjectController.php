<?php

namespace App\Http\Controllers\Admin;

use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends AdminController
{
    public function index(): View
    {
        $subjects = Subject::where('school_id', $this->schoolId())
            ->orderBy('name')
            ->get();

        return view('admin.subjects.index', compact('subjects'));
    }

    public function create(): View
    {
        return view('admin.subjects.form', ['subject' => new Subject(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Subject::create([
            ...$this->validateSubject($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.subjects.index')->with('success', 'Subject created.');
    }

    public function edit(Subject $subject): View
    {
        abort_unless($subject->school_id === $this->schoolId(), 404);

        return view('admin.subjects.form', compact('subject'));
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($subject->school_id === $this->schoolId(), 404);

        $subject->update($this->validateSubject($request));

        return redirect()->route('admin.subjects.index')->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        abort_unless($subject->school_id === $this->schoolId(), 404);

        $subject->delete();

        return redirect()->route('admin.subjects.index')->with('success', 'Subject deleted.');
    }

    protected function validateSubject(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
