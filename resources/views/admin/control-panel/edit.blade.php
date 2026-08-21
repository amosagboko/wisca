@php
    $textarea = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
@endphp

<x-portal-layout title="Control Panel">
    <x-portal.page-intro
        eyebrow="Configuration"
        title="Control Panel"
        meta="School brand plus every section of the public landing page."
    />

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.control-panel.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <x-portal.panel title="School profile" subtitle="Name and logo appear in the sidebar, login, and landing page.">
            <div class="space-y-5 max-w-2xl">
                <div>
                    <x-input-label for="name" value="School name" />
                    <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $school->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    @if ($school->logoUrl())
                        <div class="mb-3 flex items-center gap-4">
                            <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }} logo" class="h-16 w-16 rounded-lg object-contain ring-1 ring-slate-200 bg-white p-1">
                            <p class="text-sm text-slate-500">Current logo</p>
                        </div>
                    @endif

                    <x-input-label for="logo" value="School logo" />
                    <x-file-input id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp" button="Upload logo" empty="No logo chosen" />
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG, or WebP · max 2 MB.</p>
                    <x-input-error :messages="$errors->get('logo')" class="mt-2" />

                    @if ($school->logo)
                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                            Remove current logo
                        </label>
                    @endif
                </div>
            </div>
        </x-portal.panel>

        <x-portal.panel title="Landing — page meta" subtitle="Browser title and search description for the public home page.">
            <div class="space-y-5 max-w-2xl">
                <div>
                    <x-input-label for="meta_title_suffix" value="Title suffix (after school name)" />
                    <x-text-input id="meta_title_suffix" name="meta_title_suffix" type="text" class="block mt-1 w-full" :value="old('meta_title_suffix', $landing['meta_title_suffix'])" required />
                    <x-input-error :messages="$errors->get('meta_title_suffix')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="meta_description" value="Meta description" />
                    <textarea id="meta_description" name="meta_description" rows="3" class="{{ $textarea }}" required>{{ old('meta_description', $landing['meta_description']) }}</textarea>
                    <x-input-error :messages="$errors->get('meta_description')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="nav_sign_in_label" value="Top navigation sign-in label" />
                    <x-text-input id="nav_sign_in_label" name="nav_sign_in_label" type="text" class="block mt-1 w-full" :value="old('nav_sign_in_label', $landing['nav_sign_in_label'])" required />
                    <x-input-error :messages="$errors->get('nav_sign_in_label')" class="mt-2" />
                </div>
            </div>
        </x-portal.panel>

        <x-portal.panel title="Landing — hero" subtitle="First viewport: image, headline, supporting line, and buttons.">
            <div class="space-y-5 max-w-2xl">
                <div>
                    <div class="mb-3 overflow-hidden rounded-lg ring-1 ring-slate-200">
                        <img src="{{ $landingHeroUrl }}" alt="Current hero" class="h-40 w-full object-cover">
                    </div>
                    <x-input-label for="hero_image" value="Hero background image" />
                    <x-file-input id="hero_image" name="hero_image" accept=".jpg,.jpeg,.png,.webp" button="Upload hero image" empty="No image chosen" />
                    <p class="mt-1 text-xs text-slate-500">Recommended size: <span class="font-medium text-slate-700">1920 × 1080 px</span> (16:9). JPG, PNG, or WebP · max 4 MB. Leave empty to keep the current image.</p>
                    <x-input-error :messages="$errors->get('hero_image')" class="mt-2" />
                    @if ($hasCustomHero)
                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_hero_image" value="1" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                            Revert to default campus image
                        </label>
                    @endif
                </div>

                <div>
                    <x-input-label for="hero_image_alt" value="Hero image alt text" />
                    <x-text-input id="hero_image_alt" name="hero_image_alt" type="text" class="block mt-1 w-full" :value="old('hero_image_alt', $landing['hero_image_alt'])" required />
                    <x-input-error :messages="$errors->get('hero_image_alt')" class="mt-2" />
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="show_logo_in_hero" value="1" class="rounded border-gray-300 text-[#0f2d4a] shadow-sm focus:ring-[#0f2d4a]" @checked(old('show_logo_in_hero', $landing['show_logo_in_hero']))>
                    Show school logo in the hero brand block
                </label>

                <div>
                    <x-input-label for="hero_headline" value="Headline" />
                    <x-text-input id="hero_headline" name="hero_headline" type="text" class="block mt-1 w-full" :value="old('hero_headline', $landing['hero_headline'])" required />
                    <x-input-error :messages="$errors->get('hero_headline')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="hero_supporting" value="Supporting sentence" />
                    <textarea id="hero_supporting" name="hero_supporting" rows="3" class="{{ $textarea }}" required>{{ old('hero_supporting', $landing['hero_supporting']) }}</textarea>
                    <x-input-error :messages="$errors->get('hero_supporting')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="hero_primary_cta" value="Primary button label" />
                        <x-text-input id="hero_primary_cta" name="hero_primary_cta" type="text" class="block mt-1 w-full" :value="old('hero_primary_cta', $landing['hero_primary_cta'])" required />
                        <x-input-error :messages="$errors->get('hero_primary_cta')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="hero_secondary_cta" value="Secondary link label" />
                        <x-text-input id="hero_secondary_cta" name="hero_secondary_cta" type="text" class="block mt-1 w-full" :value="old('hero_secondary_cta', $landing['hero_secondary_cta'])" required />
                        <x-input-error :messages="$errors->get('hero_secondary_cta')" class="mt-2" />
                    </div>
                </div>
            </div>
        </x-portal.panel>

        <x-portal.panel title="Landing — pillars section" subtitle="The three-pillar block below the hero.">
            <div class="space-y-5 max-w-2xl">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="show_pillars_section" value="1" class="rounded border-gray-300 text-[#0f2d4a] shadow-sm focus:ring-[#0f2d4a]" @checked(old('show_pillars_section', $landing['show_pillars_section']))>
                    Show pillars section on the landing page
                </label>

                <div>
                    <x-input-label for="pillars_eyebrow" value="Eyebrow" />
                    <x-text-input id="pillars_eyebrow" name="pillars_eyebrow" type="text" class="block mt-1 w-full" :value="old('pillars_eyebrow', $landing['pillars_eyebrow'])" required />
                    <x-input-error :messages="$errors->get('pillars_eyebrow')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="pillars_title" value="Section title" />
                    <x-text-input id="pillars_title" name="pillars_title" type="text" class="block mt-1 w-full" :value="old('pillars_title', $landing['pillars_title'])" required />
                    <x-input-error :messages="$errors->get('pillars_title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="pillars_intro" value="Section intro" />
                    <textarea id="pillars_intro" name="pillars_intro" rows="3" class="{{ $textarea }}" required>{{ old('pillars_intro', $landing['pillars_intro']) }}</textarea>
                    <x-input-error :messages="$errors->get('pillars_intro')" class="mt-2" />
                </div>

                @foreach ($landing['pillars'] as $index => $pillar)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pillar {{ $index + 1 }}</p>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label :for="'pillars_'.$index.'_number'" value="Number label" />
                                <x-text-input :id="'pillars_'.$index.'_number'" :name="'pillars['.$index.'][number]'" type="text" class="block mt-1 w-full" :value="old('pillars.'.$index.'.number', $pillar['number'])" required />
                                <x-input-error :messages="$errors->get('pillars.'.$index.'.number')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label :for="'pillars_'.$index.'_title'" value="Title" />
                                <x-text-input :id="'pillars_'.$index.'_title'" :name="'pillars['.$index.'][title]'" type="text" class="block mt-1 w-full" :value="old('pillars.'.$index.'.title', $pillar['title'])" required />
                                <x-input-error :messages="$errors->get('pillars.'.$index.'.title')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label :for="'pillars_'.$index.'_body'" value="Description" />
                            <textarea id="pillars_{{ $index }}_body" name="pillars[{{ $index }}][body]" rows="3" class="{{ $textarea }}" required>{{ old('pillars.'.$index.'.body', $pillar['body']) }}</textarea>
                            <x-input-error :messages="$errors->get('pillars.'.$index.'.body')" class="mt-2" />
                        </div>
                    </div>
                @endforeach
            </div>
        </x-portal.panel>

        <x-portal.panel title="Landing — invite band" subtitle="Dark call-to-action strip before the footer.">
            <div class="space-y-5 max-w-2xl">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="show_invite_section" value="1" class="rounded border-gray-300 text-[#0f2d4a] shadow-sm focus:ring-[#0f2d4a]" @checked(old('show_invite_section', $landing['show_invite_section']))>
                    Show invite section on the landing page
                </label>

                <div>
                    <x-input-label for="invite_title" value="Title" />
                    <x-text-input id="invite_title" name="invite_title" type="text" class="block mt-1 w-full" :value="old('invite_title', $landing['invite_title'])" required />
                    <x-input-error :messages="$errors->get('invite_title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="invite_body" value="Supporting text" />
                    <textarea id="invite_body" name="invite_body" rows="3" class="{{ $textarea }}" required>{{ old('invite_body', $landing['invite_body']) }}</textarea>
                    <x-input-error :messages="$errors->get('invite_body')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="invite_cta" value="Button label" />
                    <x-text-input id="invite_cta" name="invite_cta" type="text" class="block mt-1 w-full" :value="old('invite_cta', $landing['invite_cta'])" required />
                    <x-input-error :messages="$errors->get('invite_cta')" class="mt-2" />
                </div>
            </div>
        </x-portal.panel>

        <x-portal.panel title="Landing — footer" subtitle="Copyright year updates automatically each year.">
            <div class="space-y-5 max-w-2xl">
                <div>
                    <x-input-label for="footer_copyright_owner" value="Copyright owner" />
                    <x-text-input id="footer_copyright_owner" name="footer_copyright_owner" type="text" class="block mt-1 w-full" :value="old('footer_copyright_owner', $landing['footer_copyright_owner'] ?: $school->name)" placeholder="{{ $school->name }}" />
                    <p class="mt-1 text-xs text-slate-500">Shown as <span class="font-medium text-slate-700">© {{ now()->year }} …</span> — the year is generated automatically and does not need editing.</p>
                    <x-input-error :messages="$errors->get('footer_copyright_owner')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="footer_tagline" value="Footer tagline" />
                    <x-text-input id="footer_tagline" name="footer_tagline" type="text" class="block mt-1 w-full" :value="old('footer_tagline', $landing['footer_tagline'])" required />
                    <x-input-error :messages="$errors->get('footer_tagline')" class="mt-2" />
                </div>
            </div>
        </x-portal.panel>

        <div class="flex flex-wrap items-center gap-3">
            <x-primary-button>Save control panel</x-primary-button>
            <a href="{{ route('landing', ['preview' => 1]) }}" target="_blank" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Preview landing page</a>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Back to Admin Hub</a>
        </div>
    </form>
</x-portal-layout>
