@php
    $isEdit = $subject->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Subject' : 'Add Subject'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit subject' : 'New subject'"
        meta="Keep subject codes short for reports and assignment lists."
    />

    <x-portal.panel :title="$isEdit ? $subject->name : 'Subject details'">
        <form method="POST" action="{{ $isEdit ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="name" value="Subject name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $subject->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="code" value="Subject code (optional)" />
                <x-text-input id="code" name="code" type="text" class="block mt-1 w-full" :value="old('code', $subject->code)" placeholder="e.g. MTH" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $subject->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create subject' }}</x-primary-button>
                <a href="{{ route('admin.subjects.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
