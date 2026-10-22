<x-portal-layout title="Overview">
    {{-- Session switcher --}}
    @if ($allSessions->count() > 1)
        <form method="GET" action="{{ route('dashboard') }}" class="mb-6 flex items-center gap-3">
            <x-input-label for="teacher_session" value="Session" class="shrink-0" />
            <select id="teacher_session" name="session_id" onchange="this.form.submit()"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>
    @endif

    <x-portal.page-intro
        eyebrow="Teacher · Overview"
        title="My Week"
        :meta="'Week '.$week_number.($term ? ' of '.$term->name : '').' · '.$session->name.'. Do the next piece of work; HOD reviews the evidence.'"
    />

    @include('dashboard.partials.teacher-actions')
    @include('dashboard.partials.teacher-week')
    @include('dashboard.partials.teacher-performance')
    @include('dashboard.partials.teacher-activity-drawer')

    <x-portal.quicklog-fab :actions="$quicklog_actions ?? []" />
</x-portal-layout>
