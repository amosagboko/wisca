@php
    $enrolled  = $summary['enrolled'];
    $passing   = $summary['passing'];
    $percent   = $enrolled > 0 ? round($summary['rate'] * 100, 1) : null;
    $target    = 80;
    $termLabel = $filters['termId'] ? ($term?->name ?? 'Selected term') : 'All terms';
@endphp

<x-portal-layout title="Character Development">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-02"
        title="Character development ratings"
        :meta="$session->name.' · '.$termLabel.'. Learners rated Secure (3)+ on ALL active domains ÷ enrolled. Target '.$target.'%.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('character.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

        <div>
            <x-input-label for="ch_session" value="Session" />
            <select id="ch_session" name="session_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="ch_term" value="Term" />
            <select id="ch_term" name="term_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($filters['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $filters['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('character.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    {{-- CE-02 hero --}}
    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">CE-02 · {{ $termLabel }}</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    @if ($enrolled === 0)
                        No enrolled learners found.
                    @elseif ($summary['domain_count'] === 0)
                        No active character domains configured. Ask Admin to set them up.
                    @else
                        {{ $passing }} of {{ $enrolled }} learners rated Secure+ on all {{ $summary['domain_count'] }} domains · target {{ $target }}%
                    @endif
                </p>
            </div>
            <a href="{{ route('character.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Rate a class
            </a>
        </div>
        @if ($percent !== null)
            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
                <div class="h-full rounded-full {{ $summary['rate'] >= ($target / 100) ? 'bg-emerald-400' : 'bg-amber-400' }}"
                     style="width: {{ min(100, $percent) }}%"></div>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    {{-- Active domains strip --}}
    @if ($domains->isNotEmpty())
        <div class="portal-enter mb-6 flex flex-wrap gap-2">
            @foreach ($domains as $d)
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">
                    {{ $d->name }}
                    <span class="ml-1.5 text-slate-400">≥{{ $d->labelForLevel($d->passing_level) }}</span>
                </span>
            @endforeach
        </div>
    @endif

    {{-- Per-class table --}}
    <x-portal.panel :title="'Class breakdown ('.$classSummaries->count().' classes)'" subtitle="A learner passes CE-02 only when ALL active domains are rated at or above the domain's passing level.">
        @if ($classSummaries->isEmpty())
            <p class="text-sm text-slate-500">No classes or enrolled learners found.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Enrolled</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Passing CE-02</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classSummaries as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $row['class']->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['enrolled'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['passing'] }}</td>
                                <td class="px-5 py-3 font-medium {{ $row['enrolled'] > 0 && $row['rate'] >= ($target / 100) ? 'text-emerald-700' : ($row['enrolled'] > 0 ? 'text-amber-700' : 'text-slate-400') }}">
                                    {{ $row['enrolled'] > 0 ? number_format($row['rate'] * 100, 1).'%' : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('character.create', ['class' => $row['class']->id]) }}"
                                       class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">
                                        {{ $row['passing'] > 0 ? 'Update' : 'Rate' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
