@php $isEdit = $charter->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Partnership Charter' : 'New Partnership Charter'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-07"
        :title="$isEdit ? 'Edit partnership charter' : 'New partnership charter'"
        meta="Configure the Parent-School Partnership Charter content for commitment tracking."
    />

    <x-portal.panel :title="$isEdit ? $charter->title : 'Charter details'" class="max-w-3xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.partnership-charters.update', $charter) : route('admin.partnership-charters.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="title" value="Title *" />
                <x-text-input id="title" name="title" type="text" class="block mt-1 w-full"
                              :value="old('title', $charter->title)" required placeholder="Parent-School Partnership Charter" />
            </div>

            <div>
                <x-input-label for="content" value="Charter content" />
                <textarea id="content" name="content" rows="8"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('content', $charter->content) }}</textarea>
            </div>

            <div>
                <x-input-label for="version" value="Version" />
                <x-text-input id="version" name="version" type="text" class="block mt-1 w-full"
                              :value="old('version', $charter->version)" placeholder="e.g. 2025/26" />
            </div>

            <div class="flex flex-wrap gap-6">
                <div>
                    <x-input-label for="display_order" value="Display order" />
                    <x-text-input id="display_order" name="display_order" type="number" min="0"
                                  class="block mt-1 w-32" :value="old('display_order', $charter->display_order ?? 0)" />
                </div>

                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           @checked((bool) old('is_active', $charter->is_active ?? true))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Active
                </label>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create charter' }}</x-primary-button>
                <a href="{{ route('admin.partnership-charters.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
