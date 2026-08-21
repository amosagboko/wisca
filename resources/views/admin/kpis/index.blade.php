<x-portal-layout title="KPI Settings">
    <x-portal.page-intro
        eyebrow="Strategy"
        title="KPI Settings"
        meta="Adjust targets, ownership, frequency, and status thresholds for each KPI."
    />

    @foreach ($kpis as $pillarName => $pillarKpis)
        <x-portal.panel class="{{ $loop->first ? '' : 'mt-6' }}" :title="$pillarName">
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Code</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Metric</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Target</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Owner role</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Frequency</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pillarKpis as $kpi)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $kpi->code }}</td>
                                <td class="px-5 py-3">{{ $kpi->name }}</td>
                                <td class="px-5 py-3">{{ number_format($kpi->default_target * 100, 0) }}%</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $kpi->owner_role) }}</td>
                                <td class="px-5 py-3 capitalize">{{ $kpi->frequency }}</td>
                                <td class="px-5 py-3 capitalize">{{ $kpi->status }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.kpis.edit', $kpi) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Configure</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-portal.panel>
    @endforeach
</x-portal-layout>
