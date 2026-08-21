@php
    $percent = $summary['total_population'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 90;
@endphp

<x-portal-layout title="LMS Adoption">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-01"
        title="Digital portal and LMS adoption"
        :meta="$session->name.' · Week of '.\Carbon\Carbon::parse($weekStartDate)->format('d M Y').'. Active weekly LMS users ÷ total staff + students. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('lms.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="l_session" value="Session" />
            <select id="l_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="l_week" value="Week start" />
            <input id="l_week" type="date" name="week_start" value="{{ $weekStartDate }}"
                   class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>
        <div class="pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">DI-01 this week</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['active_users'] }} active users of {{ $summary['total_population'] }} total · target {{ $target }}%</p>
                <p class="mt-2 text-xs text-white/60">Staff: {{ $summary['active_staff'] }}/{{ $summary['staff_total'] }} · Learners: {{ $summary['active_learners'] }}/{{ $summary['learner_total'] }}</p>
            </div>
            <a href="{{ route('lms.create', ['week_start' => $weekStartDate]) }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Record weekly logs
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel title="Weekly usage rows ({{ $rows->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">User</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Type</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Logins</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Activities</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                {{ $row->actor_type === 'staff' ? $row->user?->name : $row->learner?->name }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ ucfirst($row->actor_type) }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->login_count }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->activity_count }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $row->is_active_weekly,
                                    'bg-slate-100 text-slate-600' => ! $row->is_active_weekly,
                                ])>{{ $row->is_active_weekly ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No LMS usage logs for this week yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
