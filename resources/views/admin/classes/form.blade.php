@php
    $isEdit = $class->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Class' : 'Add Class'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit class' : 'New class'"
        meta="Classes appear in schemes of work and teacher assignment forms."
    />

    <x-portal.panel :title="$isEdit ? $class->name : 'Class details'">
        <form method="POST" action="{{ $isEdit ? route('admin.classes.update', $class) : route('admin.classes.store') }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="name" value="Class name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $class->name)" required placeholder="e.g. JSS 1A" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="level" value="Level / phase" />
                <x-text-input id="level" name="level" type="text" class="block mt-1 w-full" :value="old('level', $class->level)" required placeholder="e.g. Junior Secondary" />
                <x-input-error :messages="$errors->get('level')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $class->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create class' }}</x-primary-button>
                <a href="{{ route('admin.classes.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
