@php
    $percent = $attendance_week['enrolled'] > 0 ? round($attendance_week['rate'] * 100, 1) : null;
@endphp

<x-portal-layout title="Attendance Week">
    {{-- Session switcher --}}
    @if ($allSessions->count() > 1)
        <form method="GET" action="{{ route('dashboard') }}" class="mb-6 flex items-center gap-3">
            <x-input-label for="off_session" value="Session" class="shrink-0" />
            <select id="off_session" name="session_id" onchange="this.form.submit()"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>
    @endif

    <x-portal.page-intro
        eyebrow="Admin Officer · AE-04"
        title="Attendance Week"
        :meta="'Week '.$week_number.($term ? ' of '.$term->name : '').' · '.$session->name.'. Morning roll for every class feeds Learner Attendance Rate.'"
    />

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">This week’s attendance</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    @if ($percent === null)
                        No class registers for this instructional week yet.
                    @else
                        {{ $attendance_week['present'] }} present of {{ $attendance_week['enrolled'] }} enrolled learner-days
                    @endif
                </p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">AE-04 status</p>
                <p class="mt-1 text-sm font-semibold">
                    @if ($percent === null)
                        Awaiting registers
                    @elseif ($attendance_week['rate'] >= 0.95)
                        On track for 95%
                    @else
                        Below 95% — needs attention
                    @endif
                </p>
            </div>
        </div>
        @if ($percent !== null)
            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
                <div class="h-full rounded-full {{ $attendance_week['rate'] >= 0.95 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ min(100, $percent) }}%"></div>
            </div>
        @endif
    </section>

    @php
        $officerInbox = $inbox ?? [];
        $officerTotal = (int) ($officerInbox['total'] ?? $missing_classes->count());
    @endphp
    <x-portal.work-inbox
        title="Classes still to take roll"
        :subtitle="'Suggested date '.$suggested_date_label.'. One register per class per day'.($officerTotal ? ', grouped by class.' : '.')"
        :items="$officerInbox['items'] ?? collect()"
        :grouped="$officerInbox['groups'] ?? null"
        :types="$officerInbox['types'] ?? null"
        :paginator="$officerInbox['paginator'] ?? null"
        :active-type="$officerInbox['active_type'] ?? ''"
        :total="$officerTotal"
        :filtered-total="$officerInbox['filtered_total'] ?? $officerTotal"
        empty="Every class has a register for this date."
    />

    <x-portal.panel title="This week’s registers" subtitle="Present counts against enrolled roll. Update a log if the morning figure was entered early.">
        @if ($attendance_logs->isEmpty())
            <p class="text-sm text-slate-500">No registers this week. Start with a class chip above.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Recorded by</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Present / Enrolled</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attendance_logs as $log)
                            @php $rate = $log->attendanceRate(); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $log->attendance_date->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->schoolClass->name }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <x-user-avatar :user="$log->recorder" size="xs" />
                                        <span>{{ $log->recorder->name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->present_count }} / {{ $log->enrolled_count }}</td>
                                <td class="px-5 py-3 font-medium {{ $rate >= 0.95 ? 'text-emerald-700' : 'text-amber-700' }}">{{ number_format($rate * 100, 1) }}%</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('attendance.edit', $log) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Update</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
