@php
    $queue = $approval_queue ?? ['tabs' => collect(), 'default' => 'schemes', 'empty' => true];
    $tabs = collect($queue['tabs'] ?? []);
    $defaultTab = $queue['default'] ?? 'schemes';
    $f = $hodFilters ?? [];
@endphp

<x-portal.panel title="Action Queue" subtitle="Schemes of Work and lesson plans awaiting your approve or return decision.">
    <div x-data="{ tab: @js(request('queue_tab', $defaultTab)) }">
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($tabs as $tab)
                <button type="button"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold transition sm:text-[13px]"
                        :class="tab === @js($tab['key']) ? 'border-[#0f2d4a] bg-[#0f2d4a] text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                        @click="tab = @js($tab['key'])">
                    {{ $tab['label'] }}
                    <span class="ml-1 font-medium opacity-80">({{ $tab['total'] }})</span>
                </button>
            @endforeach
        </div>

        @foreach ($tabs as $tab)
            @php
                $reviewHref = route($tab['review_route'], array_filter([
                    'session_id' => $f['sessionId'] ?? null,
                    'term_id' => ($f['termId'] ?? 0) ?: null,
                ]));
            @endphp
            <div x-show="tab === @js($tab['key'])" x-cloak>
                @if ($tab['items']->isEmpty())
                    <p class="text-sm font-medium text-emerald-800">All caught up ✓</p>
                    <p class="mt-1 text-sm text-slate-500">No submitted {{ strtolower($tab['label']) }} are waiting for review.</p>
                @else
                    <p class="mb-4 text-xs text-slate-500">
                        Showing {{ $tab['items']->count() }} of {{ $tab['total'] }}
                        · 20-hour warning · 24-hour review target from submission time
                    </p>
                    <div class="space-y-3">
                        @foreach ($tab['items'] as $item)
                            @include('dashboard.partials.hod-queue-item', ['item' => $item, 'cta' => 'Open'])
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        @if ($tab['more'] > 0)
                            <p class="text-xs text-slate-500">{{ $tab['more'] }} more on the review list.</p>
                        @else
                            <span></span>
                        @endif
                        <a href="{{ $reviewHref }}"
                           class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                            {{ $tab['review_cta'] }}
                        </a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-portal.panel>
