@php
    $cta = $cta ?? $item['cta'];
@endphp

<article @class([
    'rounded-xl border bg-white p-4 sm:p-5',
    'border-red-200' => $item['overdue'],
    'border-amber-200' => $item['warn'] && ! $item['overdue'],
    'border-slate-200' => ! $item['overdue'] && ! $item['warn'],
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">{{ $item['type_label'] }}</span>
                @if ($item['overdue'])
                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-800">Overdue</span>
                @elseif ($item['warn'])
                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">20-hour warning</span>
                @endif
            </div>
            <p class="mt-2 font-display text-lg font-semibold text-[#0f2d4a]">{{ $item['title'] }}</p>
            <p class="mt-1 text-sm text-slate-600">
                {{ $item['teacher'] ?? 'Teacher' }}
                @if ($item['section']) · {{ $item['section'] }} @endif
                · {{ $item['class'] ?? 'Class' }}
                · {{ $item['subject'] ?? 'Subject' }}
            </p>
            <p class="mt-2 text-xs text-slate-500">
                Submitted {{ $item['submitted_at']?->format('d M Y H:i') ?? '—' }}
                · {{ $item['elapsed_label'] }}
                · {{ $item['remaining_label'] }}
            </p>
        </div>
        <a href="{{ $item['href'] }}"
           class="inline-flex shrink-0 items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            {{ $cta }}
        </a>
    </div>
</article>
