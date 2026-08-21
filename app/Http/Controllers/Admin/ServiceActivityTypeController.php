<?php

namespace App\Http\Controllers\Admin;

use App\Models\ServiceActivityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceActivityTypeController extends AdminController
{
    public function index(): View
    {
        $types = ServiceActivityType::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.service.activity-types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.service.activity-types.form', [
            'type' => new ServiceActivityType([
                'display_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ServiceActivityType::create([
            ...$this->validateType($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.service-activity-types.index')
            ->with('success', 'Service activity type created.');
    }

    public function edit(ServiceActivityType $serviceActivityType): View
    {
        abort_unless($serviceActivityType->school_id === $this->schoolId(), 404);

        return view('admin.service.activity-types.form', [
            'type' => $serviceActivityType,
        ]);
    }

    public function update(Request $request, ServiceActivityType $serviceActivityType): RedirectResponse
    {
        abort_unless($serviceActivityType->school_id === $this->schoolId(), 404);

        $serviceActivityType->update($this->validateType($request));

        return redirect()->route('admin.service-activity-types.index')
            ->with('success', 'Service activity type updated.');
    }

    public function destroy(ServiceActivityType $serviceActivityType): RedirectResponse
    {
        abort_unless($serviceActivityType->school_id === $this->schoolId(), 404);

        if ($serviceActivityType->logs()->exists()) {
            return back()->withErrors(['type' => 'Cannot delete this type because it already has service logs.']);
        }

        $serviceActivityType->delete();

        return redirect()->route('admin.service-activity-types.index')
            ->with('success', 'Service activity type deleted.');
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
