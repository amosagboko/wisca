<x-portal-layout title="Partnership Charters">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-07"
        title="Partnership charters"
        meta="Configure the Parent-School Partnership Charter (Appendix K) used for commitment tracking."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.partnership-charters.create') }}"
           class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add charter
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel title="Charters ({{ $charters->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Title</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Version</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Order</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($charters as $charter)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <p class="font-medium text-slate-800">{{ $charter->title }}</p>
                                @if ($charter->content)
                                    <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($charter->content, 90) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $charter->version ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $charter->display_order }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $charter->is_active,
                                    'bg-slate-100 text-slate-600' => ! $charter->is_active,
                                ])>{{ $charter->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.partnership-charters.edit', $charter) }}"
                                   class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.partnership-charters.destroy', $charter) }}"
                                      class="inline" onsubmit="return confirm('Delete this charter?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No partnership charters yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
