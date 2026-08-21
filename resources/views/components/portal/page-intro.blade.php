@props([
    'eyebrow' => null,
    'title',
    'meta' => null,
])

<div {{ $attributes->class(['portal-enter mb-6']) }}>
    <div class="relative overflow-hidden rounded-2xl border border-[#0f2d4a]/10 bg-gradient-to-br from-[#0f2d4a] via-[#143a5c] to-[#1a4a73] px-5 py-5 text-white shadow-sm sm:px-6 sm:py-6">
        <div class="pointer-events-none absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-16 right-16 h-36 w-36 rounded-full bg-sky-200/10 blur-2xl"></div>

        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 max-w-2xl">
                @if ($eyebrow)
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">{{ $eyebrow }}</p>
                @endif
                <h2 class="mt-1 font-display text-2xl font-semibold tracking-tight text-white sm:text-[1.65rem]">{{ $title }}</h2>
                @if ($meta)
                    <p class="mt-2 text-sm leading-relaxed text-slate-200/90">{{ $meta }}</p>
                @endif
                @isset($description)
                    <div class="mt-2 text-sm leading-relaxed text-slate-200/90">{{ $description }}</div>
                @endisset
            </div>

            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    </div>
</div>
