@php
    $drawer = $termly_drawer ?? ['items' => collect(), 'due_count' => 0, 'current_week' => 1];
    $items = collect($drawer['items'] ?? []);
    $due = (int) ($drawer['due_count'] ?? 0);
@endphp

<details class="portal-enter mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,45,74,0.04)]">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-gradient-to-r from-slate-50/90 to-white px-5 py-4 sm:px-6 [&::-webkit-details-marker]:hidden">
        <div>
            <h3 class="font-display text-lg font-semibold text-[#0f2d4a]">Termly Drawer</h3>
            <p class="mt-0.5 text-sm text-slate-500">Scheduled term activities. These do not appear in Weekly Pulse.</p>
        </div>
        @if ($due > 0)
            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $due }} due</span>
        @endif
    </summary>
    <div class="space-y-3 border-t border-slate-100 px-5 py-5 sm:px-6">
        @foreach ($items as $item)
            <a href="{{ $item['href'] }}" class="block rounded-xl border border-slate-200 px-4 py-3 hover:bg-slate-50">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $item['code'] }}</p>
                        <p class="mt-1 font-medium text-[#0f2d4a]">{{ $item['label'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $item['note'] }}</p>
                    </div>
                    <div class="text-right">
                        @if ($item['value'])
                            <p class="font-display text-lg font-semibold text-[#0f2d4a]">{{ $item['value'] }}</p>
                        @endif
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                            'bg-amber-100 text-amber-800' => $item['status'] === 'due',
                            'bg-slate-100 text-slate-600' => $item['status'] !== 'due',
                        ])>{{ $item['badge'] }}</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</details>
