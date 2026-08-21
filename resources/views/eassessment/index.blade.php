@php
    $percent = $summary['subjects_offered'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 80;
@endphp

<x-portal-layout title="E-Assessment Usage">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-05"
        title="E-portfolio & digital assessment usage"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Subjects using e-assessment or e-portfolio ÷ total subjects offered. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('eassessment.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="ea_session" value="Session" />
            <select id="ea_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="ea_term" value="Term" />
            <select id="ea_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('eassessment.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">DI-05 this term</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['utilizing'] }} utilizing of {{ $summary['subjects_offered'] }} subjects · target {{ $target }}%</p>
                <p class="mt-2 text-xs text-white/60">E-assessment: {{ $summary['e_assessment'] }} · E-portfolio: {{ $summary['e_portfolio'] }}</p>
            </div>
            <a href="{{ route('eassessment.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Record usage
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel title="Subjects ({{ $rows->count() }})" subtitle="A subject counts toward DI-05 when it uses e-assessment and/or e-portfolio tools.">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Subject</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">E-assessment</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">E-portfolio</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Tool</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $record = $row['record']; @endphp
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                {{ $row['subject']->name }}
                                @if ($row['subject']->code)
                                    <span class="block text-xs text-slate-400">{{ $row['subject']->code }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $record?->uses_e_assessment ? 'Yes' : 'No' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $record?->uses_e_portfolio ? 'Yes' : 'No' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $record?->primary_tool ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $record?->countsForKpi(),
                                    'bg-slate-100 text-slate-600' => ! $record?->countsForKpi(),
                                ])>{{ $record?->countsForKpi() ? 'Utilizing' : 'Not using' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No active subjects found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
