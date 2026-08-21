@props([
    'user' => null,
    'size' => 'md',
])

@php
    $person = $user;
    $sizeClass = match ($size) {
        'xs' => 'h-7 w-7 text-[10px]',
        'sm' => 'h-8 w-8 text-[11px]',
        'lg' => 'h-16 w-16 text-lg',
        'xl' => 'h-24 w-24 text-2xl',
        default => 'h-9 w-9 text-xs',
    };
@endphp

@if ($person?->passportUrl())
    <img
        {{ $attributes->class([$sizeClass, 'shrink-0 rounded-full object-cover ring-1 ring-slate-200']) }}
        src="{{ $person->passportUrl() }}"
        alt="{{ $person->name }}"
    >
@else
    <span {{ $attributes->class([$sizeClass, 'inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white ring-1 ring-white/20', $person?->avatarClass() ?? 'bg-[#0f2d4a]']) }}>
        {{ $person?->initials() ?? '?' }}
    </span>
@endif
