@php
    $f = $filters;
    $hours = $summary['verified_hours'];
    $roll = $summary['roll'];
    $average = $summary['hours_per_learner'];
    $target = 10;
@endphp

<x-portal-layout title="Community Service">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-03"
        title="Community service hours"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Total verified service hours ÷ student roll. Target '.$target.' hours per learner.'"
    />

    <form method="GET" action="{{ route('service.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="svc_session" value="Session" />
            <select id="svc_session" name="session_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="svc_term" value="Term" />
            <select id="svc_term" name="term_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="svc_type" value="Activity type" />
            <select id="svc_type" name="type_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['typeId'] === 0)>All types</option>
                @foreach ($activityTypes as $type)
                    <option value="{{ $type->id }}" @selected($type->id === $f['typeId'])>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="svc_status" value="Status" />
            <select id="svc_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['filterStatus'] === '')>All statuses</option>
                <option value="draft" @selected($f['filterStatus'] === 'draft')>Draft</option>
                <option value="submitted" @selected($f['filterStatus'] === 'submitted')>Submitted</option>
                <option value="verified" @selected($f['filterStatus'] === 'verified')>Verified</option>
                <option value="rejected" @selected($f['filterStatus'] === 'rejected')>Rejected</option>
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('service.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">CE-03 this period</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ number_format($average, 2) }} hrs</p>
                <p class="mt-1 text-sm text-white/75">
                    {{ number_format($hours, 2) }} verified hour{{ $hours == 1.0 ? '' : 's' }} across {{ $summary['verified_logs'] }} log{{ $summary['verified_logs'] === 1 ? '' : 's' }} · roll {{ $roll }} · target {{ $target }} hrs
                </p>
            </div>
            <a href="{{ route('service.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Log service hours
            </a>
        </div>
        <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
            <div class="h-full rounded-full {{ $average >= $target ? 'bg-emerald-400' : 'bg-amber-400' }}"
                 style="width: {{ min(100, $target > 0 ? ($average / $target) * 100 : 0) }}%"></div>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($typeTotals->isNotEmpty())
        <div class="portal-enter mb-6 flex flex-wrap gap-2">
            @foreach ($typeTotals as $row)
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">
                    {{ $row['type']->name }}
                    <span class="ml-1.5 text-slate-400">{{ number_format($row['verified_hours'], 2) }} hrs</span>
                </span>
            @endforeach
        </div>
    @endif

    <x-portal.panel :title="'Service logs ('.$rows->count().')'" subtitle="Only verified service hours contribute to CE-03.">
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-500">
                {{ $activeFilters ? 'No service logs match the current filters.' : 'No service logs yet for this period.' }}
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Title / Type</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Participants</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Verified hours</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Submitted by</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $log)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                    {{ $log->service_date->format('d M Y') }}
                                    <span class="block text-xs text-slate-400">{{ $log->term?->name ?? 'No term' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $log->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $log->activityType?->name ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->participant_count }}</td>
                                <td class="px-5 py-3 font-medium {{ $log->isVerified() ? 'text-emerald-700' : 'text-slate-700' }}">
                                    {{ number_format((float) $log->verified_hours, 2) }} hrs
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $log->submitter?->name ?? '—' }}
                                    @if ($log->verified_by)
                                        <span class="block text-xs text-slate-400">Verified by {{ $log->verifier?->name }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-slate-100 text-slate-600' => $log->status === 'draft',
                                        'bg-amber-100 text-amber-800' => $log->status === 'submitted',
                                        'bg-emerald-100 text-emerald-800' => $log->status === 'verified',
                                        'bg-red-100 text-red-700' => $log->status === 'rejected',
                                    ])>{{ ucfirst($log->status) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('service.edit', $log) }}"
                                           class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                        @if ($canVerify && $log->status === 'submitted')
                                            <form method="POST" action="{{ route('service.verify', $log) }}" class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="text-xs font-semibold uppercase tracking-wide text-emerald-700 hover:underline">Verify</button>
                                            </form>
                                            <form method="POST" action="{{ route('service.reject', $log) }}" class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Reject</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
