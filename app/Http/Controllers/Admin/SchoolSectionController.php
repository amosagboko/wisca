<?php

namespace App\Http\Controllers\Admin;

use App\Models\SchoolSection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolSectionController extends AdminController
{
    public function index(): View
    {
        $sections = SchoolSection::where('school_id', $this->schoolId())
            ->with('hod')
            ->withCount('classes')
            ->orderBy('name')
            ->get();

        return view('admin.school-sections.index', compact('sections'));
    }

    public function create(): View
    {
        return view('admin.school-sections.form', [
            'section' => new SchoolSection(['status' => 'active']),
            'hods' => $this->hodCandidates(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SchoolSection::create([
            ...$this->validateSection($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.school-sections.index')->with('success', 'School section created.');
    }

    public function edit(SchoolSection $schoolSection): View
    {
        abort_unless($schoolSection->school_id === $this->schoolId(), 404);

        return view('admin.school-sections.form', [
            'section' => $schoolSection,
            'hods' => $this->hodCandidates(),
        ]);
    }

    public function update(Request $request, SchoolSection $schoolSection): RedirectResponse
    {
        abort_unless($schoolSection->school_id === $this->schoolId(), 404);

        $schoolSection->update($this->validateSection($request, $schoolSection));

        return redirect()->route('admin.school-sections.index')->with('success', 'School section updated.');
    }

    public function destroy(SchoolSection $schoolSection): RedirectResponse
    {
        abort_unless($schoolSection->school_id === $this->schoolId(), 404);

        if ($schoolSection->classes()->exists()) {
            return back()->withErrors(['section' => 'Reassign classes before deleting this school section.']);
        }

        $schoolSection->delete();

        return redirect()->route('admin.school-sections.index')->with('success', 'School section deleted.');
    }

    /**
     * @return array{name: string, status: string, hod_id: int|null}
     */
    protected function validateSection(Request $request, ?SchoolSection $section = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('school_sections', 'name')
                    ->where(fn ($query) => $query->where('school_id', $this->schoolId()))
                    ->ignore($section?->id),
            ],
            'status' => ['required', 'in:active,inactive'],
            'hod_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('school_id', $this->schoolId())),
            ],
        ]);

        if (! empty($validated['hod_id'])) {
            $hod = User::find($validated['hod_id']);
            abort_unless($hod?->isHoD(), 422, 'Choose a staff member with the Head of Department role.');
        }

        $validated['hod_id'] = $validated['hod_id'] ?: null;

        return $validated;
    }

    protected function hodCandidates()
    {
        return User::where('school_id', $this->schoolId())
            ->where('status', 'active')
            ->role('head_of_department')
            ->orderBy('name')
            ->get();
    }
}
