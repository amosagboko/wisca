<x-portal-layout title="Clone Scheme of Work">
    <x-portal.page-intro
        eyebrow="Appendix C · AE-01"
        title="Clone to another session"
        meta="Copies weekly topics and learning objectives into a new unsubmitted draft. Approvals, lesson plans, and coverage stay on the source."
    />

    <x-portal.panel title="Destination">
        <dl class="mb-6 grid gap-4 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Source</dt>
                <dd class="mt-1 text-slate-800">{{ $scheme->academicSession?->name }} · {{ $scheme->term?->name }} · v{{ $scheme->version }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Class / subject</dt>
                <dd class="mt-1 text-slate-800">{{ $scheme->schoolClass?->name }} — {{ $scheme->subject?->name }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('schemes.clone-session.store', $scheme) }}" class="space-y-5"
              x-data="{
                  sessionId: @js((string) old('academic_session_id', '')),
                  termId: @js((string) old('term_id', '')),
                  sourceSequence: @js((int) ($scheme->term?->sequence ?? 0)),
                  terms: @js($terms->map(fn ($term) => [
                      'id' => (int) $term->id,
                      'session_id' => (int) $term->academic_session_id,
                      'name' => $term->name,
                      'sequence' => (int) $term->sequence,
                  ])->values()),
                  get matchingTerms() {
                      return this.terms.filter(term => String(term.session_id) === String(this.sessionId) && Number(term.sequence) === Number(this.sourceSequence));
                  },
                  syncTerm() {
                      const list = this.matchingTerms;
                      if (list.some(term => String(term.id) === String(this.termId))) {
                          return;
                      }
                      this.termId = String(list[0]?.id ?? '');
                  }
              }"
              x-init="syncTerm()">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="academic_session_id" value="Destination session" />
                    <select id="academic_session_id" name="academic_session_id" required x-model="sessionId" @change="syncTerm()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select session...</option>
                        @foreach ($sessions as $item)
                            @if ((int) $item->id !== (int) $scheme->academic_session_id || $sessions->count() === 1)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @else
                                <option value="{{ $item->id }}">{{ $item->name }} (source)</option>
                            @endif
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('academic_session_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="term_id" value="Destination term" />
                    <select id="term_id" name="term_id" required x-model="termId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select matching term...</option>
                        @foreach ($terms as $term)
                            <option
                                value="{{ $term->id }}"
                                x-show="String(sessionId) === @js((string) $term->academic_session_id) && Number(sourceSequence) === {{ (int) $term->sequence }}"
                                :disabled="String(sessionId) !== @js((string) $term->academic_session_id) || Number(sourceSequence) !== {{ (int) $term->sequence }}"
                            >{{ $term->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('term_id')" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-500">Only {{ $scheme->term?->name ?? 'the same term' }} can be selected. Cross-term cloning is not allowed.</p>
                </div>
            </div>
            <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
            <div class="flex flex-wrap gap-3">
                <x-primary-button>Clone into destination</x-primary-button>
                <a href="{{ route('schemes.show', $scheme) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
