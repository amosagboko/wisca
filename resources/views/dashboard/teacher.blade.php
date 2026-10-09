@php
    $percent = $coverage['total'] > 0 ? round($coverage['rate'] * 100, 1) : 0;
    $barClass = $behind ? 'bg-amber-500' : 'bg-emerald-500';
@endphp

<x-portal-layout title="My Week">
    {{-- Session switcher --}}
    @if ($allSessions->count() > 1)
        <form method="GET" action="{{ route('dashboard') }}" class="mb-6 flex items-center gap-3">
            <x-input-label for="teacher_session" value="Session" class="shrink-0" />
            <select id="teacher_session" name="session_id" onchange="this.form.submit()"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>
    @endif

    <x-portal.page-intro
        eyebrow="Teacher · Appendix C"
        title="My Week"
        :meta="'Week '.$week_number.($term ? ' of '.$term->name : '').' · '.$session->name.'. Do the next piece of work; HOD reviews the evidence.'"
    />

    @php
        $taskInbox = $inbox ?? [];
        $taskTotal = (int) ($taskInbox['total'] ?? collect($tasks ?? [])->count());
    @endphp
    <x-portal.work-inbox
        title="Due this week"
        :subtitle="$taskTotal === 0
            ? 'One inbox. Each item opens the existing form — plans, coverage, homework, register, or marks.'
            : $taskTotal.' item'.($taskTotal === 1 ? '' : 's').' this week, grouped by activity then class.'"
        :items="$taskInbox['items'] ?? $tasks ?? collect()"
        :grouped="$taskInbox['groups'] ?? null"
        :types="$taskInbox['types'] ?? null"
        :paginator="$taskInbox['paginator'] ?? null"
        :active-type="$taskInbox['active_type'] ?? ''"
        :total="$taskTotal"
        :filtered-total="$taskInbox['filtered_total'] ?? $taskTotal"
        empty="Nothing due from your assignments this week. Coverage, homework, and attendance summaries stay below."
    />

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">Curriculum coverage</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent }}%</p>
                <p class="mt-1 text-sm text-white/75">{{ $coverage['covered'] }} of {{ $coverage['total'] }} scheme topics covered</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">AE-01 status</p>
                <p class="mt-1 text-sm font-semibold">{{ $behind ? 'Below 95% — needs attention' : 'On track for this scheme' }}</p>
            </div>
        </div>
        <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
            <div class="h-full rounded-full {{ $barClass }}" style="width: {{ min(100, $percent) }}%"></div>
        </div>
    </section>

    @php $hwPercent = $homework_week['given'] > 0 ? round($homework_week['rate'] * 100, 1) : null; @endphp
    <x-portal.panel class="mb-6" title="This week’s homework" subtitle="AE-03 · on-time completions against assignments given. Update a log after you mark the work.">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                @if ($hwPercent === null)
                    No homework logged for this instructional week yet.
                @else
                    <span class="font-semibold text-[#0f2d4a]">{{ $hwPercent }}%</span>
                    on time · {{ $homework_week['completed'] }} of {{ $homework_week['given'] }} assignments
                    @if ($homework_week['rate'] < 0.95)
                        <span class="ml-2 text-amber-700">Below 95% target</span>
                    @endif
                @endif
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('homework.index') }}" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">View all</a>
                <a href="{{ route('homework.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                    Log homework
                </a>
            </div>
        </div>

        @if ($homework_logs->isEmpty())
            <p class="text-sm text-slate-500">No homework logged for this instructional week. Record class-level given vs on-time counts — a student roster is not required.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Assignment</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Given</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">On time</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($homework_logs as $hw)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $hw->title }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $hw->schoolClass->name }} · {{ $hw->subject->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $hw->given_count }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $hw->completed_on_time_count }} ({{ number_format($hw->completionRate() * 100, 1) }}%)</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('homework.edit', $hw) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Update</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    @php $attPercent = $attendance_week['enrolled'] > 0 ? round($attendance_week['rate'] * 100, 1) : null; @endphp
    <x-portal.panel class="mb-6" title="This week’s attendance" subtitle="AE-04 · present learner-days against enrolled roll. Late counts as present.">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                @if ($attPercent === null)
                    No class register for this instructional week yet.
                @else
                    <span class="font-semibold text-[#0f2d4a]">{{ $attPercent }}%</span>
                    present · {{ $attendance_week['present'] }} of {{ $attendance_week['enrolled'] }} enrolled learner-days
                    @if ($attendance_week['rate'] < 0.95)
                        <span class="ml-2 text-amber-700">Below 95% target</span>
                    @endif
                @endif
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('attendance.index') }}" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">View all</a>
                <a href="{{ route('attendance.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                    Take register
                </a>
            </div>
        </div>

        @if ($attendance_logs->isEmpty())
            <p class="text-sm text-slate-500">No attendance logged for this instructional week. Record enrolled vs present for each class day — a student roster is not required.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Present / Enrolled</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attendance_logs as $att)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $att->attendance_date->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $att->schoolClass->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $att->present_count }} / {{ $att->enrolled_count }} ({{ number_format($att->attendanceRate() * 100, 1) }}%)</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('attendance.edit', $att) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Update</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    @php
        $examPercent = $exam_term['enrolled'] > 0 ? round($exam_term['rate'] * 100, 1) : null;
        $firstSitting = collect($exam_term['sittings'])->first();
        $marksheetQuery = $firstSitting
            ? ['assignment' => $firstSitting['assignment']->school_class_id.':'.$firstSitting['assignment']->subject_id]
            : [];
    @endphp
    <x-portal.panel class="mb-6" title="Term examination pass rate" subtitle="AE-02 · students scoring ≥{{ (int) $exam_term['pass_mark'] }}% against the enrolled class roll. Leave a score blank if there is no result.">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                @if ($examPercent === null)
                    No enrolled learners on your class roll yet.
                @else
                    <span class="font-semibold text-[#0f2d4a]">{{ $examPercent }}%</span>
                    passed · {{ $exam_term['passed'] }} of {{ $exam_term['enrolled'] }} enrolled
                    @if ($exam_term['recorded'] < $exam_term['enrolled'])
                        <span class="ml-2 text-slate-500">{{ $exam_term['enrolled'] - $exam_term['recorded'] }} without a score</span>
                    @endif
                    @if ($exam_term['rate'] < 0.9)
                        <span class="ml-2 text-amber-700">Below 90% target</span>
                    @endif
                @endif
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('exam-results.index') }}" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">View all</a>
                <a href="{{ route('exam-results.edit', $marksheetQuery) }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                    Open marksheet
                </a>
            </div>
        </div>

        @if (collect($exam_term['sittings'])->isEmpty())
            <p class="text-sm text-slate-500">No class/subject assignment this session. Ask Admin to assign you before entering marks.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Enrolled</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Recorded</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Passed</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exam_term['sittings'] as $sitting)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $sitting['assignment']->schoolClass->name }} · {{ $sitting['assignment']->subject->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $sitting['enrolled'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $sitting['recorded'] }}</td>
                                <td class="px-5 py-3 font-medium {{ $sitting['enrolled'] > 0 && $sitting['rate'] >= 0.9 ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ $sitting['passed'] }} ({{ $sitting['enrolled'] > 0 ? number_format($sitting['rate'] * 100, 1) : '—' }}%)
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('exam-results.edit', ['assignment' => $sitting['assignment']->school_class_id.':'.$sitting['assignment']->subject_id]) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Marksheet</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    @php
        $atRiskPercent = $at_risk['identified'] > 0 ? round($at_risk['rate'] * 100, 1) : null;
    @endphp
    <x-portal.panel class="mb-6" title="At-risk learners in my classes" subtitle="Appendix F · AE-07. Flag below-pass-mark or Concern learners so Learning Support can attach a Tier 2/3 plan.">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                @if ($atRiskPercent === null)
                    No at-risk identifications in your classes yet.
                @else
                    <span class="font-semibold text-[#0f2d4a]">{{ $atRiskPercent }}%</span>
                    with an active plan · {{ $at_risk['with_plan'] }} of {{ $at_risk['identified'] }} identified
                    @if ($at_risk['rate'] < 1)
                        <span class="ml-2 text-amber-700">{{ $at_risk['without_plan'] }} still need a plan</span>
                    @endif
                @endif
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('at-risk.index') }}" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">View all</a>
                <a href="{{ route('at-risk.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                    Identify learner
                </a>
            </div>
        </div>

        @if ($at_risk['unflagged']->isNotEmpty())
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-800">Below pass mark, not yet flagged</p>
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($at_risk['unflagged'] as $row)
                    <form method="POST" action="{{ route('at-risk.from-exam') }}">
                        @csrf
                        <input type="hidden" name="learner_id" value="{{ $row['learner']->id }}">
                        <button type="submit" class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-[#0f2d4a] hover:text-[#0f2d4a]">
                            {{ $row['learner']->name }} · {{ number_format($row['lowest'], 0) }}%
                        </button>
                    </form>
                @endforeach
            </div>
        @endif

        @if ($at_risk['records']->isEmpty() && $at_risk['unflagged']->isEmpty())
            <p class="text-sm text-slate-500">No at-risk learners in your assigned classes.</p>
        @elseif ($at_risk['records']->isNotEmpty())
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Plan</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($at_risk['records'] as $record)
                            @php $plan = $record->activePlan(); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $record->learner->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $record->schoolClass->name }}</td>
                                <td class="px-5 py-3">{{ $plan ? $plan->typeLabel().' active' : 'No active plan' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('at-risk.show', $record) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    <x-portal.panel class="mb-6" title="Lesson observations" subtitle="Appendix B · AE-06. Your observer scores 12 instructional standards; Secure (3) or better is effective.">
        @if ($observations->isEmpty())
            <p class="text-sm text-slate-500">No observations recorded for you this session yet.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Observer</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Score</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($observations as $obs)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $obs->observation_date->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $obs->schoolClass->name }} · {{ $obs->subject->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $obs->observer->name }}</td>
                                <td class="px-5 py-3 font-medium {{ $obs->isCompleted() ? ($obs->isEffective() ? 'text-emerald-700' : 'text-amber-700') : 'text-slate-500' }}">
                                    {{ $obs->isCompleted() ? $obs->scoreLabel() : 'Scheduled' }}
                                </td>
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

    <x-portal.panel title="This week’s topics" :subtitle="'Topics scheduled for week '.$week_number.' from your approved scheme of work.'">
        @if ($this_week_topics->isEmpty())
            <p class="text-sm text-slate-500">No topics are scheduled for this week. Check remaining topics below, or confirm your class/subject assignment with Admin.</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this_week_topics as $topic)
                    @php
                        $scheme = $topic->schemeOfWork;
                        $log = $topic->latestCoverageLog;
                        $plan = $topic->latestLessonPlan;
                    @endphp
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            {{ $scheme->schoolClass->name }} · {{ $scheme->subject->name }}
                        </p>
                        <h3 class="mt-2 font-display text-lg font-semibold text-[#0f2d4a]">{{ $topic->title }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Week {{ $topic->week_number }}</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($topic->status === 'covered')
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Covered</span>
                            @elseif ($log?->status === 'submitted')
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Coverage awaiting HoD</span>
                            @elseif ($log?->status === 'rejected')
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Coverage rejected</span>
                            @elseif ($topic->hasApprovedLessonPlan())
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Plan approved</span>
                            @elseif ($plan?->status === 'submitted')
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Plan awaiting HoD</span>
                            @elseif ($plan?->status === 'rejected')
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Plan rejected</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">No lesson plan</span>
                            @endif
                        </div>

                        @if (! $topic->hasApprovedLessonPlan())
                            @if ($plan?->status === 'submitted')
                                <p class="mt-4 text-xs text-slate-500">Coverage is locked until this plan is approved.</p>
                            @else
                                <a href="{{ $plan?->isEditable() ? route('lesson-plans.edit', $plan) : route('lesson-plans.create', ['topic' => $topic->id]) }}" class="mt-4 inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                                    {{ $plan?->status === 'rejected' ? 'Revise lesson plan' : 'Submit lesson plan' }}
                                </a>
                            @endif
                        @elseif ($topic->isLoggable())
                            <a href="{{ route('coverage-logs.create', ['topic' => $topic->id]) }}" class="mt-4 inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                                Log coverage
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-portal.panel>

    @if ($remaining_topics->isNotEmpty())
        <x-portal.panel class="mt-6" title="Remaining scheme topics" subtitle="Tap a topic to submit workbook evidence for HoD verification.">
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Week</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Topic</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($remaining_topics as $topic)
                            @php $scheme = $topic->schemeOfWork; @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 text-slate-600">{{ $topic->week_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-800">{{ $topic->title }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $scheme->schoolClass->name }} · {{ $scheme->subject->name }}</td>
                                <td class="px-5 py-3 capitalize text-slate-600">{{ str_replace('_', ' ', $topic->latestCoverageLog?->status ?? $topic->status) }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if (! $topic->hasApprovedLessonPlan())
                                        <a href="{{ $topic->latestLessonPlan?->isEditable() ? route('lesson-plans.edit', $topic->latestLessonPlan) : route('lesson-plans.create', ['topic' => $topic->id]) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Plan</a>
                                    @elseif ($topic->isLoggable())
                                        <a href="{{ route('coverage-logs.create', ['topic' => $topic->id]) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Log</a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-portal.panel>
    @endif

    <x-portal.panel class="mt-6" title="Recent submissions" subtitle="Workbook evidence you have sent for verification.">
        @if ($logs->isEmpty())
            <p class="text-sm text-slate-500">No coverage logs yet. Log this week’s topic using the card above.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Topic</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Workbook</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $log->topic->title }}</td>
                                <td class="px-5 py-3">{{ $log->schoolClass->name }} · {{ $log->subject->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->workbook_reference }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->coverage_date->format('d M Y') }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $log->status === 'verified',
                                        'bg-amber-100 text-amber-800' => $log->status === 'submitted',
                                        'bg-red-100 text-red-800' => $log->status === 'rejected',
                                        'bg-slate-100 text-slate-700' => ! in_array($log->status, ['verified', 'submitted', 'rejected']),
                                    ])>{{ ucfirst($log->status) }}</span>
                                    @if ($log->status === 'rejected' && $log->rejection_reason)
                                        <p class="mt-1 max-w-xs text-xs text-red-700">{{ $log->rejection_reason }}</p>
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
