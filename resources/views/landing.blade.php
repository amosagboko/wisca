<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $schoolName }} — {{ $landing['meta_title_suffix'] }}</title>
    <meta name="description" content="{{ $schoolName }} {{ $landing['meta_description'] }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-serif-4:600,700|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @keyframes land-rise {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes land-fade {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes land-ken {
            from { transform: scale(1.06); }
            to { transform: scale(1); }
        }
        .land-rise { animation: land-rise 0.85s cubic-bezier(0.22, 1, 0.36, 1) both; }
        .land-rise-2 { animation: land-rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.12s both; }
        .land-rise-3 { animation: land-rise 0.95s cubic-bezier(0.22, 1, 0.36, 1) 0.22s both; }
        .land-fade { animation: land-fade 1.1s ease-out both; }
        .land-ken { animation: land-ken 12s ease-out both; }
        @media (prefers-reduced-motion: reduce) {
            .land-rise, .land-rise-2, .land-rise-3, .land-fade, .land-ken {
                animation: none !important;
            }
        }
    </style>
</head>
<body class="font-sans text-slate-900 antialiased">
    <header class="relative min-h-[100svh] overflow-hidden bg-[#0f2d4a]">
        <div class="absolute inset-0">
            <img
                src="{{ $heroUrl }}"
                alt="{{ $landing['hero_image_alt'] }} at {{ $schoolName }}"
                class="land-ken h-full w-full object-cover object-center"
            >
            <div class="absolute inset-0 bg-gradient-to-r from-[#0a1f33]/92 via-[#0f2d4a]/78 to-[#0f2d4a]/35"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-[#0a1f33]/70 via-transparent to-[#0a1f33]/25"></div>
        </div>

        <div class="relative z-10 mx-auto flex min-h-[100svh] w-full max-w-6xl flex-col px-6 py-8 sm:px-10 lg:px-12">
            <nav class="land-fade flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="h-11 w-auto max-w-[3.5rem] object-contain sm:h-12">
                    @endif
                    <p class="truncate font-display text-lg font-semibold tracking-tight text-white sm:text-xl">{{ $schoolName }}</p>
                </div>
                <a
                    href="{{ route('login') }}"
                    class="shrink-0 text-sm font-semibold text-white/90 underline-offset-4 transition hover:text-white hover:underline"
                >
                    {{ $landing['nav_sign_in_label'] }}
                </a>
            </nav>

            <div class="flex flex-1 flex-col justify-end pb-16 pt-24 sm:pb-20 sm:pt-28 lg:max-w-2xl">
                <div class="land-rise flex flex-col items-start gap-5">
                    @if ($landing['show_logo_in_hero'] && $logoUrl)
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ $schoolName }}"
                            class="h-20 w-auto max-w-[11rem] object-contain sm:h-24 sm:max-w-[13rem]"
                        >
                    @endif
                    <p class="font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $schoolName }}</p>
                </div>

                <h1 class="land-rise-2 mt-8 font-display text-[2.15rem] font-semibold leading-[1.15] tracking-tight text-white sm:text-4xl lg:text-[2.85rem]">
                    {{ $landing['hero_headline'] }}
                </h1>

                <p class="land-rise-2 mt-5 max-w-xl text-base leading-relaxed text-white/85 sm:text-lg">
                    {{ $landing['hero_supporting'] }}
                </p>

                <div class="land-rise-3 mt-9 flex flex-wrap items-center gap-4">
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center bg-white px-6 py-3.5 text-xs font-semibold uppercase tracking-[0.16em] text-[#0f2d4a] transition hover:bg-slate-100"
                    >
                        {{ $landing['hero_primary_cta'] }}
                    </a>
                    @if ($landing['show_pillars_section'])
                        <a
                            href="#pillars"
                            class="inline-flex items-center text-sm font-medium text-white/80 underline-offset-4 transition hover:text-white hover:underline"
                        >
                            {{ $landing['hero_secondary_cta'] }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </header>

    @if ($landing['show_pillars_section'])
        <section id="pillars" class="relative overflow-hidden bg-[#eef2f6]">
            <div class="pointer-events-none absolute inset-0 opacity-[0.45]" style="background-image: radial-gradient(#0f2d4a12 1px, transparent 1px); background-size: 22px 22px;"></div>
            <div class="relative mx-auto max-w-6xl px-6 py-20 sm:px-10 sm:py-28 lg:px-12">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#0f2d4a]/55">{{ $landing['pillars_eyebrow'] }}</p>
                <h2 class="mt-3 max-w-2xl font-display text-3xl font-semibold tracking-tight text-[#0f2d4a] sm:text-4xl">
                    {{ $landing['pillars_title'] }}
                </h2>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-slate-600">
                    {{ $landing['pillars_intro'] }}
                </p>

                <div class="mt-14 grid gap-12 border-t border-[#0f2d4a]/15 pt-12 sm:grid-cols-3 sm:gap-10">
                    @foreach ($landing['pillars'] as $pillar)
                        <div>
                            <p class="font-display text-5xl font-semibold text-[#0f2d4a]/20">{{ $pillar['number'] }}</p>
                            <h3 class="mt-3 font-display text-xl font-semibold text-[#0f2d4a]">{{ $pillar['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $pillar['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($landing['show_invite_section'])
        <section class="bg-[#0f2d4a]">
            <div class="mx-auto flex max-w-6xl flex-col items-start gap-8 px-6 py-16 sm:flex-row sm:items-end sm:justify-between sm:px-10 sm:py-20 lg:px-12">
                <div class="max-w-xl">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        {{ $landing['invite_title'] }}
                    </h2>
                    <p class="mt-4 text-base leading-relaxed text-white/75">
                        {{ $landing['invite_body'] }}
                    </p>
                </div>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex shrink-0 items-center bg-white px-6 py-3.5 text-xs font-semibold uppercase tracking-[0.16em] text-[#0f2d4a] transition hover:bg-slate-100"
                >
                    {{ $landing['invite_cta'] }}
                </a>
            </div>
        </section>
    @endif

    <footer class="border-t border-slate-200 bg-[#f7f8fa]">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-6 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-10 lg:px-12">
            <div class="flex items-center gap-3">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="h-8 w-auto object-contain">
                @endif
                <p class="text-sm font-medium text-[#0f2d4a]">{{ $schoolName }}</p>
            </div>
            <div class="text-xs text-slate-500 sm:text-right">
                <p>&copy; {{ now()->year }} {{ filled($landing['footer_copyright_owner'] ?? null) ? $landing['footer_copyright_owner'] : $schoolName }}</p>
                <p class="mt-0.5">{{ $landing['footer_tagline'] }}</p>
            </div>
        </div>
    </footer>
</body>
</html>
