@php
    $percent = $at_risk['identified'] > 0 ? round($at_risk['rate'] * 100, 1) : null;
    $gaps = $at_risk['records']->reject->hasActivePlan()->values();
    $unflagged = $unflagged ?? collect();
@endphp

<x-portal-layout title="Learning Support">
    {{-- Session / Term switcher --}}
    <form method="GET" action="{{ route('dashboard') }}"
          class="mb-6 flex flex-wrap items-center gap-4">
        @if ($allSessions->count() > 1)
            <div class="flex items-center gap-2">
                <x-input-label for="sup_session" value="Session" class="shrink-0" />
                <select id="sup_session" name="session_id"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($allSessions as $s)
                        <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($allTerms->count() > 1)
            <div class="flex items-center gap-2">
                <x-input-label for="sup_term" value="Term" class="shrink-0" />
                <select id="sup_term" name="term_id"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($termId === 0)>Current term</option>
                    @foreach ($allTerms as $t)
                        <option value="{{ $t->id }}" @selected($t->id === $termId)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($allSessions->count() > 1 || $allTerms->count() > 1)
            <x-primary-button type="submit">Apply</x-primary-button>
        @endif
    </form>

    <x-portal.page-intro
        eyebrow="Learning Support · Appendix F"
        title="At-risk caseload"
        :meta="($term?->name ?? 'Current term').' · '.$session->name.'. Every identified learner needs an active Tier 2 or Tier 3 plan.'"
    />

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">AE-07 coverage</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    @if ($percent === null)
                        No at-risk identifications this session.
                    @else
                        {{ $at_risk['with_plan'] }} of {{ $at_risk['identified'] }} identified learners have an active plan
                    @endif
                </p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Target</p>
                <p class="mt-1 text-sm font-semibold">{{ $percent === null ? 'Awaiting identifications' : ($at_risk['rate'] >= 1 ? 'On track for 100%' : 'Gaps remain') }}</p>
            </div>
        </div>
        @if ($percent !== null)
            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
                <div class="h-full rounded-full {{ $at_risk['rate'] >= 1 ? 'bg-emerald-400' : 'bg-amber-400' }}" style="width: {{ min(100, $percent) }}%"></div>
            </div>
        @endif
    </section>

    @if ($unflagged->isNotEmpty())
        <x-portal.panel class="mb-6" tone="danger" title="Below pass mark — not flagged" subtitle="These exam sittings are below the AE-02 pass mark and are not yet in the AE-07 denominator.">
            <div class="flex flex-wrap gap-2">
                @foreach ($unflagged as $row)
                    <form method="POST" action="{{ route('at-risk.from-exam') }}">
                        @csrf
                        <input type="hidden" name="learner_id" value="{{ $row['learner']->id }}">
                        <button type="submit" class="inline-flex items-center rounded-full border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-[#0f2d4a] hover:text-[#0f2d4a]">
                            {{ $row['learner']->name }} · {{ number_format($row['lowest'], 0) }}%
                        </button>
                    </form>
                @endforeach
            </div>
        </x-portal.panel>
    @endif

    <x-portal.panel class="mb-6" title="Learners without an active plan" subtitle="AE-07 stays off 100% until each of these has a live Tier 2 or Tier 3 plan.">
        @if ($gaps->isEmpty())
            <p class="text-sm text-slate-500">Every currently identified learner has an active plan.</p>
        @else
            <div class="space-y-3">
                @foreach ($gaps as $record)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <div>
                            <p class="font-medium text-slate-800">{{ $record->learner->name }}</p>
                            <p class="text-xs text-slate-500">{{ $record->schoolClass->name }} · {{ implode(', ', $record->factorLabels()) }}</p>
                        </div>
                        <a href="{{ route('at-risk.plans.create', $record) }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                            Add plan
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </x-portal.panel>

    <x-portal.panel title="Caseload" subtitle="Open identifications this session.">
        @if ($at_risk['records']->isEmpty())
            <p class="text-sm text-slate-500">No active identifications. Start from a below-pass-mark result or record a Concern.</p>
            <a href="{{ route('at-risk.create') }}" class="mt-4 inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">Identify learner</a>
        @else
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
                                <td class="px-5 py-3">{{ $plan ? $plan->typeLabel() : 'None' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('at-risk.show', $record) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <a href="{{ route('at-risk.index') }}" class="mt-4 inline-block text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">View full register</a>
        @endif
    </x-portal.panel>
</x-portal-layout>
