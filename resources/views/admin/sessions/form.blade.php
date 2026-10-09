@php
    $isEdit = $session->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Session' : 'Add Session'">
    <x-portal.page-intro
        eyebrow="Configuration"
        :title="$isEdit ? 'Edit academic session' : 'New academic session'"
        meta="Create a school year and, as System Admin, activate it immediately. No Head of School or Board approval is required."
    />

    <x-portal.panel :title="$isEdit ? $session->name : 'Session details'">
        <form method="POST" action="{{ $isEdit ? route('admin.sessions.update', $session) : route('admin.sessions.store') }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="name" value="Session name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $session->name)" required placeholder="e.g. 2025/2026" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_date" value="Start date" />
                    <x-text-input id="start_date" name="start_date" type="date" class="block mt-1 w-full" :value="old('start_date', optional($session->start_date)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="end_date" value="End date" />
                    <x-text-input id="end_date" name="end_date" type="date" class="block mt-1 w-full" :value="old('end_date', optional($session->end_date)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                </div>
            </div>

            @unless ($isEdit)
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3" x-data="{ activate: true }">
                    <label class="inline-flex items-start gap-3">
                        <input type="checkbox" name="activate" value="1" x-model="activate" class="mt-1 rounded border-gray-300 text-[#0f2d4a] focus:ring-[#0f2d4a]" checked>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">Activate this session now</span>
                            <span class="mt-1 block text-xs text-slate-500">Makes it the current school year. A First Term is created from these dates so the period can open. You can add Second Term and Third Term afterwards. Admin does not need approval.</span>
                        </span>
                    </label>
                    <div x-cloak x-show="activate">
                        <x-input-label for="first_term_name" value="First term name" />
                        <x-text-input id="first_term_name" name="first_term_name" type="text" class="block mt-1 w-full" :value="old('first_term_name', 'First Term')" />
                    </div>
                </div>
            @endunless

            @if ($isEdit)
                <p class="text-sm text-slate-600">
                    Status: <span class="font-medium capitalize">{{ $session->status ?? 'upcoming' }}</span>
                    @if ($session->is_current)
                        · current
                    @endif
                </p>
            @endif

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create and activate session' }}</x-primary-button>
                <a href="{{ route('admin.sessions.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
