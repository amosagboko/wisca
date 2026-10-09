@php
    $evaluator = app(\App\Services\KpiStatusEvaluator::class);
    $firstPillar = $summary['pillars']->first();
    $defaultPillar = is_array($firstPillar) ? ($firstPillar['pillar']->code ?? 'AE') : 'AE';
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
        @if (($allTerms ?? collect())->count() > 1)
            <div>
                <x-input-label for="exec_term" value="Term" />
                <select id="exec_term" name="term_id" onchange="this.form.submit()"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($allTerms as $t)
                        <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </form>

    <x-portal.page-intro
        eyebrow="Board & Proprietor Edition"
        title="Strategic Health Overview"
        :meta="'Live KPI monitoring for '.$summary['school_name'].' — session '.$session->name.'.'"
    >
        @if (auth()->user()?->canManageAcademicPeriod())
            <x-slot:actions>
                <a href="{{ route('academic-period.show') }}" class="inline-flex items-center rounded-lg border border-white/30 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-white/10">Academic period</a>
            </x-slot:actions>
        @endif
    </x-portal.page-intro>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <x-dashboard.school-hero :summary="$summary" :session="$session" class="mb-6" />

    @if ($term ?? null)
        @php
            $leadInbox = $leadership['inbox'] ?? [];
            $leadTotal = (int) ($leadInbox['total'] ?? collect($leadership['items'] ?? [])->count());
        @endphp
        <x-portal.work-inbox
            title="Operational exceptions"
            :subtitle="$leadTotal === 0
                ? 'Week '.($leadership['week_number'] ?? 1).' · '.$term->name.'. Teachers capture and HODs verify. You review outstanding department work — this does not change AE KPI formulas.'
                : 'Week '.($leadership['week_number'] ?? 1).' · '.$term->name.'. '.$leadTotal.' outstanding area'.($leadTotal === 1 ? '' : 's').', grouped by type. Teachers capture and HODs verify — this does not change AE KPI formulas.'"
            :items="$leadInbox['items'] ?? $leadership['items'] ?? collect()"
            :grouped="$leadInbox['groups'] ?? null"
            :types="$leadInbox['types'] ?? null"
            :paginator="$leadInbox['paginator'] ?? null"
            :active-type="$leadInbox['active_type'] ?? ''"
            :total="$leadTotal"
            :filtered-total="$leadInbox['filtered_total'] ?? $leadTotal"
            empty="No outstanding HOD reviews or IIP gaps for this term. You can still record that leadership reviewed the week."
        />

        <x-portal.panel class="mb-6" title="Leadership week review" subtitle="A sign-off that you have seen this week’s exceptions. It does not approve lesson plans or verify coverage.">
            @if ($leadership['review'] ?? null)
                <p class="text-sm text-slate-600">
                    Week {{ $leadership['week_number'] }} recorded by
                    <span class="font-medium text-[#0f2d4a]">{{ $leadership['review']->reviewer?->name }}</span>
                    on {{ $leadership['review']->reviewed_at?->format('d M Y H:i') }}.
                </p>
                @if ($leadership['review']->notes)
                    <p class="mt-2 text-sm text-slate-500">{{ $leadership['review']->notes }}</p>
                @endif
            @else
                <p class="text-sm text-slate-500">No leadership review recorded for week {{ $leadership['week_number'] ?? 1 }} yet.</p>
            @endif

            @if ($canReviewWeek ?? false)
                <form method="POST" action="{{ route('leadership-week-reviews.store') }}" class="mt-4 max-w-xl space-y-3">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ $session->id }}">
                    <input type="hidden" name="term_id" value="{{ $term->id }}">
                    <input type="hidden" name="week_number" value="{{ $leadership['week_number'] ?? 1 }}">
                    <div>
                        <x-input-label for="lead_notes" value="Notes (optional)" />
                        <textarea id="lead_notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $leadership['review']->notes ?? '') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                    <x-primary-button>{{ ($leadership['review'] ?? null) ? 'Update week review' : 'Record week review' }}</x-primary-button>
                </form>
            @endif
        </x-portal.panel>
    @endif

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
