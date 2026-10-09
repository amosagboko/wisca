<article id="at-risk-plan-{{ $record->id }}" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <p class="font-display text-lg font-semibold text-[#0f2d4a]">{{ $record->learner->name }}</p>
            <p class="text-xs text-slate-500">{{ $record->schoolClass->name }} · {{ implode(' · ', $record->factorLabels()) }}</p>
            @if ($record->concern_note)
                <p class="mt-2 text-sm text-slate-700">{{ $record->concern_note }}</p>
            @endif
            <p class="mt-2 text-xs text-slate-400">HOD writes the plan. AE-07 still uses active Tier 2/3 ÷ identified. Plans are not created automatically.</p>
        </div>
        <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:min-w-[16rem]">
            <a href="{{ route('at-risk.plans.create', $record) }}"
               class="inline-flex w-full items-center justify-center rounded-lg bg-[#0f2d4a] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#163d63]">
                Add intervention plan
            </a>
        </div>
    </div>
</article>
