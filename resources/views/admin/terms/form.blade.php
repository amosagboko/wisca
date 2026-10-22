@php
    $isEdit = $term->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Term' : 'Add Term'">
    <x-portal.page-intro
        eyebrow="Configuration"
        :title="$isEdit ? 'Edit term' : 'New term'"
        meta="Link each term to its parent academic session."
    />

    <x-portal.panel :title="$isEdit ? $term->name : 'Term details'">
        <form method="POST" action="{{ $isEdit ? route('admin.terms.update', $term) : route('admin.terms.store') }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="academic_session_id" value="Academic session" />
                <select id="academic_session_id" name="academic_session_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select session...</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(old('academic_session_id', $term->academic_session_id) == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('academic_session_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="sequence" value="Term" />
                <select id="sequence" name="sequence" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (\App\Services\AcademicPeriodService::TERM_LABELS as $sequence => $label)
                        <option value="{{ $sequence }}" @selected((int) old('sequence', $term->sequence ?: 1) === $sequence)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('sequence')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_date" value="Start date" />
                    <x-text-input id="start_date" name="start_date" type="date" class="block mt-1 w-full" :value="old('start_date', optional($term->start_date)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="end_date" value="End date" />
                    <x-text-input id="end_date" name="end_date" type="date" class="block mt-1 w-full" :value="old('end_date', optional($term->end_date)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                </div>
            </div>

            <p class="text-xs text-slate-500">Terms must be added in order (First, then Second, then Third). Status is {{ $term->status ?? 'upcoming' }}@if ($term->is_current) (current)@endif. Use Academic Period to transition.</p>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create term' }}</x-primary-button>
                <a href="{{ route('admin.terms.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
