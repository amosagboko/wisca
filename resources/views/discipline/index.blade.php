@php
    $f = $filters;
    $percent = $summary['logged'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 90;
@endphp

<x-portal-layout title="Restorative Discipline">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-04"
        title="Restorative discipline register"
        :meta="$session->name.' · '.$term->name.'. Incidents with completed restorative agreement ÷ incidents logged. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('discipline.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="d_session" value="Session" />
            <select id="d_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="d_term" value="Term" />
            <select id="d_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $term->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="d_type" value="Incident type" />
            <select id="d_type" name="type_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['typeId'] === 0)>All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected($type->id === $f['typeId'])>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="d_status" value="Case status" />
            <select id="d_status" name="status" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['status'] === '')>All</option>
                <option value="open" @selected($f['status'] === 'open')>Open</option>
                <option value="in_review" @selected($f['status'] === 'in_review')>In review</option>
                <option value="resolved" @selected($f['status'] === 'resolved')>Resolved</option>
            </select>
        </div>

        <div>
            <x-input-label for="d_restorative" value="Restorative status" />
            <select id="d_restorative" name="restorative_status" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['restorativeStatus'] === '')>All</option>
                <option value="not_required" @selected($f['restorativeStatus'] === 'not_required')>Not required</option>
                <option value="pending" @selected($f['restorativeStatus'] === 'pending')>Pending</option>
                <option value="in_progress" @selected($f['restorativeStatus'] === 'in_progress')>In progress</option>
                <option value="completed" @selected($f['restorativeStatus'] === 'completed')>Completed</option>
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('discipline.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">CE-04 this month</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    {{ $summary['completed'] }} completed agreements of {{ $summary['logged'] }} incidents · target {{ $target }}%
                </p>
            </div>
            <a href="{{ route('discipline.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Log incident
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

    <x-portal.panel :title="'Incidents ('.$rows->count().')'" subtitle="A case counts in CE-04 numerator only when restorative status is Completed with agreement evidence.">
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-500">No incidents found for the selected filters.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Incident</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Learner</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Severity</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Case</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Restorative</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $incident)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 text-slate-700">{{ $incident->incident_date->format('d M Y') }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $incident->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $incident->type?->name ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $incident->learner?->name ?? 'General / multiple learners' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ ucfirst($incident->severity) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', ucfirst($incident->status)) }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-slate-100 text-slate-600' => $incident->restorative_status === 'not_required',
                                        'bg-amber-100 text-amber-800' => in_array($incident->restorative_status, ['pending', 'in_progress'], true),
                                        'bg-emerald-100 text-emerald-800' => $incident->restorative_status === 'completed',
                                    ])>{{ str_replace('_', ' ', ucfirst($incident->restorative_status)) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('discipline.edit', $incident) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Update</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
