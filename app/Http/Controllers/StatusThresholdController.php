<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\StatusThresholdResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusThresholdController extends Controller
{
    public function edit(Request $request, StatusThresholdResolver $resolver): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isHoS() || $user->isBoard(), 403);

        $school = $user->school;
        abort_unless($school, 403);

        $thresholds = $resolver->forSchool($school->id)->all();

        return view('status-thresholds.edit', [
            'school' => $school,
            'thresholds' => $thresholds,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isHoS() || $user->isBoard(), 403);

        /** @var School $school */
        $school = $user->school;
        abort_unless($school, 403);

        $validated = $request->validate([
            'kpi_on_track' => ['required', 'numeric', 'min:0', 'max:2'],
            'kpi_needs_attention' => ['required', 'numeric', 'min:0', 'max:2'],
            'pillar_exceeding' => ['required', 'numeric', 'min:0', 'max:2'],
            'pillar_on_track' => ['required', 'numeric', 'min:0', 'max:2'],
            'health_healthy' => ['required', 'numeric', 'min:0', 'max:2'],
            'health_satisfactory' => ['required', 'numeric', 'min:0', 'max:2'],
        ]);

        abort_unless(
            (float) $validated['kpi_on_track'] >= (float) $validated['kpi_needs_attention'],
            422,
            'KPI on-track minimum must be ≥ needs-attention minimum.'
        );
        abort_unless(
            (float) $validated['pillar_exceeding'] >= (float) $validated['pillar_on_track'],
            422,
            'Pillar exceeding minimum must be ≥ on-track minimum.'
        );
        abort_unless(
            (float) $validated['health_healthy'] >= (float) $validated['health_satisfactory'],
            422,
            'Healthy minimum must be ≥ satisfactory minimum.'
        );

        $settings = $school->settings ?? [];
        $settings['status_thresholds'] = [
            'kpi' => [
                'on_track' => (float) $validated['kpi_on_track'],
                'needs_attention' => (float) $validated['kpi_needs_attention'],
            ],
            'pillar_headline' => [
                'exceeding' => (float) $validated['pillar_exceeding'],
                'on_track' => (float) $validated['pillar_on_track'],
            ],
            'overall_health' => [
                'healthy' => (float) $validated['health_healthy'],
                'satisfactory' => (float) $validated['health_satisfactory'],
            ],
        ];

        $school->update(['settings' => $settings]);

        return redirect()
            ->route('status-thresholds.edit')
            ->with('success', 'Status thresholds updated. Executive labels will use the new scales.');
    }
}
