@php
    $canEnter   = auth()->user()->canEnterExamResults();
    $isStaff    = ! auth()->user()->isTeacher() || auth()->user()->isHoD();
    $percent    = $termSummary['enrolled'] > 0 ? round($termSummary['rate'] * 100, 1) : null;
    $f          = $filters;
    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterSubjectId'] || $f['sortBy'] !== ''
        || ($f['sessionId'] && $f['sessionId'] !== ($allSessions->first()?->id ?? 0));

    // Sort arrow helper
    $nextSort = fn(string $col) => match(true) {
        $f['sortBy'] === $col.'_asc'  => $col.'_desc',
        $f['sortBy'] === $col.'_desc' => '',
        default => $col.'_asc',
    };
    $sortIcon = fn(string $col) => match(true) {
        $f['sortBy'] === $col.'_asc'  => '↑',
        $f['sortBy'] === $col.'_desc' => '↓',
        default => '↕',
    };
@endphp

<x-portal-layout title="Examination Results">
    <x-portal.page-intro
        eyebrow="Termly Broad Sheet · AE-02"
        title="School-wide examination pass rate"
        :meta="($term?->name ?? 'All terms').' · '.$session->name.'. Passed (≥'.(int) $termSummary['pass_mark'].'%) ÷ enrolled learners. Missing scores count as not passed.'"
    />

    {{-- AE-02 hero --}}
    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">AE-02 {{ $term?->name ?? 'this session' }}</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    @if ($termSummary['enrolled'] === 0)
                        Add learners to the class roll before entering marks.
                    @else
                        {{ $termSummary['passed'] }} passed of {{ $termSummary['enrolled'] }} enrolled sittings · target 90%
                    @endif
                </p>
            </div>
            @if ($canEnter && $term)
                <a href="{{ route('exam-results.edit') }}"
                   class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                    Open marksheet
                </a>
            @endif
        </div>
        @if ($percent !== null)
            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
                <div class="h-full rounded-full {{ $termSummary['rate'] >= 0.9 ? 'bg-emerald-400' : 'bg-amber-400' }}"
                     style="width: {{ min(100, $percent) }}%"></div>
            </div>
        @endif
    </section>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('exam-results.index') }}"
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
                <option value="0" @selected($f['termId'] === 0)>Current term</option>
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

        <div>
            <x-input-label for="f_sort" value="Sort by rate" />
            <select id="f_sort" name="sort"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""          @selected($f['sortBy'] === '')>Default</option>
                <option value="rate_desc" @selected($f['sortBy'] === 'rate_desc')>Rate ↓ highest first</option>
                <option value="rate_asc"  @selected($f['sortBy'] === 'rate_asc')>Rate ↑ lowest first</option>
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('exam-results.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Sittings table --}}
    <x-portal.panel
        :title="'Class / subject sittings'.($sittings->count() ? ' ('.$sittings->count().')' : '')"
        subtitle="Each sitting uses the enrolled roll as the denominator. Missing scores count as not passed.">
        @if ($sittings->isEmpty())
            <p class="text-sm text-slate-500">
                @if ($activeFilters)
                    No sittings match the current filters.
                @else
                    No active class/subject assignments this session.
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
                            <th class="px-5 {{ $isStaff ? 'py-3' : 'sm:px-6 py-3' }} font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Enrolled</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Recorded</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Passed</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">
                                <a href="{{ route('exam-results.index', array_merge(request()->query(), ['sort' => $nextSort('rate')])) }}"
                                   class="inline-flex items-center gap-1 hover:text-[#0f2d4a]">
                                    Rate <span class="text-slate-400">{{ $sortIcon('rate') }}</span>
                                </a>
                            </th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Review</th>
                            @if ($canEnter && $term)
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sittings as $row)
                            @php $assignment = $row['assignment']; @endphp
                            <tr class="border-t border-slate-100">
                                @if ($isStaff)
                                    <td class="px-5 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$assignment->teacher" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $assignment->teacher->name }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-5 {{ $isStaff ? 'py-3' : 'sm:px-6 py-3' }} font-medium text-slate-800">
                                    {{ $assignment->schoolClass->name }} · {{ $assignment->subject->name }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['enrolled'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['recorded'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['passed'] }}</td>
                                <td class="px-5 py-3 font-medium
                                    {{ $row['enrolled'] > 0 && $row['rate'] >= 0.9 ? 'text-emerald-700' : ($row['enrolled'] > 0 ? 'text-amber-700' : 'text-slate-400') }}">
                                    {{ $row['enrolled'] > 0 ? number_format($row['rate'] * 100, 1).'%' : '—' }}
                                </td>
                                <td class="px-5 py-3 capitalize text-slate-600">{{ $row['review_status'] ?? 'incomplete' }}</td>
                                @if ($canEnter && $term)
                                    <td class="px-5 py-3 text-right">
                                        @if (! empty($row['locked']))
                                            <span class="text-xs text-slate-400">Verified</span>
                                        @else
                                            <a href="{{ route('exam-results.edit', ['assignment' => $assignment->school_class_id.':'.$assignment->subject_id]) }}"
                                               class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">
                                                {{ ($row['review_status'] ?? '') === 'rejected' ? 'Revise' : ($row['recorded'] > 0 ? 'Update' : 'Enter') }}
                                            </a>
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
</x-portal-layout>
