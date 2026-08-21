@props(['pillarSummary'])

@php
    use App\Support\RoleLabels;

    $evaluator = app(\App\Services\KpiStatusEvaluator::class);
    $pillar = $pillarSummary['pillar'];
@endphp

<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="font-display text-lg font-semibold text-[#0f2d4a]">{{ $pillar->name }}</h3>
            <p class="text-sm text-slate-500">{{ $pillarSummary['total_kpis'] }} KPIs · Avg {{ number_format($pillarSummary['avg_achievement'] * 100, 2) }}%</p>
        </div>
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" x-model="needsAttentionOnly" class="rounded border-gray-300 text-[#0f2d4a] shadow-sm focus:ring-[#0f2d4a]">
            Show needs attention / off track only
        </label>
    </div>

    <div class="overflow-x-auto -mx-5 sm:-mx-6">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Code</th>
                    <th class="px-5 py-3 font-semibold text-slate-600">Metric</th>
                    <th class="px-5 py-3 font-semibold text-slate-600">Target</th>
                    <th class="px-5 py-3 font-semibold text-slate-600">Actual</th>
                    <th class="px-5 py-3 font-semibold text-slate-600">Achievement</th>
                    <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                    <th class="px-5 py-3 font-semibold text-slate-600">Owner</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pillarSummary['kpis'] as $row)
                    @php
                        $kpi = $row['kpi'];
                        $data = $row['data'];
                        $status = $data?->status;
                        $target = $kpi->target_type === 'hours' ? $kpi->default_target.' hrs' : number_format((float) $kpi->default_target * 100, 0).'%';
                        $actual = $data ? ($kpi->target_type === 'hours' ? $data->actual_value.' hrs' : number_format((float) $data->actual_value * 100, 1).'%') : '—';
                        $achievement = $data ? number_format((float) $data->achievement_rate * 100, 2).'%' : '—';
                    @endphp
                    <tr
                        class="border-t border-slate-100"
                        x-show="!needsAttentionOnly || @js(in_array($status, ['NEEDS ATTENTION', 'OFF TRACK'], true))"
                    >
                        <td class="px-5 sm:px-6 py-3 font-mono text-xs text-slate-700">{{ $kpi->code }}</td>
                        <td class="px-5 py-3 text-slate-800">{{ $kpi->name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $target }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $actual }}</td>
                        <td class="px-5 py-3 font-medium text-slate-800">{{ $achievement }}</td>
                        <td class="px-5 py-3">
                            @if ($data)
                                <x-dashboard.health-badge :status="$data->status" />
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ RoleLabels::label($kpi->owner_role) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
