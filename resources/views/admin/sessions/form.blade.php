@php
    $isEdit = $session->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Session' : 'Add Session'">
    <x-portal.page-intro
        eyebrow="Configuration"
        :title="$isEdit ? 'Edit academic session' : 'New academic session'"
        meta="Sessions group terms and drive reporting periods across WISCA."
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

            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['upcoming', 'active', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $session->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_current" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_current', $session->is_current))>
                    <span class="text-sm text-slate-700">Set as current academic session</span>
                </label>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create session' }}</x-primary-button>
                <a href="{{ route('admin.sessions.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
