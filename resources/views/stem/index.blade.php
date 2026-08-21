@php
    $f = $filters;
    $percent = $summary['enrolled'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 85;
@endphp

<x-portal-layout title="STEM Projects">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-02"
        title="Coding & STEM project completion"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Students completing approved STEM projects ÷ total enrolled. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('stem.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="st_session" value="Session" />
            <select id="st_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="st_term" value="Term" />
            <select id="st_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="st_type" value="Project type" />
            <select id="st_type" name="type_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['typeId'] === 0)>All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected($type->id === $f['typeId'])>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="st_status" value="Status" />
            <select id="st_status" name="status" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['status'] === '')>All</option>
                <option value="completed" @selected($f['status'] === 'completed')>Completed</option>
                <option value="in_progress" @selected($f['status'] === 'in_progress')>In progress</option>
                <option value="not_started" @selected($f['status'] === 'not_started')>Not started</option>
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('stem.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">DI-02 this term</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['completed'] }} completed of {{ $summary['enrolled'] }} enrolled · target {{ $target }}%</p>
            </div>
            <a href="{{ route('stem.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Record completions
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($classSummaries->isNotEmpty())
        <x-portal.panel title="By class" class="mb-6">
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Completed</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Enrolled</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classSummaries as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $row['class']->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['completed'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['enrolled'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ round($row['rate'] * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-portal.panel>
    @endif

    <x-portal.panel title="Completions ({{ $rows->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Project</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Score</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Completed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <p class="font-medium text-slate-800">{{ $row->learner?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $row->learner?->schoolClass?->name }}</p>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->projectType?->name }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $row->status === 'completed',
                                    'bg-amber-100 text-amber-800' => $row->status === 'in_progress',
                                    'bg-slate-100 text-slate-600' => $row->status === 'not_started',
                                ])>{{ str_replace('_', ' ', ucfirst($row->status)) }}</span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->score !== null ? $row->score : '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->completed_on?->format('d M Y') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No STEM project completions recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
