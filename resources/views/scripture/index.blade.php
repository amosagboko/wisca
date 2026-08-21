@php
    $f = $filters;
    $percent = $summary['assessed'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 85;
@endphp

<x-portal-layout title="Scripture Mastery">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-06"
        title="Scripture memory and application"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Learners reciting and contextually explaining verses ÷ total assessed. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('scripture.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="sc_session" value="Session" />
            <select id="sc_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="sc_term" value="Term" />
            <select id="sc_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="sc_passage" value="Passage" />
            <select id="sc_passage" name="passage_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['passageId'] === 0)>All passages</option>
                @foreach ($passages as $p)
                    <option value="{{ $p->id }}" @selected($p->id === $f['passageId'])>{{ $p->reference }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('scripture.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">CE-06 this period</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['mastered'] }} mastered of {{ $summary['assessed'] }} assessed · target {{ $target }}%</p>
            </div>
            <a href="{{ route('scripture.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Assess class
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel :title="'Class breakdown ('.$classSummaries->count().')'" subtitle="Mastery means learner recites correctly and explains contextually.">
        @if ($classSummaries->isEmpty())
            <p class="text-sm text-slate-500">No assessed rows found for current filters.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Assessed</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Mastered</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classSummaries as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $row['class']->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['assessed'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['mastered'] }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ number_format($row['rate'] * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    <x-portal.panel class="mt-6" :title="'Assessment rows ('.$rows->count().')'">
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-500">No assessment rows found.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Learner</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Passage</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Recites</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Explains</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Counts</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 text-slate-700">{{ $row->assessed_on->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $row->learner?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $row->passage?->reference ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $row->recites_correctly ? 'Yes' : 'No' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $row->explains_contextually ? 'Yes' : 'No' }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $row->countsForKpi(),
                                        'bg-amber-100 text-amber-800' => ! $row->countsForKpi(),
                                    ])>{{ $row->countsForKpi() ? 'Yes' : 'No' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
