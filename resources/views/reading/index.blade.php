@php
    $canRecord  = auth()->user()->canRecordReading();
    $assessed   = $schoolSummary['assessed'];
    $achieved   = $schoolSummary['achieved'];
    $percent    = $assessed > 0 ? round($schoolSummary['rate'] * 100, 1) : null;
    $growthMin  = $schoolSummary['growth_min'];
    $target     = 85;
    $termLabel  = $filters['termId'] ? ($term?->name ?? 'Selected term') : 'All terms';
    $classLabel = $filters['classId'] ? ($summaries->first()['class']?->name ?? 'Selected class') : 'All classes';
@endphp

<x-portal-layout title="Reading Progress">
    <x-portal.page-intro
        eyebrow="Literacy Assessment Battery · AE-08"
        title="Reading progress (≥{{ $growthMin }} grade-level growth)"
        :meta="$session->name.' · '.$termLabel.' · '.$classLabel.'. Students achieving ≥'.$growthMin.' grade-level growth ÷ total assessed. Target '.$target.'%.'"
    />

    {{-- Filters --}}
    <form method="GET" action="{{ route('reading.index') }}" class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="f_session" value="Session" />
            <select id="f_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_term" value="Term" />
            <select id="f_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($filters['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $filters['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_class" value="Class" />
            <select id="f_class" name="class_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($filters['classId'] === 0)>All classes</option>
                @foreach ($allClasses as $cls)
                    <option value="{{ $cls->id }}" @selected($cls->id === $filters['classId'])>{{ $cls->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($filters['sessionId'] || $filters['termId'] || $filters['classId'])
                <a href="{{ route('reading.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- AE-08 hero card --}}
    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">AE-08 this session</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    @if ($assessed === 0)
                        No complete assessments yet. Record baseline and follow-up levels to begin.
                    @else
                        {{ $achieved }} of {{ $assessed }} assessed learners reached ≥{{ $growthMin }} grade-level growth · target {{ $target }}%
                    @endif
                </p>
            </div>
            @if ($canRecord)
                <a href="{{ route('reading.create') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                    Record assessments
                </a>
            @endif
        </div>
        @if ($percent !== null)
            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
                <div class="h-full rounded-full {{ $schoolSummary['rate'] >= ($target / 100) ? 'bg-emerald-400' : 'bg-amber-400' }}" style="width: {{ min(100, $percent) }}%"></div>
            </div>
        @endif
    </section>

    {{-- Per-class breakdown --}}
    <x-portal.panel title="Class breakdown" subtitle="Complete assessments only (both baseline and follow-up levels entered).">
        @if ($summaries->isEmpty())
            <p class="text-sm text-slate-500">No classes assigned. Ensure teacher assignments are set up for this session.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Assessed</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">≥{{ $growthMin }} growth</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Rate</th>
                            @if ($canRecord)
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summaries as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $row['class']->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['assessed'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['achieved'] }}</td>
                                <td class="px-5 py-3 font-medium {{ $row['assessed'] > 0 && $row['rate'] >= ($target / 100) ? 'text-emerald-700' : ($row['assessed'] > 0 ? 'text-amber-700' : 'text-slate-400') }}">
                                    {{ $row['assessed'] > 0 ? number_format($row['rate'] * 100, 1).'%' : '—' }}
                                </td>
                                @if ($canRecord)
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('reading.create', ['class' => $row['class']->id]) }}" class="text-xs font-semibold text-[#0f2d4a] hover:underline">
                                            {{ $row['assessed'] > 0 ? 'Update' : 'Record' }}
                                        </a>
                                    </td>
                                @endif
                            </tr>
                            {{-- Learner detail rows --}}
                            @foreach ($row['rows'] as $assessment)
                                @php
                                    $growth = $assessment->growth();
                                    $ok = $growth !== null && $growth >= $growthMin;
                                @endphp
                                <tr class="border-t border-slate-50 bg-slate-50/50">
                                    <td class="px-5 sm:px-6 py-2 pl-10 text-xs text-slate-500">{{ $assessment->learner->name }}</td>
                                    <td class="px-5 py-2 text-xs text-slate-500">{{ $assessment->baseline_level ?? '—' }}</td>
                                    <td class="px-5 py-2 text-xs {{ $ok ? 'text-emerald-700 font-semibold' : 'text-slate-500' }}">
                                        {{ $assessment->followup_level ?? '—' }}
                                        @if ($growth !== null)
                                            <span class="ml-1 {{ $ok ? 'text-emerald-600' : 'text-amber-600' }}">
                                                ({{ $growth >= 0 ? '+' : '' }}{{ $growth }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2 text-xs {{ $ok ? 'text-emerald-700 font-semibold' : 'text-amber-700' }}">
                                        @if ($growth === null)
                                            Incomplete
                                        @elseif ($ok)
                                            ✓ Achieved
                                        @else
                                            Below target
                                        @endif
                                    </td>
                                    @if ($canRecord)
                                        <td></td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    @if (session('status'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif
</x-portal-layout>
