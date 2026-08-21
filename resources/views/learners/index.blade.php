@php
    $activeFilters = $filters['termId'] || $filters['search'] !== '' || $filters['status'] !== 'enrolled'
        || ($filters['sessionId'] && $filters['sessionId'] !== ($allSessions->first()?->id ?? 0));
@endphp

<x-portal-layout title="Class Roll">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-02"
        title="Class roll"
        :meta="($session?->name ?? 'All sessions').($term ? ' · '.$term->name : '').'. Named learners are the AE-02 denominator. Missing exam scores still count as not passed against this enrolled roll.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('learners.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

        {{-- Session --}}
        <div>
            <x-input-label for="f_session" value="Session" />
            <select id="f_session" name="session_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $filters['sessionId'])>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Term --}}
        <div>
            <x-input-label for="f_term" value="Term" />
            <select id="f_term" name="term_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($filters['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $filters['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Class --}}
        <div>
            <x-input-label for="f_class" value="Class" />
            <select id="f_class" name="class"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($classes as $cls)
                    <option value="{{ $cls->id }}" @selected($cls->id === (int) $filters['class'])>{{ $cls->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div>
            <x-input-label for="f_status" value="Status" />
            <select id="f_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="enrolled" @selected($filters['status'] === 'enrolled')>Enrolled</option>
                <option value="withdrawn" @selected($filters['status'] === 'withdrawn')>Withdrawn</option>
                <option value="all" @selected($filters['status'] === 'all')>All</option>
            </select>
        </div>

        {{-- Search --}}
        <div class="flex-1 min-w-[160px]">
            <x-input-label for="f_search" value="Search" />
            <x-text-input id="f_search" name="search" type="text" placeholder="Name or admission no."
                          :value="$filters['search']" class="mt-1 block w-full text-sm" />
        </div>

        {{-- Actions --}}
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('learners.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Add learner button (outside filter form) --}}
    @if ($canManage)
        <div class="mb-4 flex justify-end">
            <a href="{{ route('learners.create') }}"
               class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Add learner
            </a>
        </div>
    @endif

    <x-portal.panel :title="'Enrolled learners'.($learners->count() ? ' ('.$learners->count().')' : '')">
        @if ($classes->isEmpty())
            <p class="text-sm text-slate-500">No classes are available for your account this session.</p>
        @elseif ($learners->isEmpty())
            <p class="text-sm text-slate-500">
                No learners match the current filters.
                {{ $canManage ? ' Add names before teachers enter the termly broad sheet.' : '' }}
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Admission no.</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Gender</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            @if ($canManage)
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($learners as $learner)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $learner->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $learner->admission_no ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $learner->schoolClass->name }}</td>
                                <td class="px-5 py-3 capitalize text-slate-600">{{ $learner->gender ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                        {{ $learner->status === 'enrolled' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ ucfirst($learner->status) }}
                                    </span>
                                </td>
                                @if ($canManage)
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('learners.edit', $learner) }}"
                                           class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
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
