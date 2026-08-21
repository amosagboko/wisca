<?php

namespace App\Http\Controllers\Admin;

use App\Models\DigitalCompetencyArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DigitalCompetencyAreaController extends AdminController
{
    public function index(): View
    {
        $areas = DigitalCompetencyArea::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.competency.areas.index', compact('areas'));
    }

    public function create(): View
    {
        return view('admin.competency.areas.form', [
            'area' => new DigitalCompetencyArea([
                'display_order' => 0,
                'passing_level' => 3,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DigitalCompetencyArea::create([
            ...$this->validateArea($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.digital-competency-areas.index')
            ->with('success', 'Digital competency area created.');
    }

    public function edit(DigitalCompetencyArea $digitalCompetencyArea): View
    {
        abort_unless($digitalCompetencyArea->school_id === $this->schoolId(), 404);

        return view('admin.competency.areas.form', ['area' => $digitalCompetencyArea]);
    }

    public function update(Request $request, DigitalCompetencyArea $digitalCompetencyArea): RedirectResponse
    {
        abort_unless($digitalCompetencyArea->school_id === $this->schoolId(), 404);

        $digitalCompetencyArea->update($this->validateArea($request));

        return redirect()->route('admin.digital-competency-areas.index')
            ->with('success', 'Digital competency area updated.');
    }

    public function destroy(DigitalCompetencyArea $digitalCompetencyArea): RedirectResponse
    {
        abort_unless($digitalCompetencyArea->school_id === $this->schoolId(), 404);

        if ($digitalCompetencyArea->ratings()->exists()) {
            return back()->withErrors(['area' => 'Cannot delete this area because ratings already use it.']);
        }

        $digitalCompetencyArea->delete();

        return redirect()->route('admin.digital-competency-areas.index')
            ->with('success', 'Digital competency area deleted.');
    }

    private function validateArea(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'passing_level' => ['required', 'integer', 'min:1', 'max:4'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
