@php
    $f = $hodFilters;
@endphp

<x-portal-layout title="Verification Queue">
    <div>
        <x-portal.page-intro
            eyebrow="Head of Department"
            title="Coverage Verification"
            :meta="'Approve workbook evidence before AE-01 updates · session '.$session->name.'.'"
        />

        {{-- Filter bar --}}
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

            @if ($allTerms->count() > 1)
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

            @if ($inbox['teachers']->isNotEmpty())
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

            @if ($inbox['classes']->isNotEmpty())
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

            @if ($inbox['subjects']->isNotEmpty())
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

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pending coverage</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">{{ $pending->count() }}</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pending plans</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">{{ $pending_plans->count() }}</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Teachers in queue</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">{{ $inbox['teacher_count'] }}</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Red-flag schemes</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">{{ $red_flags->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">Coverage below 95%</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Homework this week</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">
                    {{ $homework_week['given'] > 0 ? number_format($homework_week['rate'] * 100, 1).'%' : '—' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">AE-03 · target 95%</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Attendance this week</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">
                    {{ $attendance_week['enrolled'] > 0 ? number_format($attendance_week['rate'] * 100, 1).'%' : '—' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">AE-04 · target 95%</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Observations this term</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">
                    {{ $observation_term['observed'] > 0 ? number_format($observation_term['rate'] * 100, 1).'%' : '—' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">AE-06 · Secure+ · target 90%</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Exam pass rate</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">
                    {{ $exam_term['enrolled'] > 0 ? number_format($exam_term['rate'] * 100, 1).'%' : '—' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">AE-02 · ≥{{ (int) $exam_term['pass_mark'] }}% · target 90%</p>
            </div>
            <div class="portal-enter rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">At-risk with a plan</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">
                    {{ $at_risk['identified'] > 0 ? number_format($at_risk['rate'] * 100, 1).'%' : '—' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">AE-07 · active Tier 2/3 · target 100%</p>
            </div>
        </div>

        <x-portal.panel :title="'Pending lesson plans'.($pending_plans->count() ? ' ('.$pending_plans->count().')' : '')" subtitle="Approve before coverage can be logged.">
            @if ($pending_plans->isEmpty())
                <p class="text-sm text-slate-500">
                    {{ $hodActiveFilters ? 'No lesson plans match the current filters.' : 'No lesson plans awaiting approval.' }}
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($pending_plans as $plan)
                        @include('dashboard.partials.hod-plan-card', ['plan' => $plan])
                    @endforeach
                </div>
            @endif
        </x-portal.panel>

        <x-portal.panel class="mt-6" :title="'Pending coverage verifications'.($pending->count() ? ' ('.$pending->count().')' : '')" subtitle="Differentiate teachers by avatar and name; filter to a class when the queue is long.">
            @if ($pending->isEmpty())
                <p class="text-sm text-slate-500">
                    {{ $hodActiveFilters ? 'No coverage logs match the current filters.' : 'No pending verifications. All caught up.' }}
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($pending as $log)
                        @include('dashboard.partials.hod-coverage-card', ['log' => $log])
                    @endforeach
                </div>
            @endif
        </x-portal.panel>

        <x-portal.panel class="mt-6" title="Department coverage" subtitle="Teachers below 95% are flagged for priority observation (AE-06).">
            @if ($coverage_rows->isEmpty())
                <p class="text-sm text-slate-500">No active schemes of work for this session.</p>
            @else
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Teacher</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Subject</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Covered</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Flag</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($coverage_rows as $row)
                                <tr class="border-t border-slate-100 {{ $row['flagged'] ? 'bg-amber-50/70' : '' }}">
                                    <td class="px-5 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            @if ($row['teacher'])
                                                <x-user-avatar :user="$row['teacher']" size="xs" />
                                            @endif
                                            <span class="font-medium text-slate-800">{{ $row['teacher']?->name ?? 'Unassigned' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">{{ $row['scheme']->schoolClass->name }}</td>
                                    <td class="px-5 py-3">{{ $row['scheme']->subject->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row['covered'] }} / {{ $row['total'] }}</td>
                                    <td class="px-5 py-3 font-medium">{{ number_format($row['rate'] * 100, 1) }}%</td>
                                    <td class="px-5 py-3">
                                        @if ($row['flagged'])
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Priority observation</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">On track</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($row['teacher'])
                                            <a href="{{ route('observations.create', ['teacher' => $row['teacher_id'], 'class' => $row['class_id'], 'subject' => $row['subject_id']]) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">
                                                {{ $row['flagged'] ? 'Observe now' : 'Observe' }}
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

        <x-portal.panel class="mt-6" title="Homework this week" subtitle="Class-level given vs on-time counts feeding AE-03. Teachers update logs after marking.">
            @if ($homework_logs->isEmpty())
                <p class="text-sm text-slate-500">No homework logged for this instructional week.</p>
            @else
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Teacher</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Assignment</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Given</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">On time</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($homework_logs as $hw)
                                @php $hwRate = $hw->completionRate(); @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$hw->teacher" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $hw->teacher->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-medium text-slate-800">{{ $hw->title }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $hw->schoolClass->name }} · {{ $hw->subject->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $hw->given_count }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $hw->completed_on_time_count }}</td>
                                    <td class="px-5 py-3 font-medium {{ $hwRate >= 0.95 ? 'text-emerald-700' : 'text-amber-700' }}">{{ number_format($hwRate * 100, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-portal.panel>

        <x-portal.panel class="mt-6" title="Attendance this week" subtitle="Class registers feeding AE-04. Present counts against enrolled roll for each instructional day.">
            @if ($attendance_logs->isEmpty())
                <p class="text-sm text-slate-500">No attendance logged for this instructional week.</p>
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendance_logs as $att)
                                @php $attRate = $att->attendanceRate(); @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $att->attendance_date->format('d M Y') }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $att->schoolClass->name }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$att->recorder" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $att->recorder->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $att->present_count }} / {{ $att->enrolled_count }}</td>
                                    <td class="px-5 py-3 font-medium {{ $attRate >= 0.95 ? 'text-emerald-700' : 'text-amber-700' }}">{{ number_format($attRate * 100, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-portal.panel>

        <x-portal.panel class="mt-6" title="Lesson observations this term" subtitle="Appendix B · AE-06. A lesson is effective when the 12-standard average is Secure (3) or better.">
            @if ($observations->isEmpty())
                <p class="text-sm text-slate-500">No observations recorded this term. Use Observe on a red-flag scheme above.</p>
            @else
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Teacher</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Score</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($observations as $obs)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $obs->observation_date->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$obs->teacher" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $obs->teacher->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $obs->schoolClass->name }} · {{ $obs->subject->name }}</td>
                                    <td class="px-5 py-3 font-medium {{ $obs->isCompleted() ? ($obs->isEffective() ? 'text-emerald-700' : 'text-amber-700') : 'text-slate-500' }}">
                                        {{ $obs->isCompleted() ? $obs->scoreLabel() : '—' }}
                                    </td>
                                    <td class="px-5 py-3 capitalize text-slate-600">{{ str_replace('_', ' ', $obs->status) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('observations.show', $obs) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-portal.panel>

        <x-portal.panel class="mt-6" title="Term examination pass rate" subtitle="Termly Broad Sheet · AE-02. Passed (≥{{ (int) $exam_term['pass_mark'] }}%) ÷ enrolled learners. Missing scores count as not passed.">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    @if ($exam_term['enrolled'] === 0)
                        No enrolled learners on the class roll yet.
                    @else
                        <span class="font-semibold text-[#0f2d4a]">{{ number_format($exam_term['rate'] * 100, 1) }}%</span>
                        passed · {{ $exam_term['passed'] }} of {{ $exam_term['enrolled'] }} enrolled sittings
                        @if ($exam_term['rate'] < 0.9)
                            <span class="ml-2 text-amber-700">Below 90% target</span>
                        @endif
                    @endif
                </p>
                <a href="{{ route('exam-results.index') }}" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">Open broad sheet</a>
            </div>
        </x-portal.panel>

        <x-portal.panel class="mt-6" title="At-risk learners" subtitle="Appendix F · AE-07. Identified learners (below pass mark or Concern) with an active Tier 2/3 plan. Target 100%.">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    @if ($at_risk['identified'] === 0)
                        No learners currently identified as at-risk.
                    @else
                        <span class="font-semibold text-[#0f2d4a]">{{ number_format($at_risk['rate'] * 100, 1) }}%</span>
                        with an active plan · {{ $at_risk['with_plan'] }} of {{ $at_risk['identified'] }} identified
                        @if ($at_risk['rate'] < 1)
                            <span class="ml-2 text-amber-700">{{ $at_risk['without_plan'] }} still need a plan</span>
                        @endif
                    @endif
                </p>
                <a href="{{ route('at-risk.index') }}" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">Open caseload</a>
            </div>
        </x-portal.panel>
    </div>
</x-portal-layout>
