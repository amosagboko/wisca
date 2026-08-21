@php $isEdit = $area->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Digital Competency Area' : 'New Digital Competency Area'">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-04"
        :title="$isEdit ? 'Edit competency area' : 'New competency area'"
        meta="Set up a proficiency matrix area. Staff count toward DI-04 when rated at or above the pass level on all active areas."
    />

    <x-portal.panel :title="$isEdit ? $area->name : 'Area details'" class="max-w-2xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.digital-competency-areas.update', $area) : route('admin.digital-competency-areas.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name *" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full"
                              :value="old('name', $area->name)" required placeholder="e.g. LMS tool integration" />
            </div>

            <div>
                <x-input-label for="code" value="Short code" />
                <x-text-input id="code" name="code" type="text" class="block mt-1 w-full"
                              :value="old('code', $area->code)" placeholder="e.g. LMS" maxlength="20" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $area->description) }}</textarea>
            </div>

            <div class="flex flex-wrap gap-6">
                <div>
                    <x-input-label for="passing_level" value="Passing level *" />
                    <select id="passing_level" name="passing_level" required
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ([1,2,3,4] as $level)
                            <option value="{{ $level }}" @selected((int) old('passing_level', $area->passing_level ?? 3) === $level)>
                                Level {{ $level }} — {{ \App\Models\DigitalCompetencyArea::LEVEL_LABELS[$level] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="display_order" value="Display order" />
                    <x-text-input id="display_order" name="display_order" type="number" min="0"
                                  class="block mt-1 w-32" :value="old('display_order', $area->display_order ?? 0)" />
                </div>
                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           @checked((bool) old('is_active', $area->is_active ?? true))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Active
                </label>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create area' }}</x-primary-button>
                <a href="{{ route('admin.digital-competency-areas.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
