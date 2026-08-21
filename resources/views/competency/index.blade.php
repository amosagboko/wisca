@php
    $percent = $summary['staff_total'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 85;
@endphp

<x-portal-layout title="Staff Digital Competency">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-04"
        title="Staff digital competency mastery"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Staff at Level 3+ across all active matrix areas ÷ total staff. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('competency.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="c_session" value="Session" />
            <select id="c_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="c_term" value="Term" />
            <select id="c_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('competency.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">DI-04 this term</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['proficient'] }} proficient of {{ $summary['staff_total'] }} staff · target {{ $target }}%</p>
                <p class="mt-2 text-xs text-white/60">{{ $areas->count() }} active matrix areas</p>
            </div>
            <a href="{{ route('competency.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Record ratings
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel title="Staff proficiency ({{ $marksheet->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Staff</th>
                        @foreach ($areas as $area)
                            <th class="px-5 py-3 font-semibold text-slate-600">{{ $area->code ?: $area->name }}</th>
                        @endforeach
                        <th class="px-5 py-3 font-semibold text-slate-600">Result</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($marksheet as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $row['staff']->name }}</td>
                            @foreach ($areas as $area)
                                @php $rating = $row['ratings']->get($area->id); @endphp
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $rating ? 'L'.$rating->level : '—' }}
                                </td>
                            @endforeach
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $row['proficient'],
                                    'bg-amber-100 text-amber-800' => ! $row['proficient'] && $row['all_rated'],
                                    'bg-slate-100 text-slate-600' => ! $row['all_rated'],
                                ])>
                                    {{ $row['proficient'] ? 'Level 3+' : ($row['all_rated'] ? 'Below target' : 'Incomplete') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $areas->count() + 2 }}" class="px-5 sm:px-6 py-8 text-center text-slate-500">No active staff found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
