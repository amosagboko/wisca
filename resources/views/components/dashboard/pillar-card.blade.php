@props(['pillarSummary', 'code'])

@php
    $evaluator = app(\App\Services\KpiStatusEvaluator::class);
    $pillar = $pillarSummary['pillar'];
    $theme = $pillar->config['color_theme'] ?? '#0f2d4a';
    $accent = $evaluator->statusAccentClass($pillarSummary['headline_status']);
@endphp

<button
    type="button"
    {{ $attributes->class([
        'portal-enter group w-full rounded-xl border-l-4 bg-white p-5 text-left shadow-sm transition hover:shadow-md',
        $accent,
    ]) }}
    :class="pillar === @js($code) ? 'ring-2 ring-[#0f2d4a]/25 ring-offset-2 shadow-md' : ''"
    @click="selectPillar(@js($code))"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $pillar->name }}</p>
            <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">{{ number_format($pillarSummary['avg_achievement'] * 100, 2) }}%</p>
        </div>
        <span
            class="mt-1 h-3 w-3 shrink-0 rounded-full ring-2 ring-white"
            style="background-color: {{ $theme }}"
            aria-hidden="true"
        ></span>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <x-dashboard.health-badge :status="$pillarSummary['headline_status']" />
    </div>

    <p class="mt-4 text-xs text-slate-500">
        {{ $pillarSummary['reported_kpis'] }}/{{ $pillarSummary['total_kpis'] }} reported ·
        <span class="text-emerald-700">{{ $pillarSummary['on_track'] }} on track</span> ·
        <span class="text-amber-700">{{ $pillarSummary['needs_attention'] }} need attention</span>
        @if ($pillarSummary['off_track'] > 0)
            · <span class="text-red-700">{{ $pillarSummary['off_track'] }} off track</span>
        @endif
    </p>

    <p class="mt-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400 group-hover:text-[#0f2d4a]">
        View KPI detail →
    </p>
</button>
