<x-portal-layout title="Scheme of Work">
    <x-portal.page-intro
        eyebrow="Appendix C · AE-01"
        :title="$scheme->schoolClass?->name.' — '.$scheme->subject?->name"
        :meta="$scheme->academicSession?->name.' · '.$scheme->term?->name.' · Version '.$scheme->version.' · '.$scheme->processLabel()"
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @if ($canEdit)
                    <a href="{{ route('schemes.edit', $scheme) }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">Edit draft</a>
                @endif
                @if ($canClone)
                    <form method="POST" action="{{ route('schemes.clone', $scheme) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-white/40 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-white/10">Clone as draft</button>
                    </form>
                @endif
            </div>
        </x-slot:actions>
    </x-portal.page-intro>

    @if ($scheme->rejection_reason)
        <div class="portal-enter mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold">Last rejection</p>
            <p class="mt-1">{{ $scheme->rejection_reason }}</p>
            <p class="mt-2 text-xs text-amber-800">
                {{ $scheme->rejectedBy?->name }}
                @if ($scheme->rejected_at)
                    · {{ $scheme->rejected_at->format('d M Y H:i') }}
                @endif
            </p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-portal.panel title="Weekly topics">
                @if ($scheme->topics->isEmpty())
                    <p class="text-sm text-slate-500">No topics recorded on this version.</p>
                @else
                    <div class="overflow-x-auto -mx-5 sm:-mx-6">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left">
                                <tr>
                                    <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Week</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Topic</th>
                                    <th class="px-5 py-3 font-semibold text-slate-600">Learning objectives</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($scheme->topics as $topic)
                                    <tr>
                                        <td class="px-5 sm:px-6 py-3 text-slate-600">{{ $topic->week_number }}</td>
                                        <td class="px-5 py-3 font-medium text-slate-800">{{ $topic->title }}</td>
                                        <td class="px-5 py-3 text-slate-600">
                                            @if ($topic->learning_objectives)
                                                <ul class="list-disc pl-4 space-y-1">
                                                    @foreach ($topic->learning_objectives as $objective)
                                                        <li>{{ $objective }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-portal.panel>
        </div>

        <div class="space-y-6">
            <x-portal.panel title="Evidence">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Prepared by</dt>
                        <dd class="mt-0.5 text-slate-700">{{ $scheme->uploadedBy?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Submitted</dt>
                        <dd class="mt-0.5 text-slate-700">
                            {{ $scheme->submitted_at?->format('d M Y H:i') ?? 'Not submitted' }}
                            @if ($scheme->submittedBy)
                                <span class="text-slate-500"> · {{ $scheme->submittedBy->name }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">HoS approval</dt>
                        <dd class="mt-0.5 text-slate-700">
                            @if ($scheme->hasHosApproval())
                                {{ $scheme->hosApprover?->name }} · {{ $scheme->hos_approved_at?->format('d M Y H:i') }}
                            @else
                                Waiting
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Board approval</dt>
                        <dd class="mt-0.5 text-slate-700">
                            @if ($scheme->hasBoardApproval())
                                {{ $scheme->boardApprover?->name }} · {{ $scheme->board_approved_at?->format('d M Y H:i') }}
                            @else
                                {{ $scheme->hasHosApproval() ? 'Waiting' : 'Blocked until HoS approves' }}
                            @endif
                        </dd>
                    </div>
                    @if ($scheme->replaces)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Replaces</dt>
                            <dd class="mt-0.5">
                                <a href="{{ route('schemes.show', $scheme->replaces) }}" class="font-medium text-[#0f2d4a] hover:underline">Version {{ $scheme->replaces->version }}</a>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Supplementary file</dt>
                        <dd class="mt-0.5">
                            @if ($scheme->file_path)
                                <a href="{{ route('schemes.file', $scheme) }}" class="font-medium text-[#0f2d4a] hover:underline">Download</a>
                            @else
                                <span class="text-slate-500">None</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-portal.panel>

            @if ($canSubmit || $canHosApprove || $canBoardApprove || $canActivate || $canReject || $canDelete)
                <x-portal.panel title="Review actions">
                    <div class="space-y-4">
                        @if ($canSubmit)
                            <form method="POST" action="{{ route('schemes.submit', $scheme) }}">
                                @csrf
                                <x-primary-button>Submit for HoS review</x-primary-button>
                            </form>
                        @endif

                        @if ($canHosApprove)
                            <form method="POST" action="{{ route('schemes.approve', $scheme) }}">
                                @csrf
                                <x-primary-button>Record HoS approval</x-primary-button>
                            </form>
                        @endif

                        @if ($canBoardApprove)
                            <form method="POST" action="{{ route('schemes.approve', $scheme) }}">
                                @csrf
                                <x-primary-button>Record Board approval</x-primary-button>
                            </form>
                        @endif

                        @if ($canActivate)
                            <form method="POST" action="{{ route('schemes.activate', $scheme) }}">
                                @csrf
                                <x-primary-button>Activate this version</x-primary-button>
                            </form>
                        @endif

                        @if ($canReject)
                            <form method="POST" action="{{ route('schemes.reject', $scheme) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <x-input-label for="rejection_reason" value="Rejection reason" />
                                    <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('rejection_reason') }}</textarea>
                                    <x-input-error :messages="$errors->get('rejection_reason')" class="mt-2" />
                                </div>
                                <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-red-700 transition hover:bg-red-50">Return to draft</button>
                            </form>
                        @endif

                        @if ($canDelete)
                            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-red-800">This action is permanent</p>
                                <p class="mt-1 text-xs text-red-900">
                                    Deleting version {{ $scheme->version }} removes this draft and its weekly topics. This cannot be undone.
                                    Active and archived schemes stay in history. Use this only when the version was created in error.
                                </p>
                                <form method="POST" action="{{ route('schemes.destroy', $scheme) }}" class="mt-3"
                                      onsubmit="return confirm('This permanently deletes version {{ $scheme->version }} of {{ $scheme->schoolClass?->name }} — {{ $scheme->subject?->name }}. Topics on this version cannot be recovered. Continue?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-red-700 transition hover:bg-red-50">Permanently delete this version</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </x-portal.panel>
            @endif
        </div>
    </div>
</x-portal-layout>
