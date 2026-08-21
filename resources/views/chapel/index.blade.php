@php
    $f        = $filters;
    $assessed = $summary['sessions_held'];
    $roll     = $summary['roll'];
    $part     = $summary['participating'];
    $percent  = ($assessed > 0 && $roll > 0)
        ? round($summary['rate'] * 100, 1)
        : null;
    $target   = 90;
@endphp

<x-portal-layout title="Chapel & Assembly">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-01"
        title="Chapel & assembly attendance"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Present + participating ÷ school roll. Target '.$target.'%.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('chapel.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

        @if ($allSessions->count() > 1)
            <div>
                <x-input-label for="c_session" value="Session" />
                <select id="c_session" name="session_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($allSessions as $s)
                        <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($allTerms->count() > 1)
            <div>
                <x-input-label for="c_term" value="Term" />
                <select id="c_term" name="term_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['termId'] === 0)>All terms</option>
                    @foreach ($allTerms as $t)
                        <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($activityTypes->count() > 1)
            <div>
                <x-input-label for="c_type" value="Activity type" />
                <select id="c_type" name="type_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['typeId'] === 0)>All types</option>
                    @foreach ($activityTypes as $at)
                        <option value="{{ $at->id }}" @selected($at->id === $f['typeId'])>{{ $at->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <x-input-label for="c_status" value="Status" />
            <select id="c_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['filterStatus'] === '')>All statuses</option>
                <option value="scheduled" @selected($f['filterStatus'] === 'scheduled')>Scheduled</option>
                <option value="held"      @selected($f['filterStatus'] === 'held')>Held</option>
                <option value="cancelled" @selected($f['filterStatus'] === 'cancelled')>Cancelled</option>
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('chapel.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    {{-- CE-01 hero card --}}
    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">CE-01 this period</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    @if ($percent === null)
                        No held sessions with roll taken yet.
                    @else
                        {{ $part }} participating of {{ $roll }} × {{ $assessed }} session{{ $assessed !== 1 ? 's' : '' }} · target {{ $target }}%
                    @endif
                </p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Sessions held</p>
                <p class="mt-1 text-sm font-semibold">{{ $assessed }} held · {{ $summary['roll'] }} enrolled</p>
            </div>
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

    {{-- Session table --}}
    <x-portal.panel :title="'Sessions ('.$rows->count().')'" subtitle="Take roll for each held session to update CE-01.">
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-500">
                {{ $activeFilters ? 'No sessions match the current filters.' : 'No sessions scheduled for this period.' }}
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.chapel-sessions.create') }}" class="text-[#0f2d4a] hover:underline ml-1">Schedule one.</a>
                @endif
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Type / Theme</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Led by</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Roll</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Participating</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $cs = $row['session']; @endphp
                            <tr class="border-t border-slate-100 {{ $cs->status === 'cancelled' ? 'opacity-50' : '' }}">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                    {{ $cs->session_date->format('d M Y') }}
                                    <span class="block text-xs text-slate-400">{{ $cs->session_date->format('l') }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $cs->activityType?->name ?? '—' }}</p>
                                    @if ($cs->theme)
                                        <p class="text-xs text-slate-500">{{ $cs->theme }}</p>
                                    @endif
                                    @if ($cs->scripture_reference)
                                        <p class="text-xs text-slate-400 italic">{{ $cs->scripture_reference }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $cs->leader?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $row['roll_taken'] ? $row['roll'] : '—' }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $row['roll_taken'] ? $row['participating'] : '—' }}
                                </td>
                                <td class="px-5 py-3 font-medium">
                                    @if ($row['rate'] !== null)
                                        <span class="{{ $row['rate'] >= 0.9 ? 'text-emerald-700' : 'text-amber-700' }}">
                                            {{ number_format($row['rate'] * 100, 1) }}%
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $cs->status === 'held',
                                        'bg-amber-100 text-amber-800'     => $cs->status === 'scheduled',
                                        'bg-slate-100 text-slate-600'     => $cs->status === 'cancelled',
                                    ])>{{ ucfirst($cs->status) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($cs->status !== 'cancelled')
                                        <a href="{{ route('chapel.roll', $cs) }}"
                                           class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">
                                            {{ $row['roll_taken'] ? 'Update roll' : 'Take roll' }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
