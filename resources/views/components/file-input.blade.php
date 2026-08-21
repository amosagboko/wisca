@props([
    'name' => 'file',
    'accept' => null,
    'button' => 'Choose file',
    'empty' => 'No file chosen',
])

@php
    $inputId = $attributes->get('id') ?? $name;
@endphp

<div class="mt-1" x-data="{ fileName: '' }">
    <input
        {{ $attributes->merge([
            'id' => $inputId,
            'name' => $name,
            'type' => 'file',
            'class' => 'sr-only',
        ])->except(['button', 'empty']) }}
        @if ($accept) accept="{{ $accept }}" @endif
        x-ref="fileInput"
        @change="fileName = $refs.fileInput.files[0]?.name || ''"
    >

    <div class="flex flex-wrap items-center gap-3">
        <button
            type="button"
            @click="$refs.fileInput.click()"
            class="inline-flex items-center gap-2 rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-[#163d63] focus:outline-none focus:ring-2 focus:ring-[#0f2d4a]/40 focus:ring-offset-2"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            {{ $button }}
        </button>
        <span class="min-w-0 text-sm text-slate-600" x-text="fileName || @js($empty)"></span>
    </div>
</div>
