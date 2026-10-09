<x-portal-layout title="Classes">
    <x-portal.page-intro
        eyebrow="School Structure"
        title="Classes"
        meta="Define class levels, then add subjects to each class from here or from Subjects."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.classes.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add class
        </a>
    </div>

    <x-portal.panel title="All classes">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Level</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classes as $class)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $class->name }}</td>
                            <td class="px-5 py-3">{{ $class->level }}</td>
                            <td class="px-5 py-3 capitalize">{{ $class->status }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.classes.edit', $class) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <a href="{{ route('admin.subjects.create', ['class_id' => $class->id]) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Add subject</a>
                                <form method="POST" action="{{ route('admin.classes.destroy', $class) }}" class="inline" onsubmit="return confirm('Delete this class?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 sm:px-6 py-8 text-center text-slate-500">No classes configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
