@php
    $chips = collect($performance['chips'] ?? []);
    $followUps = collect($performance['follow_ups'] ?? []);
@endphp

<x-portal.panel class="mt-6" title="My Performance & Learner Follow-up" subtitle="Teacher-scoped rates. Missing data stays Awaiting data — never a fake 0%.">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($chips as $chip)
            <a href="{{ $chip['href'] }}"
               class="rounded-xl border-l-4 bg-slate-50/70 p-4 transition hover:bg-slate-50 {{ $chip['accent_class'] }}">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $chip['label'] }}</p>
                @if ($chip['has_data'] && $chip['actual'] !== null)
                    <p class="mt-2 font-display text-2xl font-semibold text-[#0f2d4a]">{{ number_format($chip['actual'] * 100, 1) }}%</p>
                    <span class="mt-2 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $chip['status_class'] }}">
                        {{ $chip['status'] }}
                    </span>
                    <p class="mt-2 text-xs text-slate-500">Target {{ number_format($chip['target'] * 100, 0) }}%</p>
                @else
                    <p class="mt-2 font-display text-lg font-semibold text-slate-500">Awaiting data</p>
                    <p class="mt-2 text-xs text-slate-500">Target {{ number_format($chip['target'] * 100, 0) }}%</p>
                @endif
                @if ($chip['detail'])
                    <p class="mt-1 text-xs text-slate-500">{{ $chip['detail'] }}</p>
                @endif
                <p class="mt-1 text-[11px] text-slate-400">{{ $chip['period'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6">
        <h4 class="text-sm font-semibold text-[#0f2d4a]">Follow-up</h4>
        @if ($followUps->isEmpty())
            <p class="mt-2 text-sm text-slate-500">No assigned follow-up items.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($followUps as $item)
                    <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <div>
                            <p class="font-medium text-[#0f2d4a]">{{ $item['label'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $item['context'] }}</p>
                        </div>
                        <a href="{{ $item['href'] }}"
                           class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50">
                            {{ $item['cta'] }}
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-portal.panel>
