@php $isEdit = $type->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Incident Type' : 'New Incident Type'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-04"
        :title="$isEdit ? 'Edit discipline incident type' : 'New discipline incident type'"
        meta="Set up behavior categories and whether restorative agreements are required by default."
    />

    <x-portal.panel :title="$isEdit ? $type->name : 'Incident type details'" class="max-w-2xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.discipline-incident-types.update', $type) : route('admin.discipline-incident-types.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name *" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full"
                              :value="old('name', $type->name)" required placeholder="e.g. Disruptive behavior" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="code" value="Short code" />
                <x-text-input id="code" name="code" type="text" class="block mt-1 w-full"
                              :value="old('code', $type->code)" placeholder="e.g. DISRUPT" maxlength="20" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="Optional description">{{ old('description', $type->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="restorative_required" value="1"
                           @checked((bool) old('restorative_required', $type->restorative_required ?? true))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Restorative agreement required by default
                </label>

                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           @checked((bool) old('is_active', $type->is_active ?? true))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Active
                </label>
            </div>

            <div>
                <x-input-label for="display_order" value="Display order" />
                <x-text-input id="display_order" name="display_order" type="number" min="0"
                              class="block mt-1 w-32" :value="old('display_order', $type->display_order ?? 0)" />
                <x-input-error :messages="$errors->get('display_order')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create type' }}</x-primary-button>
                <a href="{{ route('admin.discipline-incident-types.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
