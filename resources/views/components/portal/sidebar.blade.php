@php
    use App\Models\AcademicSession;
    use App\Support\WiscaNavigation;

    $user = auth()->user();
    $session = AcademicSession::currentForSchool($user->school_id);
    $groups = WiscaNavigation::groups($user);
    $area = WiscaNavigation::areaLabel($user);
    $school = $user->school;
    $schoolName = $school?->name ?? config('app.name', 'WISCA PEMS');
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 flex h-full flex-col border-r border-slate-200 bg-[#f7f8fa] transition-all duration-200"
    :class="collapsed ? 'w-[72px]' : 'w-64'"
>
    <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-4">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-slate-200">
            @if ($school?->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="{{ $schoolName }}" class="h-full w-full object-contain p-0.5">
            @else
                <span class="text-xs font-semibold text-[#0f2d4a]">{{ strtoupper(substr($schoolName, 0, 2)) }}</span>
            @endif
        </div>
        <div x-show="!collapsed" x-cloak class="min-w-0">
            <div class="truncate font-display text-base font-semibold leading-snug text-[#0f2d4a]" title="{{ $schoolName }}">
                {{ $schoolName }}
            </div>
        </div>
    </div>

    <div x-show="!collapsed" x-cloak class="border-b border-slate-200 px-4 py-3">
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
            <p class="text-[11px] uppercase tracking-wide text-slate-400">Academic session</p>
            <p class="truncate text-sm font-medium text-slate-800">{{ $session?->name ?? 'No active session' }}</p>
            @if ($session)
                <p class="text-xs text-emerald-700">{{ ucfirst($session->status) }}</p>
            @endif
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        @foreach ($groups as $group)
            @php
                $isPillar = ($group['type'] ?? 'flat') === 'pillar';
                $groupActive = collect($group['items'] ?? [])->contains(
                    fn ($item) => \App\Support\WiscaNavigation::itemIsActive($item)
                );
            @endphp

            @if ($isPillar)
                <div class="mb-2" x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }">
                    @php
                        $firstHref = \App\Support\WiscaNavigation::itemHref($group['items'][0] ?? []);
                    @endphp
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-semibold transition"
                        :class="open || {{ $groupActive ? 'true' : 'false' }} ? 'bg-slate-100 text-[#0f2d4a]' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        @click="if (collapsed) { {{ $firstHref ? 'window.location.href = '.json_encode($firstHref) : 'open = !open' }} } else { open = !open }"
                        :title="collapsed ? @js($group['label']) : ''"
                    >
                        <x-portal.icon :name="$group['icon'] ?? 'book'" class="h-5 w-5 shrink-0 opacity-80" />
                        <span class="min-w-0 flex-1 truncate" x-show="!collapsed" x-cloak>{{ $group['label'] }}</span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition" x-show="!collapsed" x-cloak :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open && !collapsed" x-cloak class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-2">
                        @foreach ($group['items'] as $activity)
                            @php
                                $activityHref = \App\Support\WiscaNavigation::itemHref($activity);
                                $activityActive = \App\Support\WiscaNavigation::itemIsActive($activity);
                            @endphp
                            <x-portal.nav-item
                                :href="$activityHref"
                                :active="$activityActive"
                                :icon="$activity['icon'] ?? 'document'"
                                :label="$activity['label']"
                            />
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mb-5">
                    <p
                        class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400"
                        x-show="!collapsed"
                        x-cloak
                    >
                        {{ $group['label'] }}
                    </p>
                    <div class="space-y-1">
                        @foreach ($group['items'] as $item)
                            @php
                                $href = \App\Support\WiscaNavigation::itemHref($item);
                                $active = \App\Support\WiscaNavigation::itemIsActive($item);
                            @endphp
                            <x-portal.nav-item
                                :href="$href"
                                :active="$active"
                                :icon="$item['icon'] ?? 'dashboard'"
                                :label="$item['label']"
                            />
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-slate-200 p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-0 py-0 hover:opacity-90" title="My profile">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full">
                    <x-user-avatar :user="$user" size="md" />
                </div>
                <div x-show="!collapsed" x-cloak class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ \App\Support\RoleLabels::label($user->getRoleNames()->first()) }}</p>
                </div>
            </a>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-1">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                :title="collapsed ? 'Sign out' : ''"
            >
                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span x-show="!collapsed" x-cloak>Sign out</span>
            </button>
        </form>
    </div>
</aside>
