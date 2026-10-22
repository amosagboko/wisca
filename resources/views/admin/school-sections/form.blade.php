@php
    $isEdit = $section->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit School Section' : 'Add School Section'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit school section' : 'New school section'"
        meta="A section groups classes. The section HOD is the only approver for schemes of work and lesson plans in those classes."
    />

    <x-portal.panel :title="$isEdit ? $section->name : 'Section details'">
        <form method="POST" action="{{ $isEdit ? route('admin.school-sections.update', $section) : route('admin.school-sections.store') }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="name" value="Section name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $section->name)" required placeholder="e.g. EYFS" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="hod_id" value="Head of Department" />
                <select id="hod_id" name="hod_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Unassigned</option>
                    @foreach ($hods as $hod)
                        <option value="{{ $hod->id }}" @selected((string) old('hod_id', $section->hod_id) === (string) $hod->id)>{{ $hod->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Only users with the Head of Department role appear here. Teachers cannot submit a scheme until the class section has a HOD.</p>
                <x-input-error :messages="$errors->get('hod_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $section->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create section' }}</x-primary-button>
                <a href="{{ route('admin.school-sections.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
