@props(['activity'])

@php
    use App\Support\RoleLabels;
    use App\Support\WiscaNavigation;
    use App\Support\WiscaOperationalCatalog;

    $user = auth()->user();
    $roles = $user ? WiscaNavigation::navRoleNames($user) : [];
    $tabs = $user ? WiscaNavigation::visibleSubs($activity, $user) : [];
    $requested = (string) request()->query('sub', '');
    $onPageTabs = collect($tabs)->filter(fn ($tab) => request()->routeIs($tab['active'] ?? []));
    $activeKey = collect($tabs)->firstWhere('key', $requested)['key']
        ?? ($onPageTabs->first(fn ($tab) => WiscaOperationalCatalog::userOwnsSub($roles, $tab))['key'] ?? null)
        ?? ($onPageTabs->first()['key'] ?? null)
        ?? (collect($tabs)->first(fn ($tab) => WiscaOperationalCatalog::userOwnsSub($roles, $tab))['key'] ?? null)
        ?? ($tabs[0]['key'] ?? '1');
@endphp

@if ($tabs !== [])
    <div {{ $attributes->class(['portal-enter mb-6']) }} x-data="{ tab: @js($activeKey) }">
        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $index => $item)
                @php
                    $owns = WiscaOperationalCatalog::userOwnsSub($roles, $item);
                    $href = WiscaNavigation::itemHref($item);
                    $tabOnThisPage = request()->routeIs($item['active'] ?? []);
                    $tabClass = 'max-w-full rounded-lg border px-3 py-2 text-left text-xs font-medium leading-snug transition sm:text-[13px]';
                @endphp
                @if ($href && ! $tabOnThisPage)
                    <a
                        href="{{ $href.(str_contains($href, '?') ? '&' : '?').'sub='.$item['key'] }}"
                        class="{{ $tabClass }} border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                        title="{{ $item['label'] }}"
                    >
                        <span class="block font-semibold">{{ $index + 1 }}. <span class="font-medium">{{ \Illuminate\Support\Str::limit($item['label'], 52) }}</span></span>
                        @if ($owns)
                            <span class="mt-1 inline-block text-[10px] uppercase tracking-wide opacity-80">Your work</span>
                        @endif
                    </a>
                @else
                    <button
                        type="button"
                        class="{{ $tabClass }}"
                        :class="tab === @js($item['key']) ? 'border-[#0f2d4a] bg-[#0f2d4a] text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                        @click="tab = @js($item['key']); const url = new URL(window.location.href); url.searchParams.set('sub', @js($item['key'])); history.replaceState({}, '', url);"
                        title="{{ $item['label'] }}"
                    >
                        <span class="block font-semibold">{{ $index + 1 }}. <span class="font-medium">{{ \Illuminate\Support\Str::limit($item['label'], 52) }}</span></span>
                        @if ($owns)
                            <span class="mt-1 inline-block text-[10px] uppercase tracking-wide opacity-80">Your work</span>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>

        @foreach ($tabs as $item)
            <div x-show="tab === @js($item['key'])" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-white px-4 py-3 sm:px-5">
                <p class="text-sm font-medium text-slate-800">{{ $item['label'] }}</p>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Target</dt>
                        <dd class="mt-0.5 text-slate-700">{{ $item['target'] ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Frequency</dt>
                        <dd class="mt-0.5 text-slate-700">{{ $item['frequency'] ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Responsibility</dt>
                        <dd class="mt-0.5 text-slate-700">{{ RoleLabels::list($item['roles'] ?? []) ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Evidence</dt>
                        <dd class="mt-0.5 text-slate-700">{{ $item['evidence'] ?: '—' }}</dd>
                    </div>
                </dl>
            </div>
        @endforeach
    </div>
@endif
