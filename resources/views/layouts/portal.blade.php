<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? \App\Support\WiscaNavigation::currentTitle() }} · {{ config('app.name', 'WISCA PEMS') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=source-serif-4:600,700|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="font-sans antialiased text-slate-800">
        @php
            $pageTitle = $title ?? \App\Support\WiscaNavigation::currentTitle();
            $pageBreadcrumbs = $breadcrumbs ?? [$pageTitle];
        @endphp
        <div
            class="min-h-screen bg-[radial-gradient(ellipse_at_top,_#e4ebf2_0%,_#eef1f4_45%,_#e8ecf1_100%)]"
            x-data="{
                collapsed: localStorage.getItem('wisca.portal.sidebarCollapsed') === '1',
                mobileOpen: false
            }"
        >
            <div class="hidden lg:block">
                <x-portal.sidebar />
            </div>

            <div
                x-show="mobileOpen"
                x-cloak
                class="fixed inset-0 z-50 lg:hidden"
                @keydown.escape.window="mobileOpen = false"
            >
                <div class="absolute inset-0 bg-slate-900/40" @click="mobileOpen = false"></div>
                <div class="absolute inset-y-0 left-0 w-64 overflow-y-auto bg-[#f7f8fa] shadow-xl" x-data="{ collapsed: false }">
                    <x-portal.sidebar />
                </div>
            </div>

            <div class="transition-all duration-200" :class="collapsed ? 'lg:pl-[72px]' : 'lg:pl-64'">
                <x-portal.topbar :title="$pageTitle" :breadcrumbs="$pageBreadcrumbs" />

                <main class="px-4 py-6 sm:px-6">
                    @if (session('success'))
                        <div class="portal-enter mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="portal-enter mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
