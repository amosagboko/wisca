@props([
    'title' => 'Dashboard',
    'breadcrumbs' => [],
])

@php
    $home = \App\Support\WiscaNavigation::homeRoute();
    $area = \App\Support\WiscaNavigation::areaLabel();
    $session = \App\Models\AcademicSession::currentForSchool(auth()->user()->school_id);
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 lg:hidden"
                @click="mobileOpen = !mobileOpen"
                aria-label="Open menu"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <button
                type="button"
                class="hidden h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 lg:inline-flex"
                @click="collapsed = !collapsed; localStorage.setItem('wisca.portal.sidebarCollapsed', collapsed ? '1' : '0')"
                aria-label="Toggle sidebar"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h16" />
                </svg>
            </button>

            <div class="min-w-0">
                <h1 class="truncate font-display text-lg font-semibold text-[#0f2d4a] sm:text-xl">{{ $title }}</h1>
                @if (count($breadcrumbs))
                    <nav class="mt-0.5 flex flex-wrap items-center gap-1 text-xs text-slate-500">
                        <a href="{{ $home }}" class="hover:text-[#0f2d4a]">{{ $area }}</a>
                        @foreach ($breadcrumbs as $crumb)
                            <span aria-hidden="true">/</span>
                            <span class="text-slate-700">{{ $crumb }}</span>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <div class="hidden rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 sm:inline-flex">
                {{ $session?->name ?? 'No session' }}
            </div>

            <div class="relative" x-data="{ open: false }">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50"
                >
                    <x-user-avatar :user="auth()->user()" size="xs" />
                    <span class="hidden max-w-[10rem] truncate md:inline">{{ auth()->user()->name }}</span>
                </button>

                <div
                    x-show="open"
                    x-cloak
                    @click.outside="open = false"
                    class="absolute right-0 mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                >
                    <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">My profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
