<?php

namespace App\Http\Controllers\Admin;

use App\Models\Kpi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiController extends AdminController
{
    public function index(): View
    {
        $kpis = Kpi::whereHas('pillar', fn ($q) => $q->where('school_id', $this->schoolId()))
            ->with('pillar')
            ->orderBy('display_order')
            ->get()
            ->groupBy(fn (Kpi $kpi) => $kpi->pillar->name);

        return view('admin.kpis.index', compact('kpis'));
    }

    public function edit(Kpi $kpi): View
    {
        $this->ensureKpiBelongsToSchool($kpi);

        return view('admin.kpis.form', [
            'kpi' => $kpi->load('pillar'),
            'ownerRoles' => $this->assignableRoles(),
        ]);
    }

    public function update(Request $request, Kpi $kpi): RedirectResponse
    {
        $this->ensureKpiBelongsToSchool($kpi);

        $validated = $request->validate([
            'default_target' => ['required', 'numeric', 'min:0'],
            'owner_role' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'on_track_min' => ['required', 'numeric', 'min:0', 'max:2'],
            'needs_attention_min' => ['required', 'numeric', 'min:0', 'max:2'],
            'pass_mark' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $config = $kpi->config ?? [];
        if ($kpi->code === 'AE-02' && array_key_exists('pass_mark', $validated) && $validated['pass_mark'] !== null) {
            $config['pass_mark'] = (float) $validated['pass_mark'];
        }
        $config['thresholds'] = [
            'on_track' => ['min' => (float) $validated['on_track_min'], 'label' => 'ON TRACK'],
            'needs_attention' => ['min' => (float) $validated['needs_attention_min'], 'label' => 'NEEDS ATTENTION'],
            'off_track' => ['max' => (float) $validated['needs_attention_min'] - 0.0001, 'label' => 'OFF TRACK'],
        ];

        $kpi->update([
            'default_target' => $validated['default_target'],
            'owner_role' => $validated['owner_role'],
            'frequency' => $validated['frequency'],
            'status' => $validated['status'],
            'config' => $config,
        ]);

        return redirect()->route('admin.kpis.index')->with('success', "{$kpi->code} updated.");
    }

    protected function ensureKpiBelongsToSchool(Kpi $kpi): void
    {
        abort_unless($kpi->pillar?->school_id === $this->schoolId(), 404);
    }
}
