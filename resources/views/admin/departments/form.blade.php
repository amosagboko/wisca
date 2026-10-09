@php
    $isEdit = $department->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Department' : 'Add Department'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit department' : 'New department'"
        meta="A department groups subjects. Each HOD only reviews plans and evidence for subjects in their department."
    />

    <x-portal.panel :title="$isEdit ? $department->name : 'Department details'">
        <form method="POST" action="{{ $isEdit ? route('admin.departments.update', $department) : route('admin.departments.store') }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="name" value="Department name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $department->name)" required placeholder="e.g. Sciences" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $department->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create department' }}</x-primary-button>
                <a href="{{ route('admin.departments.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
