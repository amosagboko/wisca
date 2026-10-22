@php
    $chips = collect($weekly_pulse['chips'] ?? []);
    $pills = collect($weekly_pulse['pills'] ?? []);
@endphp

<x-portal.panel class="mt-6" title="Weekly Pulse" subtitle="School-section performance for this period. Verified coverage is not the same as planned or delivered.">
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

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @foreach ($pills as $pill)
            <a href="{{ $pill['href'] }}"
               class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                {{ $pill['label'] }}
            </a>
        @endforeach
        <a href="{{ $weekly_pulse['see_all'] ?? route('curriculum-coverage.report') }}"
           class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">See all</a>
    </div>
</x-portal.panel>
