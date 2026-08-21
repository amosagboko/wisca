<?php

namespace App\Http\Controllers\Admin;

use App\Models\BullyingCaseType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BullyingCaseTypeController extends AdminController
{
    public function index(): View
    {
        $types = BullyingCaseType::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.bullying.types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.bullying.types.form', [
            'type' => new BullyingCaseType([
                'display_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BullyingCaseType::create([
            ...$this->validateType($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.bullying-case-types.index')
            ->with('success', 'Bullying case type created.');
    }

    public function edit(BullyingCaseType $bullyingCaseType): View
    {
        abort_unless($bullyingCaseType->school_id === $this->schoolId(), 404);

        return view('admin.bullying.types.form', ['type' => $bullyingCaseType]);
    }

    public function update(Request $request, BullyingCaseType $bullyingCaseType): RedirectResponse
    {
        abort_unless($bullyingCaseType->school_id === $this->schoolId(), 404);

        $bullyingCaseType->update($this->validateType($request));

        return redirect()->route('admin.bullying-case-types.index')
            ->with('success', 'Bullying case type updated.');
    }

    public function destroy(BullyingCaseType $bullyingCaseType): RedirectResponse
    {
        abort_unless($bullyingCaseType->school_id === $this->schoolId(), 404);

        if ($bullyingCaseType->cases()->exists()) {
            return back()->withErrors(['type' => 'Cannot delete this type because bullying cases already use it.']);
        }

        $bullyingCaseType->delete();

        return redirect()->route('admin.bullying-case-types.index')
            ->with('success', 'Bullying case type deleted.');
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
