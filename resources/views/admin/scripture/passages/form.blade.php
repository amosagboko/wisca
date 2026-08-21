@php $isEdit = $passage->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Scripture Passage' : 'New Scripture Passage'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-06"
        :title="$isEdit ? 'Edit scripture passage' : 'New scripture passage'"
        meta="Configure the verse reference and text used in scripture mastery assessments."
    />

    <x-portal.panel :title="$isEdit ? $passage->reference : 'Passage details'" class="max-w-3xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.scripture-passages.update', $passage) : route('admin.scripture-passages.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="reference" value="Reference *" />
                <x-text-input id="reference" name="reference" type="text" class="block mt-1 w-full"
                              :value="old('reference', $passage->reference)" required placeholder="e.g. Romans 12:2" />
            </div>

            <div>
                <x-input-label for="verse_text" value="Verse text" />
                <textarea id="verse_text" name="verse_text" rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('verse_text', $passage->verse_text) }}</textarea>
            </div>

            <div>
                <x-input-label for="theme" value="Theme" />
                <x-text-input id="theme" name="theme" type="text" class="block mt-1 w-full"
                              :value="old('theme', $passage->theme)" placeholder="e.g. Renewed mind and transformation" />
            </div>

            <div class="flex flex-wrap gap-6">
                <div>
                    <x-input-label for="display_order" value="Display order" />
                    <x-text-input id="display_order" name="display_order" type="number" min="0"
                                  class="block mt-1 w-32" :value="old('display_order', $passage->display_order ?? 0)" />
                </div>

                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           @checked((bool) old('is_active', $passage->is_active ?? true))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Active
                </label>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create passage' }}</x-primary-button>
                <a href="{{ route('admin.scripture-passages.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
