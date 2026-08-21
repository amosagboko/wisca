@php
    $f = $filters;
    $isStaff = $canConduct;
    $activeFilters = $f['termId'] || $f['filterClass'] || $f['filterTeacher']
        || $f['filterStatus'] !== '' || $f['filterOutcome'] !== '' || $f['search'] !== ''
        || ($f['sessionId'] && $f['sessionId'] !== ($allSessions->first()?->id ?? 0));

    $completed    = $observations->filter->isCompleted();
    $effective    = $completed->filter->isEffective();
    $effectiveRate = $completed->count() > 0 ? round($effective->count() / $completed->count() * 100, 1) : null;
@endphp

<x-portal-layout title="Observations">
    <x-portal.page-intro
        eyebrow="Appendix B · AE-06"
        title="Lesson observations"
        :meta="'Lessons scored Secure (3) or better on 12 standards ÷ lessons observed · '.$session->name.'. Target 90%.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('observations.index') }}"
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
                    <option value="0" @selected($f['filterClass'] === 0)>All classes</option>
                    @foreach ($allClasses as $cls)
                        <option value="{{ $cls->id }}" @selected($cls->id === $f['filterClass'])>{{ $cls->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($isStaff && $allTeachers->isNotEmpty())
            <div>
                <x-input-label for="f_teacher" value="Teacher" />
                <select id="f_teacher" name="teacher_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['filterTeacher'] === 0)>All teachers</option>
                    @foreach ($allTeachers as $t)
                        <option value="{{ $t->id }}" @selected($t->id === $f['filterTeacher'])>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <x-input-label for="f_status" value="Status" />
            <select id="f_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""                 @selected($f['filterStatus'] === '')>All statuses</option>
                <option value="completed"        @selected($f['filterStatus'] === 'completed')>Completed</option>
                <option value="follow_up_required" @selected($f['filterStatus'] === 'follow_up_required')>Follow-up required</option>
                <option value="scheduled"        @selected($f['filterStatus'] === 'scheduled')>Scheduled</option>
            </select>
        </div>

        <div>
            <x-input-label for="f_outcome" value="Outcome" />
            <select id="f_outcome" name="outcome"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""           @selected($f['filterOutcome'] === '')>Any outcome</option>
                <option value="effective"  @selected($f['filterOutcome'] === 'effective')>Effective (≥3)</option>
                <option value="needs_work" @selected($f['filterOutcome'] === 'needs_work')>Needs work (&lt;3)</option>
            </select>
        </div>

        @if ($isStaff)
            <div class="flex-1 min-w-[160px]">
                <x-input-label for="f_search" value="Search teacher" />
                <x-text-input id="f_search" name="search" type="text" placeholder="Teacher name…"
                              :value="$f['search']" class="mt-1 block w-full text-sm" />
            </div>
        @endif

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('observations.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Summary strip --}}
    @if ($observations->isNotEmpty())
        <div class="portal-enter mb-6 flex flex-wrap gap-4">
            <div class="flex-1 min-w-[140px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Showing</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $observations->count() }}</p>
                <p class="text-xs text-slate-500">observation{{ $observations->count() === 1 ? '' : 's' }}</p>
            </div>
            <div class="flex-1 min-w-[140px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Completed</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $completed->count() }}</p>
                <p class="text-xs text-slate-500">scored observations</p>
            </div>
            <div class="flex-1 min-w-[140px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">AE-06 rate</p>
                <p class="mt-1 text-2xl font-display font-semibold {{ $effectiveRate !== null && $effectiveRate >= 90 ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $effectiveRate !== null ? $effectiveRate.'%' : '—' }}
                </p>
                <p class="text-xs text-slate-500">effective (≥3) · target 90%</p>
            </div>
        </div>
    @endif

    @if ($canConduct)
        <div class="mb-4 flex justify-end">
            <a href="{{ route('observations.create') }}"
               class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Record observation
            </a>
        </div>
    @endif

    <x-portal.panel :title="($isStaff ? 'Department observations' : 'My lesson observations').($observations->count() ? ' ('.$observations->count().')' : '')">
        @if ($observations->isEmpty())
            <p class="text-sm text-slate-500">
                @if ($activeFilters)
                    No observations match the current filters.
                @else
                    No observations this session yet.{{ $canConduct ? ' Record a visit from a scheduled observation.' : '' }}
                @endif
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            @if ($isStaff)
                                <th class="px-5 py-3 font-semibold text-slate-600">Teacher</th>
                            @endif
                            <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Score</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($observations as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                    {{ $row->observation_date->format('d M Y') }}
                                </td>
                                @if ($isStaff)
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$row->teacher" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $row->teacher->name }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $row->schoolClass->name }} · {{ $row->subject->name }}
                                </td>
                                <td class="px-5 py-3 font-medium
                                    {{ $row->isCompleted() ? ($row->isEffective() ? 'text-emerald-700' : 'text-amber-700') : 'text-slate-400' }}">
                                    {{ $row->isCompleted() ? $row->scoreLabel() : '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $row->status === 'completed',
                                        'bg-amber-100 text-amber-800'     => $row->status === 'follow_up_required',
                                        'bg-slate-100 text-slate-700'     => $row->status === 'scheduled',
                                    ])>{{ ucwords(str_replace('_', ' ', $row->status)) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('observations.show', $row) }}"
                                       class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">View</a>
                                    @if ($canConduct)
                                        <a href="{{ route('observations.edit', $row) }}"
                                           class="ml-3 text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">
                                            {{ $row->isCompleted() ? 'Revise' : 'Score' }}
                                        </a>
                                    @endif
                                </td>
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
