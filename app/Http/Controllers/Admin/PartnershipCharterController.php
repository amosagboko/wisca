<?php

namespace App\Http\Controllers\Admin;

use App\Models\PartnershipCharter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnershipCharterController extends AdminController
{
    public function index(): View
    {
        $charters = PartnershipCharter::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        return view('admin.partnership.charters.index', compact('charters'));
    }

    public function create(): View
    {
        return view('admin.partnership.charters.form', [
            'charter' => new PartnershipCharter([
                'display_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PartnershipCharter::create([
            ...$this->validateCharter($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.partnership-charters.index')
            ->with('success', 'Partnership charter created.');
    }

    public function edit(PartnershipCharter $partnershipCharter): View
    {
        abort_unless($partnershipCharter->school_id === $this->schoolId(), 404);

        return view('admin.partnership.charters.form', [
            'charter' => $partnershipCharter,
        ]);
    }

    public function update(Request $request, PartnershipCharter $partnershipCharter): RedirectResponse
    {
        abort_unless($partnershipCharter->school_id === $this->schoolId(), 404);

        $partnershipCharter->update($this->validateCharter($request));

        return redirect()->route('admin.partnership-charters.index')
            ->with('success', 'Partnership charter updated.');
    }

    public function destroy(PartnershipCharter $partnershipCharter): RedirectResponse
    {
        abort_unless($partnershipCharter->school_id === $this->schoolId(), 404);

        if ($partnershipCharter->signatures()->exists()) {
            return back()->withErrors(['charter' => 'Cannot delete this charter because it already has recorded signatures.']);
        }

        $partnershipCharter->delete();

        return redirect()->route('admin.partnership-charters.index')
            ->with('success', 'Partnership charter deleted.');
    }

    private function validateCharter(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:10000'],
            'version' => ['nullable', 'string', 'max:20'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
