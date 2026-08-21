<x-portal-layout title="Observation">
    <x-portal.page-intro
        eyebrow="Appendix B · AE-06"
        title="Lesson observation"
        :meta="$observation->schoolClass->name.' · '.$observation->subject->name.' · '.$observation->observation_date->format('d M Y')"
    />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Teacher</p>
            <div class="mt-2 flex items-center gap-2">
                <x-user-avatar :user="$observation->teacher" size="xs" />
                <p class="font-semibold text-[#0f2d4a]">{{ $observation->teacher->name }}</p>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Observer</p>
            <p class="mt-2 font-semibold text-[#0f2d4a]">{{ $observation->observer->name }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overall</p>
            <p class="mt-2 font-display text-2xl font-semibold {{ $observation->isCompleted() ? ($observation->isEffective() ? 'text-emerald-700' : 'text-amber-700') : 'text-slate-500' }}">
                {{ $observation->isCompleted() ? $observation->scoreLabel() : 'Scheduled' }}
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</p>
            <p class="mt-2 font-semibold capitalize text-[#0f2d4a]">{{ str_replace('_', ' ', $observation->status) }}</p>
        </div>
    </div>

    <x-portal.panel title="Rubric scores">
        @if (! $observation->isCompleted())
            <p class="text-sm text-slate-500">This visit is scheduled. Scores will appear after the observer completes the checklist.</p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Standard</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Score</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Judgement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($standards as $key => $label)
                            @php $score = (int) ($observation->rubric_scores[$key] ?? 0); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 text-slate-800">{{ $label }}</td>
                                <td class="px-5 py-3 font-medium {{ $score >= 3 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $score ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $scale[$score] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-portal.panel title="Strengths">
            <p class="text-sm text-slate-600 whitespace-pre-line">{{ $observation->strengths ?: '—' }}</p>
        </x-portal.panel>
        <x-portal.panel title="Areas for improvement">
            <p class="text-sm text-slate-600 whitespace-pre-line">{{ $observation->areas_for_improvement ?: '—' }}</p>
        </x-portal.panel>
        <x-portal.panel title="Action plan">
            <p class="text-sm text-slate-600 whitespace-pre-line">{{ $observation->action_plan ?: '—' }}</p>
        </x-portal.panel>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('observations.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Back to list</a>
        @if ($canConduct)
            <a href="{{ route('observations.edit', $observation) }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                {{ $observation->isCompleted() ? 'Revise scores' : 'Complete scoring' }}
            </a>
        @endif
    </div>
</x-portal-layout>
