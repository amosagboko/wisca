<li class="rounded-lg border border-slate-200 px-3 py-2">
    <p class="font-medium text-slate-800">
        {{ $session->name }}
        @if ($session->is_current)
            <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-emerald-800">Current</span>
        @endif
    </p>
    <p class="text-xs capitalize text-slate-500">{{ $session->status }}</p>
    <ul class="mt-2 space-y-1 text-xs text-slate-600">
        @foreach ($session->terms as $term)
            <li>
                {{ $term->name }}
                · {{ $term->start_date->format('d M Y') }} — {{ $term->end_date->format('d M Y') }}
                · {{ $term->status }}
                @if ($term->is_current)
                    · current
                @endif
            </li>
        @endforeach
    </ul>
</li>
