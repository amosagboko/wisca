<?php

namespace App\Http\Controllers\Admin;

use App\Models\ChapelActivityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChapelActivityTypeController extends AdminController
{
    public function index(): View
    {
        $types = ChapelActivityType::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.chapel.activity-types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.chapel.activity-types.form', [
            'type' => new ChapelActivityType([
                'counting_levels' => ['active', 'leading'],
                'school_wide'     => true,
                'display_order'   => 0,
                'is_active'       => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateType($request);

        ChapelActivityType::create([
            ...$validated,
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.chapel-activity-types.index')
            ->with('success', 'Activity type created.');
    }

    public function edit(ChapelActivityType $chapelActivityType): View
    {
        abort_unless($chapelActivityType->school_id === $this->schoolId(), 404);

        return view('admin.chapel.activity-types.form', ['type' => $chapelActivityType]);
    }

    public function update(Request $request, ChapelActivityType $chapelActivityType): RedirectResponse
    {
        abort_unless($chapelActivityType->school_id === $this->schoolId(), 404);

        $chapelActivityType->update($this->validateType($request));

        return redirect()->route('admin.chapel-activity-types.index')
            ->with('success', 'Activity type updated.');
    }

    public function destroy(ChapelActivityType $chapelActivityType): RedirectResponse
    {
        abort_unless($chapelActivityType->school_id === $this->schoolId(), 404);

        if ($chapelActivityType->chapelSessions()->exists()) {
            return back()->withErrors(['type' => 'Cannot delete — this type has existing sessions.']);
        }

        $chapelActivityType->delete();

        return redirect()->route('admin.chapel-activity-types.index')
            ->with('success', 'Activity type deleted.');
    }

    private function validateType(Request $request): array
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'code'            => ['nullable', 'string', 'max:20'],
            'description'     => ['nullable', 'string', 'max:500'],
            'counting_levels' => ['required', 'array', 'min:1'],
            'counting_levels.*' => ['in:passive,active,leading'],
            'school_wide'     => ['boolean'],
            'display_order'   => ['integer', 'min:0'],
            'is_active'       => ['boolean'],
        ]);

        $data['school_wide']   = $request->boolean('school_wide', true);
        $data['is_active']     = $request->boolean('is_active', true);
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        return $data;
    }
}
