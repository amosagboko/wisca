@php
    $f = $filters;
    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterRecorderId']
        || $f['dateFrom'] !== '' || $f['dateTo'] !== '' || $f['sortBy'] !== 'date_desc'
        || ($f['sessionId'] && $f['sessionId'] !== ($allSessions->first()?->id ?? 0));

    // Summary stats
    $totalDays    = $logs->count();
    $totalPresent = $logs->sum('present_count');
    $totalEnrolled= $logs->sum('enrolled_count');
    $overallRate  = $totalEnrolled > 0 ? round($totalPresent / $totalEnrolled * 100, 1) : null;

    $sortIcon = fn(string $val) => $f['sortBy'] === $val ? '●' : '○';
@endphp

<x-portal-layout title="Attendance">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-04"
        title="Learner attendance"
        :meta="'Days present ÷ instructional class-days (enrolled roll) · '.$session->name.'. Target 95%.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('attendance.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

        <div>
            <x-input-label for="f_session" value="Session" />
            <select id="f_session" name="session_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="f_term" value="Term" />
            <select id="f_term" name="term_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($isStaff && $allClasses->isNotEmpty())
            <div>
                <x-input-label for="f_class" value="Class" />
                <select id="f_class" name="class_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['filterClassId'] === 0)>All classes</option>
                    @foreach ($allClasses as $cls)
                        <option value="{{ $cls->id }}" @selected($cls->id === $f['filterClassId'])>{{ $cls->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($isStaff && $allRecorders->isNotEmpty())
            <div>
                <x-input-label for="f_recorder" value="Recorded by" />
                <select id="f_recorder" name="recorder_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['filterRecorderId'] === 0)>All teachers</option>
                    @foreach ($allRecorders as $r)
                        <option value="{{ $r->id }}" @selected($r->id === $f['filterRecorderId'])>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <x-input-label for="f_from" value="From date" />
            <x-text-input id="f_from" name="date_from" type="date" :value="$f['dateFrom']"
                          class="mt-1 block text-sm" />
        </div>

        <div>
            <x-input-label for="f_to" value="To date" />
            <x-text-input id="f_to" name="date_to" type="date" :value="$f['dateTo']"
                          class="mt-1 block text-sm" />
        </div>

        <div>
            <x-input-label for="f_sort" value="Sort" />
            <select id="f_sort" name="sort"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="date_desc" @selected($f['sortBy'] === 'date_desc')>Date ↓ newest first</option>
                <option value="date_asc"  @selected($f['sortBy'] === 'date_asc')>Date ↑ oldest first</option>
                <option value="rate_desc" @selected($f['sortBy'] === 'rate_desc')>Rate ↓ highest first</option>
                <option value="rate_asc"  @selected($f['sortBy'] === 'rate_asc')>Rate ↑ lowest first</option>
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('attendance.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Summary strip --}}
    @if ($logs->isNotEmpty())
        <div class="portal-enter mb-6 flex flex-wrap gap-4">
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Registers</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $totalDays }}</p>
                <p class="text-xs text-slate-500">class-days shown</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Present</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ number_format($totalPresent) }}</p>
                <p class="text-xs text-slate-500">of {{ number_format($totalEnrolled) }} enrolled slots</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">AE-04 rate</p>
                <p class="mt-1 text-2xl font-display font-semibold {{ $overallRate !== null && $overallRate >= 95 ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $overallRate !== null ? $overallRate.'%' : '—' }}
                </p>
                <p class="text-xs text-slate-500">target 95%</p>
            </div>
        </div>
    @endif

    @if ($canRecord)
        <div class="mb-4 flex justify-end">
            <a href="{{ route('attendance.create') }}"
               class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Take register
            </a>
        </div>
    @endif

    <x-portal.panel :title="($isStaff ? 'School attendance logs' : 'My class registers').($logs->count() ? ' ('.$logs->count().')' : '')">
        @if ($logs->isEmpty())
            <p class="text-sm text-slate-500">
                @if ($activeFilters)
                    No attendance logs match the current filters.
                @else
                    No attendance logs yet. Record enrolled vs present for each class on each instructional day.
                @endif
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            @if ($isStaff)
                                <th class="px-5 py-3 font-semibold text-slate-600">Recorded by</th>
                            @endif
                            <th class="px-5 py-3 font-semibold text-slate-600">Enrolled</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Present</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Review</th>
                            @if ($canRecord)
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php $rate = $log->attendanceRate(); @endphp
                            <tr class="border-t border-slate-100 {{ $rate < 0.95 ? 'bg-amber-50/40' : '' }}">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                    {{ $log->attendance_date->format('d M Y') }}
                                    <span class="ml-1 text-xs font-normal text-slate-400">{{ $log->attendance_date->format('D') }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->schoolClass->name }}</td>
                                @if ($isStaff)
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$log->recorder" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $log->recorder->name }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-5 py-3 text-slate-600">{{ $log->enrolled_count }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $log->present_count }}
                                    <span class="text-xs text-slate-400">({{ $log->absentCount() }} absent)</span>
                                </td>
                                <td class="px-5 py-3 font-medium {{ $rate >= 0.95 ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ number_format($rate * 100, 1) }}%
                                </td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                        'bg-slate-100 text-slate-600' => $log->isSubmitted(),
                                        'bg-emerald-100 text-emerald-800' => $log->isVerified(),
                                        'bg-red-100 text-red-800' => $log->isRejected(),
                                    ])>{{ $log->status }}</span>
                                    @if ($log->isRejected() && $log->rejection_reason)
                                        <p class="mt-1 max-w-xs text-xs text-red-700">{{ $log->rejection_reason }}</p>
                                    @endif
                                </td>
                                @if ($canRecord)
                                    <td class="px-5 py-3 text-right">
                                        @if ($log->isEditableByTeacher())
                                            <a href="{{ route('attendance.edit', $log) }}"
                                               class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Update</a>
                                        @else
                                            <span class="text-xs text-slate-400">Verified</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    @if (session('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
</x-portal-layout>
