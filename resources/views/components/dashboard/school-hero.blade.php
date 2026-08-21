@props(['summary', 'session'])

@php
    $evaluator = app(\App\Services\KpiStatusEvaluator::class);
    $totals = $summary['totals'];
@endphp

<section class="portal-enter overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">Executive Dashboard</p>
            <h2 class="mt-2 font-display text-2xl font-semibold sm:text-3xl">{{ $summary['school_name'] }}</h2>
            <p class="mt-2 text-sm text-white/75">Session {{ $session->name }} · {{ ucfirst($session->status) }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div class="rounded-xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Overall health</p>
                <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold text-white ring-1 ring-white/20">
                    <span @class(['h-1.5 w-1.5 shrink-0 rounded-full', $evaluator->statusDotClass($totals['overall_health'])]) aria-hidden="true"></span>
                    {{ $totals['overall_health'] }}
                </span>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Avg achievement</p>
                <p class="mt-1 font-display text-2xl font-semibold">{{ number_format($totals['avg_achievement'] * 100, 2) }}%</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Reported KPIs</p>
                <p class="mt-1 font-display text-2xl font-semibold">{{ $totals['reported_kpis'] }}<span class="text-base font-normal text-white/60"> / {{ $totals['total_kpis'] }}</span></p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-3 border-t border-white/10 pt-6 sm:grid-cols-3">
        <div class="flex items-center gap-3 rounded-lg bg-white/5 px-4 py-3">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
            <div>
                <p class="text-xs text-white/60">On track</p>
                <p class="font-display text-xl font-semibold">{{ $totals['on_track'] }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg bg-white/5 px-4 py-3">
            <span class="h-2.5 w-2.5 rounded-full bg-amber-400" aria-hidden="true"></span>
            <div>
                <p class="text-xs text-white/60">Needs attention</p>
                <p class="font-display text-xl font-semibold">{{ $totals['needs_attention'] }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg bg-white/5 px-4 py-3">
            <span class="h-2.5 w-2.5 rounded-full bg-red-400" aria-hidden="true"></span>
            <div>
                <p class="text-xs text-white/60">Off track</p>
                <p class="font-display text-xl font-semibold">{{ $totals['off_track'] }}</p>
            </div>
        </div>
    </div>
</section>
