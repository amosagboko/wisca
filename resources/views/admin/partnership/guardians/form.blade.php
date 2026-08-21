@php $isEdit = $guardian->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Parent/Guardian' : 'New Parent/Guardian'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-07"
        :title="$isEdit ? 'Edit parent/guardian' : 'New parent/guardian'"
        meta="Register a parent or guardian and link them to enrolled learners."
    />

    <x-portal.panel :title="$isEdit ? $guardian->name : 'Parent/guardian details'" class="max-w-3xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.guardians.update', $guardian) : route('admin.guardians.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Full name *" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full"
                              :value="old('name', $guardian->name)" required />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="block mt-1 w-full"
                                  :value="old('email', $guardian->email)" />
                </div>
                <div>
                    <x-input-label for="phone" value="Phone" />
                    <x-text-input id="phone" name="phone" type="text" class="block mt-1 w-full"
                                  :value="old('phone', $guardian->phone)" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="relationship" value="Relationship" />
                    <select id="relationship" name="relationship"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">—</option>
                        @foreach (['mother', 'father', 'guardian', 'other'] as $rel)
                            <option value="{{ $rel }}" @selected(old('relationship', $guardian->relationship) === $rel)>{{ ucfirst($rel) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="status" value="Status *" />
                    <select id="status" name="status" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="active" @selected(old('status', $guardian->status ?? 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $guardian->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div>
                <x-input-label value="Linked learners" />
                <div class="mt-2 max-h-64 overflow-y-auto rounded-lg border border-slate-200 p-3 space-y-2">
                    @forelse ($learners as $learner)
                        <label class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" name="learner_ids[]" value="{{ $learner->id }}"
                                   @checked(in_array($learner->id, old('learner_ids', $selectedLearnerIds), true))
                                   class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span>{{ $learner->name }}@if ($learner->schoolClass) <span class="text-slate-500">· {{ $learner->schoolClass->name }}</span>@endif</span>
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">No enrolled learners found.</p>
                    @endforelse
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create parent/guardian' }}</x-primary-button>
                <a href="{{ route('admin.guardians.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
