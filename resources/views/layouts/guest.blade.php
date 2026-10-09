<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'WISCA PEMS') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=source-serif-4:600,700|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        @php
            $school = auth()->user()?->school ?? \App\Models\School::query()->first();
            $schoolName = $school?->name ?? config('app.name', 'WISCA PEMS');
            $logoUrl = $school?->logoUrl();
        @endphp
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[radial-gradient(ellipse_at_top,_#e8eef5_0%,_#f4f6f8_45%,_#eef1f4_100%)]">
            <div class="text-center">
                <a href="{{ route('landing') }}" class="inline-flex flex-col items-center" title="Back to home">
                    @if ($logoUrl)
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ $schoolName }}"
                            class="h-28 w-auto max-w-[16rem] object-contain sm:h-32"
                        >
                    @else
                        <div class="flex h-28 w-28 items-center justify-center rounded-2xl bg-white ring-1 ring-slate-200 shadow-sm sm:h-32 sm:w-32">
                            <span class="font-display text-3xl font-semibold text-[#0f2d4a]">{{ strtoupper(substr($schoolName, 0, 2)) }}</span>
                        </div>
                    @endif
                    <span class="mt-3 font-display text-lg font-semibold tracking-wide text-[#0f2d4a]">{{ config('app.name', 'WISCA PEMS') }}</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-8 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg border border-slate-200">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
