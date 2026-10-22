@php
    $f = $hodFilters;
    $sectionLabel = $hod_section_label ?? $hod_department?->name;
@endphp

<x-portal-layout title="Overview">
    <div>
        <x-portal.page-intro
            eyebrow="Head of Department"
            title="School Section Overview"
            :meta="'Supervise teachers'.($sectionLabel ? ' in '.$sectionLabel : '').'. Approve schemes and lesson plans, then use Pulse, Flags, and the Termly Drawer for the rest. '.$session->name.'.'"
        />

        <form method="GET" action="{{ route('dashboard') }}"
              class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

            @if ($allSessions->count() > 1)
                <div>
                    <x-input-label for="hf_session" value="Session" />
                    <select id="hf_session" name="session_id"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($allSessions as $s)
                            <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($allTerms->isNotEmpty())
                <div>
                    <x-input-label for="hf_term" value="Term" />
                    <select id="hf_term" name="term_id"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="0" @selected($f['termId'] === 0)>All terms</option>
                        @foreach ($allTerms as $t)
                            <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (($max_week ?? 1) > 1)
                <div>
                    <x-input-label for="hf_week" value="Week" />
                    <select id="hf_week" name="week"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="0" @selected(($f['filterWeek'] ?? 0) === 0)>All weeks</option>
                        @for ($w = 1; $w <= (int) $max_week; $w++)
                            <option value="{{ $w }}" @selected(($f['filterWeek'] ?? 0) === $w)>Week {{ $w }}</option>
                        @endfor
                    </select>
                </div>
            @endif

            @if (($inbox['teachers'] ?? collect())->isNotEmpty())
                <div>
                    <x-input-label for="hf_teacher" value="Teacher" />
                    <select id="hf_teacher" name="teacher_id"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="0" @selected($f['filterTeacherId'] === 0)>All teachers</option>
                        @foreach ($inbox['teachers'] as $t)
                            <option value="{{ $t->id }}" @selected($t->id === $f['filterTeacherId'])>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (($inbox['classes'] ?? collect())->isNotEmpty())
                <div>
                    <x-input-label for="hf_class" value="Class" />
                    <select id="hf_class" name="class_id"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="0" @selected($f['filterClassId'] === 0)>All classes</option>
                        @foreach ($inbox['classes'] as $cls)
                            <option value="{{ $cls->id }}" @selected($cls->id === $f['filterClassId'])>{{ $cls->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (($inbox['subjects'] ?? collect())->isNotEmpty())
                <div>
                    <x-input-label for="hf_subject" value="Subject" />
                    <select id="hf_subject" name="subject_id"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="0" @selected($f['filterSubjectId'] === 0)>All subjects</option>
                        @foreach ($inbox['subjects'] as $sub)
                            <option value="{{ $sub->id }}" @selected($sub->id === $f['filterSubjectId'])>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex gap-2 pt-5">
                <x-primary-button type="submit">Apply</x-primary-button>
                @if ($hodActiveFilters)
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                        Clear
                    </a>
                @endif
            </div>
        </form>

        @include('dashboard.partials.hod-action-queue')
        @include('dashboard.partials.hod-weekly-pulse')
        @include('dashboard.partials.hod-flags')
        @include('dashboard.partials.hod-termly-drawer')

        <x-portal.quicklog-fab :actions="$quicklog_actions ?? []" />
    </div>
</x-portal-layout>
