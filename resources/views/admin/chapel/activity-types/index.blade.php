<x-portal-layout title="Chapel Activity Types">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-01"
        title="Chapel activity types"
        meta="Define what counts as a Christocentric activity (chapel, assembly, devotion, etc.) and which participation levels qualify toward CE-01."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.chapel-activity-types.create') }}"
           class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add activity type
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel title="Activity types ({{ $types->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name / Code</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Counting levels</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Scope</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Order</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <p class="font-medium text-slate-800">{{ $type->name }}</p>
                                @if ($type->code)
                                    <p class="text-xs text-slate-400">{{ $type->code }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ implode(', ', $type->countingLevels()) }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $type->school_wide ? 'School-wide' : 'Class-specific' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $type->display_order }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $type->is_active,
                                    'bg-slate-100 text-slate-600'     => ! $type->is_active,
                                ])>{{ $type->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.chapel-activity-types.edit', $type) }}"
                                   class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.chapel-activity-types.destroy', $type) }}"
                                      class="inline" onsubmit="return confirm('Delete this activity type?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 sm:px-6 py-8 text-center text-slate-500">
                                No activity types yet. Add one to start scheduling chapel sessions.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>

    <div class="mt-6">
        <a href="{{ route('admin.chapel-sessions.index') }}"
           class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">
            → Manage chapel sessions
        </a>
    </div>
</x-portal-layout>
