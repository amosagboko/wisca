@php
    $f = $filters;
    $percent = $summary['audited'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 95;
@endphp

<x-portal-layout title="Digital Ethics Audits">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-03"
        title="AI & digital ethics compliance"
        :meta="$session->name.' · '.$term->name.'. Audited assignments free of AI/tech violations ÷ total audited. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('ethics.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="e_session" value="Session" />
            <select id="e_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="e_term" value="Term" />
            <select id="e_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $term->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="e_type" value="Audit type" />
            <select id="e_type" name="type_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['typeId'] === 0)>All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected($type->id === $f['typeId'])>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="e_compliance" value="Result" />
            <select id="e_compliance" name="compliance" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['compliance'] === '')>All</option>
                <option value="compliant" @selected($f['compliance'] === 'compliant')>Compliant</option>
                <option value="violation" @selected($f['compliance'] === 'violation')>Violation</option>
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('ethics.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">DI-03 this term</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['compliant'] }} compliant of {{ $summary['audited'] }} audited · target {{ $target }}%</p>
            </div>
            <a href="{{ route('ethics.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Log audit
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel title="Audits ({{ $rows->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Assignment</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Learner</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Type</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Result</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Audited</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $row->assignment_title }}</td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ $row->learner?->name ?: '—' }}
                                @if ($row->learner?->schoolClass)
                                    <span class="block text-xs text-slate-400">{{ $row->learner->schoolClass->name }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->auditType?->name }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $row->free_of_violations,
                                    'bg-red-100 text-red-800' => ! $row->free_of_violations,
                                ])>{{ $row->free_of_violations ? 'Compliant' : 'Violation' }}</span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->audited_on?->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('ethics.edit', $row) }}"
                                   class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 sm:px-6 py-8 text-center text-slate-500">No digital ethics audits recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
