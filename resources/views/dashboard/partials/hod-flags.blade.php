@php
    $open = collect($hod_flags['open'] ?? []);
    $closed = collect($hod_flags['closed'] ?? []);
@endphp

<x-portal.panel class="mt-6" title="Flags & Follow-ups" subtitle="Exceptions that need intervention. Closing a flag requires the underlying workflow.">
    @if ($open->isEmpty())
        <p class="text-sm text-slate-500">No open exceptions for this school section.</p>
    @else
        <div class="space-y-3">
            @foreach ($open as $flag)
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ str_replace('-', ' ', $flag['category']) }}</p>
                            <p class="mt-1 font-medium text-[#0f2d4a]">{{ $flag['title'] }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $flag['context'] }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ $flag['reason'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">Next: {{ $flag['next'] }} · {{ $flag['owner'] }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col gap-2">
                            @if (! empty($flag['form']['action']))
                                <form method="POST" action="{{ $flag['form']['action'] }}">
                                    @csrf
                                    @foreach ($flag['form']['fields'] ?? [] as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                    <button type="submit" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-[#163d63]">
                                        {{ $flag['form']['cta'] ?? 'Open plan' }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ $flag['href'] }}"
                                   class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-[#163d63]">
                                    {{ $flag['cta'] }}
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($closed->isNotEmpty())
        <details class="mt-4 rounded-lg border border-slate-100 bg-slate-50/70 px-4 py-3">
            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-500">Recently closed · {{ $closed->count() }}</summary>
            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                @foreach ($closed as $row)
                    <li>{{ $row['title'] }}@if ($row['when']) · {{ $row['when']->format('d M Y') }} @endif</li>
                @endforeach
            </ul>
        </details>
    @endif
</x-portal.panel>
