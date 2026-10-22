@php
    $rows = collect($teaching_week ?? []);
@endphp

<x-portal.panel class="mt-6" title="My Teaching Week" subtitle="One row per assignment. Planned, delivered, and verified stay separate.">
    @if ($rows->isEmpty())
        <p class="text-sm text-slate-500">No teaching assignments for this session.</p>
    @else
        <div class="space-y-2">
            @foreach ($rows as $row)
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Week {{ $row['week'] }}</p>
                        <p class="mt-1 font-medium text-[#0f2d4a]">{{ $row['class'] }} · {{ $row['subject'] }}</p>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $row['topics_line'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            @if ($row['scheme_href'])
                                <a href="{{ $row['scheme_href'] }}" class="font-medium text-[#0f2d4a] hover:underline">{{ $row['scheme_label'] }}</a>
                            @else
                                {{ $row['scheme_label'] }}
                            @endif
                            · Plan: {{ $row['plan_status'] }}
                            · Delivery: {{ $row['delivery_status'] }}
                        </p>
                        @if (! empty($row['rejection_reason']))
                            <p class="mt-1 text-xs text-amber-800">{{ $row['rejection_reason'] }}</p>
                        @endif
                    </div>
                    @if (! empty($row['href']) && ! empty($row['cta']))
                        <a href="{{ $row['href'] }}"
                           class="inline-flex shrink-0 items-center rounded-lg bg-[#0f2d4a] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-[#163d63]">
                            {{ $row['cta'] }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <a href="{{ route('schemes.index') }}" class="hover:text-[#0f2d4a]">Schemes</a>
            <a href="{{ route('lesson-plans.index') }}" class="hover:text-[#0f2d4a]">Lesson plans</a>
            <a href="{{ route('coverage-logs.index') }}" class="hover:text-[#0f2d4a]">Coverage logs</a>
        </div>
    @endif
</x-portal.panel>
