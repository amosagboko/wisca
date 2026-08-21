@props([
    'title' => null,
    'subtitle' => null,
    'tone' => 'default',
])

@php
    $shell = match ($tone) {
        'danger' => 'border-red-200/80 bg-white',
        default => 'border-slate-200/90 bg-white',
    };
@endphp

<section {{ $attributes->class(['portal-enter rounded-2xl border shadow-[0_1px_2px_rgba(15,45,74,0.04),0_8px_24px_rgba(15,45,74,0.04)]', $shell]) }}>
    @if ($title || $subtitle || isset($header))
        <div @class([
            'border-b px-5 py-4 sm:px-6',
            'border-red-100 bg-red-50/50' => $tone === 'danger',
            'border-slate-100 bg-gradient-to-r from-slate-50/90 to-white' => $tone !== 'danger',
        ])>
            @isset($header)
                {{ $header }}
            @else
                @if ($title)
                    <h3 @class([
                        'font-display text-lg font-semibold',
                        'text-red-800' => $tone === 'danger',
                        'text-[#0f2d4a]' => $tone !== 'danger',
                    ])>{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
                @endif
            @endisset
        </div>
    @endif

    <div class="px-5 py-5 sm:px-6 sm:py-6">
        {{ $slot }}
    </div>
</section>
