@php
    $actions = collect($my_actions ?? []);
    $total = (int) ($my_actions_total ?? $actions->count());
    $paginator = $my_actions_paginator ?? null;
@endphp

<x-portal.panel title="My Actions" subtitle="Work you can complete now. HOD review status lives on Teaching Week.">
    @if ($actions->isEmpty())
        <p class="text-sm font-medium text-emerald-800">You are all caught up.</p>
        <p class="mt-1 text-sm text-slate-500">Nothing from your assignments needs a teacher action this week.</p>
    @else
        @if ($total > $actions->count())
            <p class="mb-3 text-xs text-slate-500">Showing {{ $actions->count() }} of {{ $total }}</p>
        @endif
        <div class="space-y-2">
            @foreach ($actions as $task)
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $task['type_label'] }}</span>
                            @if (! empty($task['class']))
                                <span class="text-xs text-slate-500">{{ $task['class'] }}@if (! empty($task['subject'])) · {{ $task['subject'] }}@endif</span>
                            @endif
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                'bg-red-100 text-red-800' => in_array($task['badge'], ['Overdue', 'Rejected'], true),
                                'bg-amber-100 text-amber-800' => in_array($task['badge'], ['Due', 'Today', 'This week', 'Deliver', 'Catch-up', 'Incomplete', 'Not flagged', 'Draft'], true),
                                'bg-slate-100 text-slate-600' => ! in_array($task['badge'], ['Overdue', 'Rejected', 'Due', 'Today', 'This week', 'Deliver', 'Catch-up', 'Incomplete', 'Not flagged', 'Draft'], true),
                            ])>{{ $task['badge'] }}</span>
                        </div>
                        <p class="mt-1 font-medium text-[#0f2d4a]">{{ $task['title'] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $task['meta'] }}</p>
                    </div>
                    @if (! empty($task['href']) && ! empty($task['cta']))
                        <a href="{{ $task['href'] }}"
                           class="inline-flex shrink-0 items-center rounded-lg bg-[#0f2d4a] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-[#163d63]">
                            {{ $task['cta'] }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
        @if ($paginator)
            <div class="mt-4">{{ $paginator->links() }}</div>
        @endif
    @endif
</x-portal.panel>
