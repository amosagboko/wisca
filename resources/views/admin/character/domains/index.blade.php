<x-portal-layout title="Character Domains">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-02"
        title="Character domains"
        meta="Define the 7 character areas assessed each term. Learners rated Secure (3) or Exemplary (4) on ALL active domains count toward CE-02."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.character-domains.create') }}"
           class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add domain
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel :title="'Character domains ('.$domains->count().')'">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Domain</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Pass level</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Order</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($domains as $domain)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <p class="font-medium text-slate-800">{{ $domain->name }}</p>
                                @if ($domain->code)
                                    <p class="text-xs text-slate-400">{{ $domain->code }}</p>
                                @endif
                                @if ($domain->description)
                                    <p class="text-xs text-slate-500 mt-0.5">{{ Str::limit($domain->description, 80) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ $domain->labelForLevel($domain->passing_level) }} ({{ $domain->passing_level }})
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $domain->display_order }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $domain->status === 'active',
                                    'bg-slate-100 text-slate-600'     => $domain->status === 'inactive',
                                ])>{{ ucfirst($domain->status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.character-domains.edit', $domain) }}"
                                   class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.character-domains.destroy', $domain) }}"
                                      class="inline" onsubmit="return confirm('Delete this domain?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">
                                No character domains configured. The spec recommends 7 — Faith, Integrity, Excellence, Service, Respect, Responsibility, Compassion.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
