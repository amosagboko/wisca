@php $isEdit = $log->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Service Log' : 'Log Service Hours'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-03"
        :title="$isEdit ? 'Edit service log' : 'Log service hours'"
        :meta="$session?->name.($log->term ? ' · '.$log->term->name : '').'. Enter the verified hours for this service activity and submit it for review.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel :title="$isEdit ? $log->title : 'Service log details'" class="max-w-3xl">
        <form method="POST"
              action="{{ $isEdit ? route('service.update', $log) : route('service.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="service_activity_type_id" value="Activity type *" />
                    <select id="service_activity_type_id" name="service_activity_type_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Select type</option>
                        @foreach ($activityTypes as $type)
                            <option value="{{ $type->id }}" @selected((int) old('service_activity_type_id', $log->service_activity_type_id) === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('service_activity_type_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="term_id" value="Term" />
                    <select id="term_id" name="term_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">No term</option>
                        @foreach ($terms as $term)
                            <option value="{{ $term->id }}" @selected((int) old('term_id', $log->term_id) === $term->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('term_id')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="service_date" value="Service date *" />
                    <x-text-input id="service_date" name="service_date" type="date" class="block mt-1 w-full"
                                  :value="old('service_date', optional($log->service_date)->toDateString() ?? now()->toDateString())" required />
                    <x-input-error :messages="$errors->get('service_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="participant_count" value="Participants *" />
                    <x-text-input id="participant_count" name="participant_count" type="number" min="0" class="block mt-1 w-full"
                                  :value="old('participant_count', $log->participant_count ?? 0)" required />
                    <x-input-error :messages="$errors->get('participant_count')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="title" value="Title *" />
                <x-text-input id="title" name="title" type="text" class="block mt-1 w-full"
                              :value="old('title', $log->title)" required placeholder="e.g. Community clean-up at school frontage" />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="Brief details about the service activity">{{ old('description', $log->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="verified_hours" value="Verified hours *" />
                <x-text-input id="verified_hours" name="verified_hours" type="number" step="0.01" min="0" class="block mt-1 w-full"
                              :value="old('verified_hours', $log->verified_hours ?? 0)" required />
                <p class="mt-1 text-xs text-slate-500">Enter the total verified hours contributed by the participating learners for this activity.</p>
                <x-input-error :messages="$errors->get('verified_hours')" class="mt-2" />
            </div>

            @if ($isEdit)
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Current status: <span class="font-semibold">{{ ucfirst($log->status) }}</span>
                    @if ($log->verified_at)
                        <span class="ml-2 text-slate-400">· {{ $log->verified_at->format('d M Y H:i') }}</span>
                    @endif
                </div>
            @endif

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" name="action" value="draft"
                        class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                    Save draft
                </button>
                <x-primary-button name="action" value="submit">Submit for verification</x-primary-button>
                <a href="{{ route('service.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
