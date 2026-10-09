<x-portal-layout title="Curriculum coverage">
    <x-portal.page-intro
        eyebrow="Academic Excellence · AE-01"
        title="Plan, delivery, and verified coverage"
        :meta="$session->name.' · '.$term->name.' · academic week '.$week.'. Planned is not delivered. Delivered is not verified. AE-01.2 is a coverage proxy. AE-01.4 counts identified catch-up topics that HOD later verifies.'"
    >
        <x-slot:actions>
            <form method="GET" class="flex items-end gap-2">
                <div>
                    <x-input-label for="week" value="Week" />
                    <select id="week" name="week" class="mt-1 rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                        @for ($n = 1; $n <= $maxWeek; $n++)
                            <option value="{{ $n }}" @selected($n === (int) $week)>Week {{ $n }}</option>
                        @endfor
                    </select>
                </div>
            </form>
        </x-slot:actions>
    </x-portal.page-intro>

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        @php
            $scheduled = $rows->sum('scheduled');
            $planned = $rows->sum('planned');
            $delivered = $rows->sum('delivered');
            $verified = $rows->sum('verified');
        @endphp
        <x-portal.panel title="Active SoW topics this week">
            <p class="text-2xl font-semibold text-[#0f2d4a]">{{ $scheduled }}</p>
            <p class="text-xs text-slate-500">Denominator for AE-01.1B / AE-01.2 proxy / weekly AE-01.3</p>
        </x-portal.panel>
        <x-portal.panel title="Planned">
            <p class="text-2xl font-semibold text-[#0f2d4a]">{{ $planned }}</p>
            <p class="text-xs text-slate-500">Lesson plans submitted or approved</p>
        </x-portal.panel>
        <x-portal.panel title="Delivered">
            <p class="text-2xl font-semibold text-[#0f2d4a]">{{ $delivered }}</p>
            <p class="text-xs text-slate-500">Teacher delivery logs (not verification)</p>
        </x-portal.panel>
        <x-portal.panel title="Verified">
            <p class="text-2xl font-semibold text-[#0f2d4a]">{{ $verified }}</p>
            <p class="text-xs text-slate-500">HOD-verified coverage logs only</p>
        </x-portal.panel>
    </div>

    @php
        $openCatchUps = $rows->flatMap(function ($row) {
            return $row['missed']
                ->filter(fn ($topic) => $topic->catchUpPlan?->isOpen())
                ->map(fn ($topic) => ['topic' => $topic, 'scheme' => $row['scheme']]);
        });
        $isHod = auth()->user()->isHoD();
        $isTeacher = auth()->user()->isTeacher();
    @endphp

    @if ($openCatchUps->isNotEmpty())
        <div class="mb-6">
            <x-portal.panel title="Open catch-up (AE-01.4)">
                <p class="mb-3 text-xs text-slate-500">Identified untaught Active SoW topics. Addressed only after HOD verifies delivery on an approved lesson plan. This is not an Individual Intervention Plan.</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($openCatchUps as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                            <span>
                                {{ $item['scheme']->schoolClass->name }} · {{ $item['scheme']->subject->name }}
                                · Wk {{ $item['topic']->week_number }} {{ $item['topic']->title }}
                            </span>
                            @if ($isHod)
                                <form method="POST" action="{{ route('catch-ups.cancel', $item['topic']->catchUpPlan) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-red-700">Cancel</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-portal.panel>
        </div>
    @endif

    <x-portal.panel :title="'Class / subject · week '.$week">
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-500">No Active Scheme of Work for this period. Complete the P1 workflow before planning.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3">Class / subject</th>
                            <th class="px-5 py-3">This week</th>
                            <th class="px-5 py-3">Planned</th>
                            <th class="px-5 py-3">Delivered</th>
                            <th class="px-5 py-3">Verified</th>
                            <th class="px-5 py-3">Missed / carry-forward</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 py-3 font-medium">{{ $row['scheme']->schoolClass->name }} · {{ $row['scheme']->subject->name }}</td>
                                <td class="px-5 py-3">{{ $row['scheduled'] }}</td>
                                <td class="px-5 py-3">{{ $row['planned'] }}</td>
                                <td class="px-5 py-3">{{ $row['delivered'] }}</td>
                                <td class="px-5 py-3">{{ $row['verified'] }}</td>
                                <td class="px-5 py-3 text-xs">
                                    @forelse ($row['missed'] as $topic)
                                        @php $catchUp = $topic->catchUpPlan; @endphp
                                        <div class="mb-2">
                                            Wk {{ $topic->week_number }} {{ $topic->title }}
                                            @if ($catchUp?->isOpen())
                                                <span class="ml-1 inline-flex rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Catch-up open</span>
                                            @elseif ($catchUp?->isCancelled())
                                                <span class="ml-1 inline-flex rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Catch-up cancelled</span>
                                            @endif
                                            @if ($isTeacher)
                                                · <a class="text-[#0f2d4a] underline" href="{{ route('lesson-plans.create', ['topic' => $topic->id]) }}">Plan</a>
                                                @if ($topic->hasApprovedLessonPlan())
                                                    · <a class="text-[#0f2d4a] underline" href="{{ route('coverage-logs.create', ['topic' => $topic->id]) }}">Record delivery</a>
                                                @endif
                                            @endif
                                            @if ($isHod)
                                                @if (! $catchUp || $catchUp->isCancelled())
                                                    <form method="POST" action="{{ route('catch-ups.store') }}" class="mt-1">
                                                        @csrf
                                                        <input type="hidden" name="topic_id" value="{{ $topic->id }}">
                                                        <button type="submit" class="text-[11px] font-semibold uppercase tracking-widest text-[#0f2d4a] underline">Open catch-up</button>
                                                    </form>
                                                @elseif ($catchUp->isOpen())
                                                    <form method="POST" action="{{ route('catch-ups.cancel', $catchUp) }}" class="mt-1">
                                                        @csrf
                                                        <button type="submit" class="text-[11px] font-semibold uppercase tracking-widest text-slate-500 underline">Cancel catch-up</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-slate-400">None</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    <div class="mt-6">
        <x-portal.panel title="KPI results (operational series)">
            @if ($results->isEmpty())
                <p class="text-sm text-slate-500">No operational KPI rows yet. They are written when schemes are activated, plans are submitted, or HOD verifies delivery.</p>
            @else
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 py-3">Measure</th>
                                <th class="px-5 py-3">Actual</th>
                                <th class="px-5 py-3">Target</th>
                                <th class="px-5 py-3">Period</th>
                                <th class="px-5 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $result)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 py-3 font-medium">{{ $result->measure_key }}</td>
                                    <td class="px-5 py-3">{{ number_format((float) $result->actual_value * 100, 1) }}%</td>
                                    <td class="px-5 py-3">{{ number_format((float) $result->target_value * 100, 0) }}%</td>
                                    <td class="px-5 py-3 text-xs">{{ optional($result->period_start)->format('d M') }} – {{ optional($result->period_end)->format('d M Y') }}</td>
                                    <td class="px-5 py-3 text-xs text-slate-500">
                                        @if (($result->metadata['calculation_method'] ?? '') === 'proxy')
                                            Proxy — not scheduled class time.
                                        @endif
                                        {{ $result->metadata['label'] ?? '' }}
                                        @if ($result->measure_key === \App\Services\CurriculumCoverageKpiService::AE01_4)
                                            {{ $result->metadata['addressed'] ?? 0 }}/{{ $result->metadata['identified'] ?? 0 }} addressed.
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-portal.panel>
    </div>
</x-portal-layout>
