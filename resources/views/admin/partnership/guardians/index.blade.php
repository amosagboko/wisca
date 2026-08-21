<x-portal-layout title="Parent Registry">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-07"
        title="Parent & guardian registry"
        meta="Maintain the parent body used as the denominator for partnership commitment tracking."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.guardians.create') }}"
           class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add parent/guardian
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel title="Parents & guardians ({{ $guardians->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Contact</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Learners</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guardians as $guardian)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <p class="font-medium text-slate-800">{{ $guardian->name }}</p>
                                @if ($guardian->relationship)
                                    <p class="text-xs text-slate-500 capitalize">{{ $guardian->relationship }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                @if ($guardian->email)
                                    <p>{{ $guardian->email }}</p>
                                @endif
                                @if ($guardian->phone)
                                    <p class="text-xs">{{ $guardian->phone }}</p>
                                @endif
                                @if (! $guardian->email && ! $guardian->phone)
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                @forelse ($guardian->learners as $learner)
                                    <span class="inline-block text-xs">{{ $learner->name }}@if ($learner->schoolClass) ({{ $learner->schoolClass->name }})@endif</span>@if (! $loop->last), @endif
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $guardian->status === 'active',
                                    'bg-slate-100 text-slate-600' => $guardian->status !== 'active',
                                ])>{{ ucfirst($guardian->status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.guardians.edit', $guardian) }}"
                                   class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.guardians.destroy', $guardian) }}"
                                      class="inline" onsubmit="return confirm('Delete this parent/guardian?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No parents or guardians registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
