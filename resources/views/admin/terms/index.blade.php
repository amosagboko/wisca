<x-portal-layout title="Terms">
    <x-portal.page-intro
        eyebrow="Configuration"
        title="Terms"
        meta="Terms belong to an academic session and define reporting windows."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.terms.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add term
        </a>
    </div>

    <x-portal.panel title="All terms">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Term</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Session</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Dates</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($terms as $term)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $term->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $term->academicSession->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $term->start_date->format('M j, Y') }} — {{ $term->end_date->format('M j, Y') }}</td>
                            <td class="px-5 py-3 capitalize">{{ $term->status }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.terms.edit', $term) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.terms.destroy', $term) }}" class="inline" onsubmit="return confirm('Delete this term?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No terms configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
