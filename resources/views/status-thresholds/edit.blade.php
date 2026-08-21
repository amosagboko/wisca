<x-portal-layout title="Status Thresholds">
    <x-portal.page-intro
        eyebrow="Board configuration"
        title="Status Thresholds"
        :meta="'Excel three-scale rules for '.$school->name.' — changeable without a deploy.'"
    />

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <x-portal.panel title="Edit threshold values" subtitle="Achievement-rate decimals (1.0 = 100% of target). Saving updates KPI, pillar, and school health labels on the Executive Dashboard.">
        <form method="POST" action="{{ route('status-thresholds.update') }}" class="space-y-8 max-w-3xl">
            @csrf
            @method('PUT')

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-5 space-y-4">
                <div>
                    <h3 class="font-display text-lg font-semibold text-[#0f2d4a]">A. KPI status</h3>
                    <p class="text-sm text-slate-500">Labels: ON TRACK / NEEDS ATTENTION / OFF TRACK</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="kpi_on_track" value="On track minimum" />
                        <x-text-input id="kpi_on_track" name="kpi_on_track" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="old('kpi_on_track', $thresholds['kpi']['on_track'])" required />
                        <x-input-error :messages="$errors->get('kpi_on_track')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="kpi_needs_attention" value="Needs attention minimum" />
                        <x-text-input id="kpi_needs_attention" name="kpi_needs_attention" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="old('kpi_needs_attention', $thresholds['kpi']['needs_attention'])" required />
                        <x-input-error :messages="$errors->get('kpi_needs_attention')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-5 space-y-4">
                <div>
                    <h3 class="font-display text-lg font-semibold text-[#0f2d4a]">B. Pillar headline</h3>
                    <p class="text-sm text-slate-500">Labels: EXCEEDING / ON TRACK / NEEDS ATTENTION</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="pillar_exceeding" value="Exceeding minimum" />
                        <x-text-input id="pillar_exceeding" name="pillar_exceeding" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="old('pillar_exceeding', $thresholds['pillar_headline']['exceeding'])" required />
                        <x-input-error :messages="$errors->get('pillar_exceeding')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="pillar_on_track" value="On track minimum" />
                        <x-text-input id="pillar_on_track" name="pillar_on_track" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="old('pillar_on_track', $thresholds['pillar_headline']['on_track'])" required />
                        <x-input-error :messages="$errors->get('pillar_on_track')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-5 space-y-4">
                <div>
                    <h3 class="font-display text-lg font-semibold text-[#0f2d4a]">C. Overall health</h3>
                    <p class="text-sm text-slate-500">Labels: HEALTHY / SATISFACTORY / CRITICAL</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="health_healthy" value="Healthy minimum" />
                        <x-text-input id="health_healthy" name="health_healthy" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="old('health_healthy', $thresholds['overall_health']['healthy'])" required />
                        <x-input-error :messages="$errors->get('health_healthy')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="health_satisfactory" value="Satisfactory minimum" />
                        <x-text-input id="health_satisfactory" name="health_satisfactory" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="old('health_satisfactory', $thresholds['overall_health']['satisfactory'])" required />
                        <x-input-error :messages="$errors->get('health_satisfactory')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <x-primary-button>Save thresholds</x-primary-button>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Back to dashboard</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
