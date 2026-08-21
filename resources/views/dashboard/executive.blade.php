@php
    $evaluator = app(\App\Services\KpiStatusEvaluator::class);
    $defaultPillar = $summary['pillars']->first()['pillar']->code;
@endphp

<x-portal-layout title="Executive Dashboard">
    <form method="GET" action="{{ route('dashboard') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <x-input-label for="exec_session" value="Session" />
            <select id="exec_session" name="session_id" onchange="this.form.submit()"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <x-portal.page-intro
        eyebrow="Board & Proprietor Edition"
        title="Strategic Health Overview"
        :meta="'Live KPI monitoring for '.$summary['school_name'].' — session '.$session->name.'.'"
    />

    <x-dashboard.school-hero :summary="$summary" :session="$session" class="mb-6" />

    <div
        class="mb-6"
        x-data="{
            pillar: localStorage.getItem('wisca.executive.pillar') || @js($defaultPillar),
            showSummary: false,
            needsAttentionOnly: false,
            selectPillar(code) {
                this.pillar = code;
                localStorage.setItem('wisca.executive.pillar', code);
            }
        }"
    >
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($summary['pillars'] as $pillarSummary)
                <x-dashboard.pillar-card
                    :pillar-summary="$pillarSummary"
                    :code="$pillarSummary['pillar']->code"
                />
            @endforeach
        </div>

        <x-portal.panel class="mt-6" title="KPI Detail" subtitle="Select a pillar above to drill down. Targets, actuals, and owners update live from session data.">
            @foreach ($summary['pillars'] as $pillarSummary)
                <div x-show="pillar === @js($pillarSummary['pillar']->code)" x-cloak>
                    <x-dashboard.kpi-table :pillar-summary="$pillarSummary" />
                </div>
            @endforeach
        </x-portal.panel>

        <div class="mt-6">
            <button
                type="button"
                @click="showSummary = !showSummary"
                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] hover:underline"
            >
                <svg class="h-4 w-4 transition" :class="showSummary && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
                <span x-text="showSummary ? 'Hide pillar summary table' : 'View full pillar summary table'"></span>
            </button>

            <div x-show="showSummary" x-cloak class="mt-4">
                <x-portal.panel title="Strategic Pillars Performance Summary" subtitle="Excel-aligned rollup — school-wide totals included.">
                    <div class="overflow-x-auto -mx-5 sm:-mx-6">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left">
                                <tr>
                                    <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Pillar</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Total KPIs</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">On Track</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Needs Attention</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Off Track</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Avg Achievement</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Headline</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Health</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($summary['pillars'] as $pillarSummary)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $pillarSummary['pillar']->name }}</td>
                                        <td class="px-5 py-3">{{ $pillarSummary['total_kpis'] }}</td>
                                        <td class="px-5 py-3">{{ $pillarSummary['on_track'] }}</td>
                                        <td class="px-5 py-3">{{ $pillarSummary['needs_attention'] }}</td>
                                        <td class="px-5 py-3">{{ $pillarSummary['off_track'] }}</td>
                                        <td class="px-5 py-3">{{ number_format($pillarSummary['avg_achievement'] * 100, 2) }}%</td>
                                        <td class="px-5 py-3">
                                            <x-dashboard.health-badge :status="$pillarSummary['headline_status']" />
                                        </td>
                                        <td class="px-5 py-3">
                                            <x-dashboard.health-badge :status="$pillarSummary['overall_health']" />
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                                    <td class="px-5 sm:px-6 py-3">TOTAL SCHOOL WIDE</td>
                                    <td class="px-5 py-3">{{ $summary['totals']['total_kpis'] }}</td>
                                    <td class="px-5 py-3">{{ $summary['totals']['on_track'] }}</td>
                                    <td class="px-5 py-3">{{ $summary['totals']['needs_attention'] }}</td>
                                    <td class="px-5 py-3">{{ $summary['totals']['off_track'] }}</td>
                                    <td class="px-5 py-3">{{ number_format($summary['totals']['avg_achievement'] * 100, 2) }}%</td>
                                    <td class="px-5 py-3 text-slate-400">—</td>
                                    <td class="px-5 py-3">
                                        <x-dashboard.health-badge :status="$summary['totals']['overall_health']" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-portal.panel>
            </div>
        </div>
    </div>
</x-portal-layout>
