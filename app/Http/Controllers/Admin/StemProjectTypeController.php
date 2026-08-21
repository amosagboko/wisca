<?php

namespace App\Http\Controllers\Admin;

use App\Models\StemProjectType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StemProjectTypeController extends AdminController
{
    public function index(): View
    {
        $types = StemProjectType::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.stem.types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.stem.types.form', [
            'type' => new StemProjectType([
                'display_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        StemProjectType::create([
            ...$this->validateType($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.stem-project-types.index')
            ->with('success', 'STEM project type created.');
    }

    public function edit(StemProjectType $stemProjectType): View
    {
        abort_unless($stemProjectType->school_id === $this->schoolId(), 404);

        return view('admin.stem.types.form', ['type' => $stemProjectType]);
    }

    public function update(Request $request, StemProjectType $stemProjectType): RedirectResponse
    {
        abort_unless($stemProjectType->school_id === $this->schoolId(), 404);

        $stemProjectType->update($this->validateType($request));

        return redirect()->route('admin.stem-project-types.index')
            ->with('success', 'STEM project type updated.');
    }

    public function destroy(StemProjectType $stemProjectType): RedirectResponse
    {
        abort_unless($stemProjectType->school_id === $this->schoolId(), 404);

        if ($stemProjectType->completions()->exists()) {
            return back()->withErrors(['type' => 'Cannot delete this type because completions already use it.']);
        }

        $stemProjectType->delete();

        return redirect()->route('admin.stem-project-types.index')
            ->with('success', 'STEM project type deleted.');
    }

    private function validateType(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
