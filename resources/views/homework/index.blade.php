@php
    $f = $filters;
    $canLog = auth()->user()->isTeacher();

    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterSubjectId']
        || $f['filterTeacherId'] || $f['search'] !== '' || $f['sortBy'] !== 'date_desc'
        || ($f['sessionId'] && $f['sessionId'] !== ($allSessions->first()?->id ?? 0));

    // Summary stats
    $totalGiven    = $logs->sum('given_count');
    $totalOnTime   = $logs->sum('completed_on_time_count');
    $overallRate   = $totalGiven > 0 ? round($totalOnTime / $totalGiven * 100, 1) : null;
@endphp

<x-portal-layout title="Homework">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-03"
        title="Homework completion"
        :meta="'On-time completions ÷ assignments given · '.$session->name.'. Target 95%.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('homework.index') }}"
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

        @if ($allClasses->isNotEmpty())
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

        @if ($allSubjects->isNotEmpty())
            <div>
                <x-input-label for="f_subject" value="Subject" />
                <select id="f_subject" name="subject_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['filterSubjectId'] === 0)>All subjects</option>
                    @foreach ($allSubjects as $sub)
                        <option value="{{ $sub->id }}" @selected($sub->id === $f['filterSubjectId'])>{{ $sub->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($isStaff && $allTeachers->isNotEmpty())
            <div>
                <x-input-label for="f_teacher" value="Teacher" />
                <select id="f_teacher" name="teacher_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['filterTeacherId'] === 0)>All teachers</option>
                    @foreach ($allTeachers as $t)
                        <option value="{{ $t->id }}" @selected($t->id === $f['filterTeacherId'])>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="flex-1 min-w-[160px]">
            <x-input-label for="f_search" value="Search assignment" />
            <x-text-input id="f_search" name="search" type="text" placeholder="Assignment title…"
                          :value="$f['search']" class="mt-1 block w-full text-sm" />
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
                <a href="{{ route('homework.index') }}"
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
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Assignments</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $logs->count() }}</p>
                <p class="text-xs text-slate-500">logs shown</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Given</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ number_format($totalGiven) }}</p>
                <p class="text-xs text-slate-500">total assignments</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">AE-03 rate</p>
                <p class="mt-1 text-2xl font-display font-semibold {{ $overallRate !== null && $overallRate >= 95 ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $overallRate !== null ? $overallRate.'%' : '—' }}
                </p>
                <p class="text-xs text-slate-500">on-time · target 95%</p>
            </div>
        </div>
    @endif

    @if ($canLog)
        <div class="mb-4 flex justify-end">
            <a href="{{ route('homework.create') }}"
               class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Log homework
            </a>
        </div>
    @endif

    <x-portal.panel :title="($isStaff ? 'Department homework logs' : 'My homework logs').($logs->count() ? ' ('.$logs->count().')' : '')">
        @if ($logs->isEmpty())
            <p class="text-sm text-slate-500">
                @if ($activeFilters)
                    No homework logs match the current filters.
                @else
                    No homework logs yet.{{ $canLog ? ' Record assignments given and how many were completed on time.' : '' }}
                @endif
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            @if ($isStaff)
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Teacher</th>
                            @endif
                            <th class="px-5 {{ $isStaff ? 'py-3' : 'sm:px-6 py-3' }} font-semibold text-slate-600">Assignment</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Given</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">On time</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Review</th>
                            @if ($canLog)
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php $rate = $log->completionRate(); @endphp
                            <tr class="border-t border-slate-100">
                                @if ($isStaff)
                                    <td class="px-5 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$log->teacher" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $log->teacher->name }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-5 {{ $isStaff ? 'py-3' : 'sm:px-6 py-3' }}">
                                    <p class="font-medium text-slate-800">{{ $log->title }}</p>
                                    <p class="text-xs text-slate-500">
                                        Given {{ $log->given_date->format('d M Y') }} · Due {{ $log->due_date->format('d M Y') }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->schoolClass->name }} · {{ $log->subject->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->given_count }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->completed_on_time_count }}</td>
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
                                @if ($canLog)
                                    <td class="px-5 py-3 text-right">
                                        @if ($log->isEditableByTeacher())
                                            <a href="{{ route('homework.edit', $log) }}"
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
