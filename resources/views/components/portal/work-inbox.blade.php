@props([
    'title',
    'subtitle' => null,
    'items',
    'empty' => 'Nothing due.',
    'grouped' => null,
    'types' => null,
    'paginator' => null,
    'activeType' => '',
    'total' => null,
    'filteredTotal' => null,
])

@php
    $items = collect($items);
    $grouped = $grouped ? collect($grouped) : null;
    $types = $types ? collect($types) : null;
    $chipQuery = request()->except(['inbox_page', 'inbox_type']);
    $allCount = $total ?? $items->count();
@endphp

<x-portal.panel {{ $attributes->merge(['class' => 'mb-6']) }} :title="$title" :subtitle="$subtitle">
    @if ($types && $types->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('dashboard', $chipQuery) }}"
               @class([
                   'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition',
                   'bg-[#0f2d4a] text-white' => $activeType === '',
                   'bg-slate-100 text-slate-700 hover:bg-slate-200' => $activeType !== '',
               ])>All · {{ $allCount }}</a>
            @foreach ($types as $type)
                <a href="{{ route('dashboard', $chipQuery + ['inbox_type' => $type['type']]) }}"
                   @class([
                       'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition',
                       'bg-[#0f2d4a] text-white' => $activeType === $type['type'],
                       'bg-amber-50 text-amber-800 hover:bg-amber-100' => $activeType !== $type['type'] && ($type['overdue'] ?? 0) > 0,
                       'bg-slate-100 text-slate-700 hover:bg-slate-200' => $activeType !== $type['type'] && ($type['overdue'] ?? 0) === 0,
                   ])>
                    {{ $type['label'] }} · {{ $type['count'] }}
                    @if (($type['overdue'] ?? 0) > 0)
                        <span @class([
                            'rounded-full px-1.5 py-0.5 text-[10px] uppercase tracking-wide',
                            'bg-white/20 text-white' => $activeType === $type['type'],
                            'bg-amber-200 text-amber-900' => $activeType !== $type['type'],
                        ])>{{ $type['overdue'] }} overdue</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    @if ($items->isEmpty())
        <p class="text-sm text-slate-500">
            @if ($activeType !== '' && ($filteredTotal ?? 0) === 0 && $allCount > 0)
                No {{ optional($types->firstWhere('type', $activeType))['label'] ?? 'items' }} match the current filters.
            @else
                {{ $empty }}
            @endif
        </p>
    @elseif ($grouped && $grouped->isNotEmpty())
        <div class="space-y-3">
            @foreach ($grouped as $section)
                <details open class="overflow-hidden rounded-xl border border-slate-200">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-slate-50 px-4 py-3 text-left hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                        <div class="min-w-0">
                            <p class="font-semibold text-[#0f2d4a]">{{ $section['label'] }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $section['count'] }} on this page
                                @if (($section['overdue'] ?? 0) > 0)
                                    · {{ $section['overdue'] }} overdue
                                @endif
                            </p>
                        </div>
                    </summary>
                    <div class="space-y-3 border-t border-slate-200 bg-white p-3 sm:p-4">
                        @foreach ($section['groups'] as $group)
                            <div>
                                @if ($group['label'])
                                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $group['label'] }}</p>
                                @endif
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($group['items'] as $task)
                                        <x-portal.work-inbox-row :task="$task" />
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @else
        <ul class="divide-y divide-slate-100 -mx-1">
            @foreach ($items as $task)
                <x-portal.work-inbox-row :task="$task" />
            @endforeach
        </ul>
    @endif

    @if ($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages())
        <div class="mt-4 border-t border-slate-100 pt-4">
            {{ $paginator->onEachSide(1)->links() }}
        </div>
    @endif
</x-portal.panel>
