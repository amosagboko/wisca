<x-portal-layout title="Planning policy">
    <x-portal.page-intro
        eyebrow="Configuration"
        title="Lesson-plan due day"
        meta="WISCA initial value is Thursday. This setting is consumed when new lesson plans are submitted. Historical due dates are not rewritten."
    />

    <x-portal.panel title="School planning policy">
        <form method="POST" action="{{ route('planning-policy.update') }}" class="max-w-xl space-y-5">
            @csrf
            @method('PUT')
            <div>
                <x-input-label for="lesson_plan_due_weekday" value="Lesson plan due weekday" />
                <select id="lesson_plan_due_weekday" name="lesson_plan_due_weekday" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($weekdays as $value => $label)
                        <option value="{{ $value }}" @selected((int) $weekday === (int) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('lesson_plan_due_weekday')" class="mt-2" />
            </div>
            <x-primary-button>Save policy</x-primary-button>
        </form>
    </x-portal.panel>
</x-portal-layout>
