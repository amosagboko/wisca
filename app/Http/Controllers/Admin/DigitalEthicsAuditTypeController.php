<?php

namespace App\Http\Controllers\Admin;

use App\Models\DigitalEthicsAuditType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DigitalEthicsAuditTypeController extends AdminController
{
    public function index(): View
    {
        $types = DigitalEthicsAuditType::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.ethics.types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.ethics.types.form', [
            'type' => new DigitalEthicsAuditType([
                'display_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DigitalEthicsAuditType::create([
            ...$this->validateType($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.digital-ethics-audit-types.index')
            ->with('success', 'Digital ethics audit type created.');
    }

    public function edit(DigitalEthicsAuditType $digitalEthicsAuditType): View
    {
        abort_unless($digitalEthicsAuditType->school_id === $this->schoolId(), 404);

        return view('admin.ethics.types.form', ['type' => $digitalEthicsAuditType]);
    }

    public function update(Request $request, DigitalEthicsAuditType $digitalEthicsAuditType): RedirectResponse
    {
        abort_unless($digitalEthicsAuditType->school_id === $this->schoolId(), 404);

        $digitalEthicsAuditType->update($this->validateType($request));

        return redirect()->route('admin.digital-ethics-audit-types.index')
            ->with('success', 'Digital ethics audit type updated.');
    }

    public function destroy(DigitalEthicsAuditType $digitalEthicsAuditType): RedirectResponse
    {
        abort_unless($digitalEthicsAuditType->school_id === $this->schoolId(), 404);

        if ($digitalEthicsAuditType->audits()->exists()) {
            return back()->withErrors(['type' => 'Cannot delete this type because audits already use it.']);
        }

        $digitalEthicsAuditType->delete();

        return redirect()->route('admin.digital-ethics-audit-types.index')
            ->with('success', 'Digital ethics audit type deleted.');
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
