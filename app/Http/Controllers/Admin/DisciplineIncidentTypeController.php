<?php

namespace App\Http\Controllers\Admin;

use App\Models\DisciplineIncidentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisciplineIncidentTypeController extends AdminController
{
    public function index(): View
    {
        $types = DisciplineIncidentType::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.discipline.types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.discipline.types.form', [
            'type' => new DisciplineIncidentType([
                'restorative_required' => true,
                'display_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DisciplineIncidentType::create([
            ...$this->validateType($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.discipline-incident-types.index')
            ->with('success', 'Incident type created.');
    }

    public function edit(DisciplineIncidentType $disciplineIncidentType): View
    {
        abort_unless($disciplineIncidentType->school_id === $this->schoolId(), 404);

        return view('admin.discipline.types.form', ['type' => $disciplineIncidentType]);
    }

    public function update(Request $request, DisciplineIncidentType $disciplineIncidentType): RedirectResponse
    {
        abort_unless($disciplineIncidentType->school_id === $this->schoolId(), 404);

        $disciplineIncidentType->update($this->validateType($request));

        return redirect()->route('admin.discipline-incident-types.index')
            ->with('success', 'Incident type updated.');
    }

    public function destroy(DisciplineIncidentType $disciplineIncidentType): RedirectResponse
    {
        abort_unless($disciplineIncidentType->school_id === $this->schoolId(), 404);

        if ($disciplineIncidentType->incidents()->exists()) {
            return back()->withErrors(['type' => 'Cannot delete this type because incidents already use it.']);
        }

        $disciplineIncidentType->delete();

        return redirect()->route('admin.discipline-incident-types.index')
            ->with('success', 'Incident type deleted.');
    }

    private function validateType(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'restorative_required' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['restorative_required'] = $request->boolean('restorative_required', true);
        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
