<x-portal-layout title="Digital Competency Areas">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-04"
        title="Digital competency areas"
        meta="Configure the Teacher Digital Skills Proficiency Matrix areas used for staff assessments."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.digital-competency-areas.create') }}"
           class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add area
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel title="Competency areas ({{ $areas->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Area</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Pass level</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Order</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <p class="font-medium text-slate-800">{{ $area->name }}</p>
                                @if ($area->description)
                                    <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($area->description, 90) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">Level {{ $area->passing_level }}+</td>
                            <td class="px-5 py-3 text-slate-600">{{ $area->display_order }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $area->is_active,
                                    'bg-slate-100 text-slate-600' => ! $area->is_active,
                                ])>{{ $area->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.digital-competency-areas.edit', $area) }}"
                                   class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.digital-competency-areas.destroy', $area) }}"
                                      class="inline" onsubmit="return confirm('Delete this area?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No digital competency areas yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
