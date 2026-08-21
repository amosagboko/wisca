@php
    $f = $filters;
    $percent = $summary['reported'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 100;
@endphp

<x-portal-layout title="Anti-Bullying Cases">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-05"
        title="Anti-bullying case register"
        :meta="$session->name.' · '.$term->name.'. Bullying cases closed with safety plan ÷ total reported. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('bullying.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="b_session" value="Session" />
            <select id="b_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="b_term" value="Term" />
            <select id="b_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $term->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="b_type" value="Case type" />
            <select id="b_type" name="type_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['typeId'] === 0)>All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected($type->id === $f['typeId'])>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="b_status" value="Status" />
            <select id="b_status" name="status" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['status'] === '')>All</option>
                <option value="reported" @selected($f['status'] === 'reported')>Reported</option>
                <option value="investigating" @selected($f['status'] === 'investigating')>Investigating</option>
                <option value="closed" @selected($f['status'] === 'closed')>Closed</option>
            </select>
        </div>
        <div>
            <x-input-label for="b_safety" value="Safety plan" />
            <select id="b_safety" name="safety" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['safety'] === '')>All</option>
                <option value="with_plan" @selected($f['safety'] === 'with_plan')>With plan</option>
                <option value="without_plan" @selected($f['safety'] === 'without_plan')>Without plan</option>
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('bullying.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">CE-05 this month</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['closed_with_plan'] }} closed with plan of {{ $summary['reported'] }} reported · target {{ $target }}%</p>
            </div>
            <a href="{{ route('bullying.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Log case
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel :title="'Cases ('.$rows->count().')'" subtitle="A case counts in CE-05 only when it is closed and a safety plan has been created.">
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-500">No bullying cases found for the selected filters.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Case</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Target learner</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Severity</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Safety plan</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $case)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 text-slate-700">{{ $case->reported_on->format('d M Y') }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $case->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $case->type?->name ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $case->targetLearner?->name ?? 'Not specified' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ ucfirst($case->severity) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ ucfirst($case->status) }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $case->safety_plan_created,
                                        'bg-amber-100 text-amber-800' => ! $case->safety_plan_created,
                                    ])>{{ $case->safety_plan_created ? 'Created' : 'Pending' }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('bullying.edit', $case) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Update</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
