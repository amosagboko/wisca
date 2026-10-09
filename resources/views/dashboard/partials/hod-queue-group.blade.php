@php
    $heading = $type === 'teacher'
        ? $items->first()->teacher->name
        : $items->first()->schoolClass->name;
    $sub = $type === 'teacher'
        ? $items->pluck('schoolClass.name')->unique()->sort()->implode(', ')
        : $items->pluck('teacher.name')->unique()->sort()->implode(', ');
    $person = $type === 'teacher' ? $items->first()->teacher : null;
@endphp

<details open class="overflow-hidden rounded-xl border border-slate-200">
    <summary class="flex cursor-pointer list-none items-center gap-3 bg-slate-50 px-4 py-3 text-left hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
        @if ($person)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full">
                <x-user-avatar :user="$person" size="md" />
            </span>
        @else
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#0f2d4a] text-xs font-semibold text-white">{{ strtoupper(substr($heading, 0, 2)) }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-[#0f2d4a]">{{ $heading }}</p>
            <p class="truncate text-xs text-slate-500">{{ $sub }} · {{ $items->count() }} {{ \Illuminate\Support\Str::plural($label, $items->count()) }}</p>
        </div>
    </summary>
    <div class="space-y-3 border-t border-slate-200 bg-slate-50/40 p-3 sm:p-4">
        @foreach ($items as $model)
            @include('dashboard.partials.hod-'.$card.'-card', [$card === 'plan' ? 'plan' : 'log' => $model])
        @endforeach
    </div>
</details>
