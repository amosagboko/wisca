@php $isEdit = $type->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Digital Ethics Audit Type' : 'New Digital Ethics Audit Type'">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-03"
        :title="$isEdit ? 'Edit digital ethics audit type' : 'New digital ethics audit type'"
        meta="Set up the categories used when auditing digital submissions for AI and tech integrity."
    />

    <x-portal.panel :title="$isEdit ? $type->name : 'Audit type details'" class="max-w-2xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.digital-ethics-audit-types.update', $type) : route('admin.digital-ethics-audit-types.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name *" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full"
                              :value="old('name', $type->name)" required placeholder="e.g. AI disclosure check" />
            </div>

            <div>
                <x-input-label for="code" value="Short code" />
                <x-text-input id="code" name="code" type="text" class="block mt-1 w-full"
                              :value="old('code', $type->code)" placeholder="e.g. AI" maxlength="20" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $type->description) }}</textarea>
            </div>

            <div class="flex flex-wrap gap-6">
                <div>
                    <x-input-label for="display_order" value="Display order" />
                    <x-text-input id="display_order" name="display_order" type="number" min="0"
                                  class="block mt-1 w-32" :value="old('display_order', $type->display_order ?? 0)" />
                </div>

                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           @checked((bool) old('is_active', $type->is_active ?? true))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Active
                </label>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create type' }}</x-primary-button>
                <a href="{{ route('admin.digital-ethics-audit-types.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
