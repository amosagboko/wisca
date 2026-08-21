@php
    $plan = $record->activePlan();
@endphp

<x-portal-layout title="At-Risk Record">
    <x-portal.page-intro
        eyebrow="Appendix F · AE-07"
        :title="$record->learner->name"
        :meta="$record->schoolClass->name.' · identified '.$record->identification_date->format('d M Y').' · '.$record->levelLabel().' risk'"
    />

    <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
        @if ($record->isOpen() && $canManagePlans)
            <a href="{{ route('at-risk.plans.create', $record) }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                {{ $plan ? 'Replace plan' : 'Add Tier 2/3 plan' }}
            </a>
        @endif
        <a href="{{ route('at-risk.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Back to list</a>
    </div>

    <x-portal.panel title="Identification">
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</dt>
                <dd class="mt-1 font-medium {{ $record->isOpen() ? 'text-amber-800' : 'text-emerald-800' }}">{{ $record->isOpen() ? 'Active' : 'Resolved' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Reason</dt>
                <dd class="mt-1 text-slate-700">{{ implode(' · ', $record->factorLabels()) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Identified by</dt>
                <dd class="mt-1 text-slate-700">{{ $record->identifier->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">AE-07</dt>
                <dd class="mt-1 text-slate-700">
                    @if (! $record->isOpen())
                        Not in the current denominator
                    @elseif ($plan)
                        Counts in numerator (active {{ $plan->typeLabel() }})
                    @else
                        In denominator — no active plan
                    @endif
                </dd>
            </div>
        </dl>
        @if ($record->concern_note)
            <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-700">{{ $record->concern_note }}</p>
        @endif

        @if ($record->isOpen() && $canIdentify)
            <form method="POST" action="{{ route('at-risk.resolve', $record) }}" class="mt-5" onsubmit="return confirm('Mark this learner as no longer at-risk? They will leave the AE-07 denominator.');">
                @csrf
                <button type="submit" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">Mark resolved</button>
            </form>
        @endif
    </x-portal.panel>

    <x-portal.panel class="mt-6" title="Intervention plans" subtitle="Appendix F. Only status Active and type Tier 2 or Tier 3 count toward AE-07.">
        @if ($record->plans->isEmpty())
            <p class="text-sm text-slate-500">No plans yet.{{ $canManagePlans && $record->isOpen() ? ' Add a Tier 2 small-group plan or a Tier 3 intensive plan.' : '' }}</p>
        @else
            <div class="space-y-4">
                @foreach ($record->plans->sortByDesc('start_date') as $row)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-display text-base font-semibold text-[#0f2d4a]">{{ $row->typeLabel() }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $row->start_date->format('d M Y') }} – {{ $row->review_date->format('d M Y') }}
                                    · {{ $row->coordinator->name }}
                                </p>
                            </div>
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-800' => $row->countsTowardKpi(),
                                'bg-slate-100 text-slate-700' => ! $row->countsTowardKpi(),
                            ])>{{ $row->statusLabel() }}</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-700 whitespace-pre-line">{{ $row->objectives }}</p>
                        <p class="mt-2 text-sm text-slate-600 whitespace-pre-line">{{ $row->strategies }}</p>
                        @if ($row->notes)
                            <p class="mt-2 text-xs text-slate-500">{{ $row->notes }}</p>
                        @endif
                        @if ($canManagePlans)
                            <a href="{{ route('intervention-plans.edit', $row) }}" class="mt-3 inline-block text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Update plan</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
