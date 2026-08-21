@php $isEdit = $chapelSession->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Session' : 'Schedule Session'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-01"
        :title="$isEdit ? 'Edit chapel session' : 'Schedule chapel session'"
        meta="Set the date, activity type, and leader. Roll is taken by the chaplain or admin officer on the day."
    />

    <x-portal.panel :title="$isEdit ? $chapelSession->session_date->format('d M Y') : 'Session details'" class="max-w-2xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.chapel-sessions.update', $chapelSession) : route('admin.chapel-sessions.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="chapel_activity_type_id" value="Activity type *" />
                <select id="chapel_activity_type_id" name="chapel_activity_type_id" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— Select —</option>
                    @foreach ($activityTypes as $at)
                        <option value="{{ $at->id }}"
                                @selected(old('chapel_activity_type_id', $chapelSession->chapel_activity_type_id) == $at->id)>
                            {{ $at->name }}{{ $at->code ? ' ('.$at->code.')' : '' }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('chapel_activity_type_id')" class="mt-2" />
                <p class="mt-1 text-xs text-slate-500">
                    Don't see the right type? <a href="{{ route('admin.chapel-activity-types.create') }}" class="text-[#0f2d4a] hover:underline">Add one</a>.
                </p>
            </div>

            <div>
                <x-input-label for="session_date" value="Session date *" />
                <x-text-input id="session_date" name="session_date" type="date"
                              class="block mt-1 w-full"
                              :value="old('session_date', $chapelSession->session_date?->format('Y-m-d') ?? now()->format('Y-m-d'))"
                              required />
                <x-input-error :messages="$errors->get('session_date')" class="mt-2" />
            </div>

            @if ($terms->isNotEmpty())
                <div>
                    <x-input-label for="term_id" value="Term" />
                    <select id="term_id" name="term_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">— Select term (optional) —</option>
                        @foreach ($terms as $t)
                            <option value="{{ $t->id }}"
                                    @selected(old('term_id', $chapelSession->term_id) == $t->id)>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('term_id')" class="mt-2" />
                </div>
            @endif

            <div>
                <x-input-label for="theme" value="Theme" />
                <x-text-input id="theme" name="theme" type="text" class="block mt-1 w-full"
                              :value="old('theme', $chapelSession->theme)" placeholder="e.g. Walking in Faith" />
                <x-input-error :messages="$errors->get('theme')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="scripture_reference" value="Scripture reference" />
                <x-text-input id="scripture_reference" name="scripture_reference" type="text" class="block mt-1 w-full"
                              :value="old('scripture_reference', $chapelSession->scripture_reference)"
                              placeholder="e.g. Proverbs 3:5-6" />
                <x-input-error :messages="$errors->get('scripture_reference')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="led_by" value="Led by" />
                <select id="led_by" name="led_by"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— Select staff member —</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}"
                                @selected(old('led_by', $chapelSession->led_by) == $member->id)>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('led_by')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status *" />
                <select id="status" name="status" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach (['scheduled' => 'Scheduled', 'held' => 'Held', 'cancelled' => 'Cancelled'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('status', $chapelSession->status ?? 'scheduled') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="Optional notes">{{ old('notes', $chapelSession->notes) }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Schedule session' }}</x-primary-button>
                <a href="{{ route('admin.chapel-sessions.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
