@php
    $weekNumber = (int) ($accountability['week_number'] ?? $leadership['week_number'] ?? 1);
    $tab = ($accountability['tab'] ?? 'hods') === 'teachers' ? 'teachers' : 'hods';
    $hodsTotal = (int) ($accountability['hods_total'] ?? 0);
    $teachersTotal = (int) ($accountability['teachers_total'] ?? 0);
    $hods = $accountability['hods'] ?? null;
    $teachers = $accountability['teachers'] ?? null;
    $active = $tab === 'teachers' ? $teachers : $hods;
    $activeTotal = $tab === 'teachers' ? $teachersTotal : $hodsTotal;
    $emptyBoth = $hodsTotal === 0 && $teachersTotal === 0;
    $tabQuery = array_filter([
        'session_id' => $session->id ?? request('session_id'),
        'term_id' => ($term->id ?? request('term_id')) ?: null,
        'hods_page' => request('hods_page') ?: null,
        'teachers_page' => request('teachers_page') ?: null,
    ]);
@endphp

<x-portal.panel
    class="mb-6"
    title="Who is behind"
    :subtitle="'Week '.$weekNumber.($term ? ' · '.$term->name : '').'. One row per person. Drill into the existing page — this list does not approve, verify, or change KPI formulas.'"
>
    @if ($emptyBoth)
        <p class="text-sm font-medium text-emerald-800">No one is behind this week.</p>
        <p class="mt-1 text-sm text-slate-500">Submitted work has been cleared and teachers have no open actions.</p>
    @else
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('dashboard', $tabQuery + ['behind' => 'hods']) }}"
               @class([
                   'rounded-lg border px-3 py-2 text-xs font-semibold transition sm:text-[13px]',
                   'border-[#0f2d4a] bg-[#0f2d4a] text-white' => $tab === 'hods',
                   'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $tab !== 'hods',
               ])>
                HODs <span class="ml-1 font-medium opacity-80">({{ $hodsTotal }})</span>
            </a>
            <a href="{{ route('dashboard', $tabQuery + ['behind' => 'teachers']) }}"
               @class([
                   'rounded-lg border px-3 py-2 text-xs font-semibold transition sm:text-[13px]',
                   'border-[#0f2d4a] bg-[#0f2d4a] text-white' => $tab === 'teachers',
                   'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $tab !== 'teachers',
               ])>
                Teachers <span class="ml-1 font-medium opacity-80">({{ $teachersTotal }})</span>
            </a>
        </div>

        @if ($activeTotal === 0)
            <p class="text-sm font-medium text-emerald-800">
                {{ $tab === 'teachers' ? 'No teachers are behind this week.' : 'No HODs are behind this week.' }}
            </p>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($active as $row)
                    <article class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-[#0f2d4a]">{{ $row['name'] }}</p>
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">{{ $row['role'] }}</span>
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                    'bg-red-100 text-red-800' => $row['badge'] === 'Overdue',
                                    'bg-amber-100 text-amber-800' => in_array($row['badge'], ['Waiting on HOD', 'Due', 'Draft', 'Today', 'This week', 'Deliver'], true),
                                    'bg-slate-100 text-slate-600' => ! in_array($row['badge'], ['Overdue', 'Waiting on HOD', 'Due', 'Draft', 'Today', 'This week', 'Deliver'], true),
                                ])>{{ $row['badge'] }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['section'] }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $row['issue'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">Next step: {{ $row['next'] }}</p>
                        </div>
                        <a href="{{ $row['href'] }}"
                           class="inline-flex shrink-0 items-center rounded-lg bg-[#0f2d4a] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-[#163d63]">
                            {{ $row['cta'] }}
                        </a>
                    </article>
                @endforeach
            </div>
            @if ($active instanceof \Illuminate\Contracts\Pagination\Paginator && $active->hasPages())
                <div class="mt-4">{{ $active->links() }}</div>
            @endif
        @endif
    @endif
</x-portal.panel>
