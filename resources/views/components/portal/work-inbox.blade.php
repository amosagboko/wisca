@props([
    'title',
    'subtitle' => null,
    'items',
    'empty' => 'Nothing due.',
])

<x-portal.panel {{ $attributes->merge(['class' => 'mb-6']) }} :title="$title" :subtitle="$subtitle">
    @if ($items->isEmpty())
        <p class="text-sm text-slate-500">{{ $empty }}</p>
    @else
        <ul class="divide-y divide-slate-100 -mx-1">
            @foreach ($items as $task)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-800">{{ $task['title'] }}</p>
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                'bg-red-100 text-red-800' => $task['badge'] === 'Rejected',
                                'bg-amber-100 text-amber-800' => in_array($task['badge'], ['Catch-up', 'Overdue', 'Incomplete', 'Not logged', 'No plan', 'Not flagged'], true),
                                'bg-slate-100 text-slate-600' => in_array($task['badge'], ['Waiting on HOD', 'Term closed'], true),
                                'bg-[#0f2d4a]/10 text-[#0f2d4a]' => ! in_array($task['badge'], ['Rejected', 'Catch-up', 'Overdue', 'Incomplete', 'Not logged', 'No plan', 'Not flagged', 'Waiting on HOD', 'Term closed'], true),
                            ])>{{ $task['badge'] }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $task['meta'] }}</p>
                    </div>
                    @if ($task['href'] && $task['cta'])
                        <a href="{{ $task['href'] }}" class="inline-flex shrink-0 items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                            {{ $task['cta'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-portal.panel>
