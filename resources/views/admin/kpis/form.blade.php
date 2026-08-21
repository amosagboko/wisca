@php
    $thresholds = $kpi->config['thresholds'] ?? [];
    $onTrackMin = old('on_track_min', $thresholds['on_track']['min'] ?? 1.0);
    $needsAttentionMin = old('needs_attention_min', $thresholds['needs_attention']['min'] ?? 0.90);
@endphp

<x-portal-layout :title="'Configure '.$kpi->code">
    <x-portal.page-intro
        eyebrow="{{ $kpi->pillar->name }}"
        :title="$kpi->code.' — '.$kpi->name"
        :meta="$kpi->description"
    />

    <x-portal.panel title="KPI configuration">
        <form method="POST" action="{{ route('admin.kpis.update', $kpi) }}" class="space-y-5 max-w-2xl">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="default_target" value="Default target (decimal, e.g. 0.95 = 95%)" />
                <x-text-input id="default_target" name="default_target" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="old('default_target', $kpi->default_target)" required />
                <x-input-error :messages="$errors->get('default_target')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="owner_role" value="Owner role" />
                <select id="owner_role" name="owner_role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($ownerRoles as $value => $label)
                        <option value="{{ $value }}" @selected(old('owner_role', $kpi->owner_role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('owner_role')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="frequency" value="Reporting frequency" />
                <x-text-input id="frequency" name="frequency" type="text" class="block mt-1 w-full" :value="old('frequency', $kpi->frequency)" required placeholder="e.g. fortnightly, termly" />
                <x-input-error :messages="$errors->get('frequency')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="KPI status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $kpi->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-4">
                <p class="text-sm font-semibold text-slate-700">Status thresholds (achievement rate as decimal)</p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="on_track_min" value="On track minimum" />
                        <x-text-input id="on_track_min" name="on_track_min" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="$onTrackMin" required />
                        <x-input-error :messages="$errors->get('on_track_min')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="needs_attention_min" value="Needs attention minimum" />
                        <x-text-input id="needs_attention_min" name="needs_attention_min" type="number" step="0.0001" min="0" max="2" class="block mt-1 w-full" :value="$needsAttentionMin" required />
                        <x-input-error :messages="$errors->get('needs_attention_min')" class="mt-2" />
                    </div>
                </div>
                <p class="text-xs text-slate-500">Below the needs-attention minimum is classified as off track.</p>
            </div>

            @if ($kpi->code === 'AE-02')
                <div>
                    <x-input-label for="pass_mark" value="Pass mark (percent)" />
                    <x-text-input id="pass_mark" name="pass_mark" type="number" step="0.01" min="0" max="100" class="block mt-1 w-full" :value="old('pass_mark', $kpi->config['pass_mark'] ?? 50)" required />
                    <p class="mt-1 text-xs text-slate-500">AE-02 counts a learner as passed when their term exam score is at or above this mark. Missing scores count as not passed against the enrolled roll.</p>
                    <x-input-error :messages="$errors->get('pass_mark')" class="mt-2" />
                </div>
            @endif

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>Save KPI settings</x-primary-button>
                <a href="{{ route('admin.kpis.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
