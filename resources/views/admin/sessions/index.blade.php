<x-portal-layout title="Academic Sessions">
    <x-portal.page-intro
        eyebrow="Configuration"
        title="Academic Sessions"
        meta="Create a school year here. System Admin can activate it immediately — no Head of School or Board approval."
    />

    <div class="mb-4 flex justify-end gap-3">
            <a href="{{ route('academic-period.show') }}" class="inline-flex items-center rounded-lg border border-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-50">
                Academic period
            </a>
            <a href="{{ route('admin.sessions.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Create / activate session
            </a>
    </div>

    <x-portal.panel title="All sessions">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Dates</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Terms</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Current</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $session->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $session->start_date->format('M j, Y') }} — {{ $session->end_date->format('M j, Y') }}</td>
                            <td class="px-5 py-3">{{ $session->terms_count }}</td>
                            <td class="px-5 py-3 capitalize">{{ $session->status }}</td>
                            <td class="px-5 py-3">
                                @if ($session->is_current)
                                    <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Current</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.sessions.edit', $session) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                @unless ($session->is_current)
                                    @if ($session->terms_count > 0 && $session->status !== 'closed')
                                        <form method="POST" action="{{ route('admin.sessions.activate', $session) }}" class="inline" onsubmit="return confirm('Activate {{ $session->name }} as the current academic session? The previous current session will be closed. Admin does not need further approval.')">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-emerald-700 hover:underline">Activate</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.sessions.destroy', $session) }}" class="inline" onsubmit="return confirm('Delete this session?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 sm:px-6 py-8 text-center text-slate-500">No academic sessions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
