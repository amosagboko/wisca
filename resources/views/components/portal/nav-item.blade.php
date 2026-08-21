@props([
    'href' => null,
    'active' => false,
    'icon' => 'dashboard',
    'label' => '',
])

@php
    $base = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition';
    $idleClasses = 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
    $text = $label !== '' ? $label : trim(strip_tags($slot));
@endphp

@if (! $href)
    <span @class([$base, 'text-slate-400']) :title="collapsed ? @js($text) : ''">
        <x-portal.icon :name="$icon" class="h-5 w-5 shrink-0 opacity-70" />
        <span class="truncate" x-show="!collapsed" x-cloak>{{ $text }}</span>
    </span>
@else
    <a
        href="{{ $href }}"
        @class([
            $base,
            $active
                ? 'bg-[#0f2d4a] text-white shadow-sm hover:bg-[#0f2d4a] hover:text-white'
                : $idleClasses,
        ])
        :title="collapsed ? @js($text) : ''"
    >
        <x-portal.icon :name="$icon" class="h-5 w-5 shrink-0 opacity-80" />
        <span class="truncate" x-show="!collapsed" x-cloak>{{ $text }}</span>
    </a>
@endif
