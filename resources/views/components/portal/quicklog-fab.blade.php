@props(['actions' => []])

@php
    $actions = collect($actions);
@endphp

@if ($actions->isNotEmpty())
    <div class="fixed bottom-5 right-5 z-40 sm:bottom-8 sm:right-8" x-data="{ open: false }">
        <div x-show="open" x-cloak class="mb-3 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
            <p class="border-b border-slate-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">QuickLog</p>
            <ul class="py-1">
                @foreach ($actions as $action)
                    <li>
                        <a href="{{ $action['href'] }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            {{ $action['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <button type="button"
                @click="open = ! open"
                class="flex h-14 w-14 items-center justify-center rounded-full bg-[#0f2d4a] text-2xl font-semibold text-white shadow-lg hover:bg-[#163d63]"
                aria-label="Open QuickLog">
            +
        </button>
    </div>
@endif
