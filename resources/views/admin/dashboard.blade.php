<x-portal-layout title="Admin Hub">
    <x-portal.page-intro
        eyebrow="System Configuration"
        :title="$school->name"
        meta="Manage academic structure, staff, assignments, and KPI settings for WISCA PEMS."
    />

    @if ($currentSession)
        <div class="portal-enter mb-6 rounded-xl border border-[#0f2d4a]/20 bg-white px-5 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Current academic session</p>
            <p class="mt-1 font-display text-xl font-semibold text-[#0f2d4a]">{{ $currentSession->name }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $currentSession->start_date->format('M j, Y') }} — {{ $currentSession->end_date->format('M j, Y') }}</p>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
        @foreach ([
            ['label' => 'Control Panel', 'count' => 'Brand', 'route' => 'admin.control-panel.edit'],
            ['label' => 'Academic Period', 'count' => $currentSession?->name ?? 'Set up', 'route' => 'academic-period.show'],
            ['label' => 'Academic Sessions', 'count' => $stats['sessions'], 'route' => 'admin.sessions.index'],
            ['label' => 'Terms', 'count' => $stats['terms'], 'route' => 'admin.terms.index'],
            ['label' => 'Classes', 'count' => $stats['classes'], 'route' => 'admin.classes.index'],
            ['label' => 'Subjects', 'count' => $stats['subjects'], 'route' => 'admin.subjects.index'],
            ['label' => 'Staff & Teachers', 'count' => $stats['staff'], 'route' => 'admin.users.index'],
            ['label' => 'Teacher Assignments', 'count' => $stats['assignments'], 'route' => 'admin.assignments.index'],
            ['label' => 'KPI Settings', 'count' => $stats['kpis'], 'route' => 'admin.kpis.index'],
            ['label' => 'Status Thresholds', 'count' => '3 scales', 'route' => 'status-thresholds.edit'],
            ['label' => 'Attendance', 'count' => 'AE-04', 'route' => 'attendance.index'],
        ] as $card)
            <a href="{{ route($card['route']) }}" class="portal-enter group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#0f2d4a]/30 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#0f2d4a]">{{ $card['count'] }}</p>
                <p class="mt-2 text-xs font-semibold uppercase tracking-widest text-slate-500 group-hover:text-[#0f2d4a]">Configure →</p>
            </a>
        @endforeach
    </div>

    <x-portal.panel title="Quick actions" subtitle="Common setup tasks for a new term or session.">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.sessions.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">Create / activate session</a>
            <a href="{{ route('academic-period.show') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Academic period</a>
            <a href="{{ route('admin.terms.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Add term</a>
            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Add staff member</a>
            <a href="{{ route('admin.assignments.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Assign teacher</a>
        </div>
    </x-portal.panel>
</x-portal-layout>
