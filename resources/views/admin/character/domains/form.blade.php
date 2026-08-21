@php $isEdit = $domain->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Domain' : 'New Domain'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-02"
        :title="$isEdit ? 'Edit character domain' : 'New character domain'"
        meta="Set the name, passing threshold, and rubric for this domain. The rubric labels appear on the rating marksheet."
    />

    <x-portal.panel :title="$isEdit ? $domain->name : 'Domain details'" class="max-w-2xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.character-domains.update', $domain) : route('admin.character-domains.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Domain name *" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full"
                              :value="old('name', $domain->name)" required placeholder="e.g. Integrity" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="code" value="Short code" />
                <x-text-input id="code" name="code" type="text" class="block mt-1 w-full"
                              :value="old('code', $domain->code)" placeholder="e.g. INTEGRITY" maxlength="20" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="Brief description of what this domain measures">{{ old('description', $domain->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="passing_level" value="Passing level (counts toward CE-02 numerator) *" />
                <select id="passing_level" name="passing_level" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ([1 => 'Beginning (1)', 2 => 'Developing (2)', 3 => 'Secure (3) — recommended', 4 => 'Exemplary only (4)'] as $val => $label)
                        <option value="{{ $val }}" @selected((int) old('passing_level', $domain->passing_level ?? 3) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('passing_level')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status *" />
                <select id="status" name="status" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="active"   @selected(old('status', $domain->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $domain->status) === 'inactive')>Inactive</option>
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="display_order" value="Display order" />
                <x-text-input id="display_order" name="display_order" type="number" min="0"
                              class="block mt-1 w-32" :value="old('display_order', $domain->display_order ?? 0)" />
                <x-input-error :messages="$errors->get('display_order')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="rubric_json" value="Rubric (JSON)" />
                <p class="mt-1 text-xs text-slate-500">
                    Four objects with <code>level</code> (1–4), <code>label</code>, and <code>description</code>.
                    Leave blank to use the default (Beginning / Developing / Secure / Exemplary).
                </p>
                <textarea id="rubric_json" name="rubric_json" rows="6"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs"
                          placeholder='[{"level":1,"label":"Beginning","description":"..."},...]'>{{ old('rubric_json', $domain->rubric ? json_encode($domain->rubric, JSON_PRETTY_PRINT) : '') }}</textarea>
                <x-input-error :messages="$errors->get('rubric_json')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create domain' }}</x-primary-button>
                <a href="{{ route('admin.character-domains.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
