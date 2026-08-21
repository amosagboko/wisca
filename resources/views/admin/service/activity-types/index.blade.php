<x-portal-layout title="Service Activity Types">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-03"
        title="Service activity types"
        meta="Define the kinds of outreach, peer tutoring, and campus service that count toward CE-03."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.service-activity-types.create') }}"
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
                        <th class="px-5 py-3 font-semibold text-slate-600">Description</th>
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
                            <td class="px-5 py-3 text-slate-600">{{ $type->description ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $type->display_order }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $type->is_active,
                                    'bg-slate-100 text-slate-600' => ! $type->is_active,
                                ])>{{ $type->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.service-activity-types.edit', $type) }}"
                                   class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.service-activity-types.destroy', $type) }}"
                                      class="inline" onsubmit="return confirm('Delete this service activity type?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">
                                No service activity types yet. Add one to start logging CE-03 hours.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
