<?php

namespace App\Http\Controllers\Admin;

use App\Support\LandingContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ControlPanelController extends AdminController
{
    public function edit(): View
    {
        $school = $this->school();

        return view('admin.control-panel.edit', [
            'school' => $school,
            'landing' => $school->landingContent(),
            'landingHeroUrl' => $school->landingHeroUrl(),
            'hasCustomHero' => filled($school->settings['landing']['hero_image'] ?? null),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $this->school();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],

            'meta_title_suffix' => ['required', 'string', 'max:120'],
            'meta_description' => ['required', 'string', 'max:500'],
            'nav_sign_in_label' => ['required', 'string', 'max:40'],

            'hero_headline' => ['required', 'string', 'max:200'],
            'hero_supporting' => ['required', 'string', 'max:500'],
            'hero_primary_cta' => ['required', 'string', 'max:60'],
            'hero_secondary_cta' => ['required', 'string', 'max:60'],
            'hero_image_alt' => ['required', 'string', 'max:160'],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_hero_image' => ['nullable', 'boolean'],
            'show_logo_in_hero' => ['nullable', 'boolean'],

            'show_pillars_section' => ['nullable', 'boolean'],
            'pillars_eyebrow' => ['required', 'string', 'max:80'],
            'pillars_title' => ['required', 'string', 'max:160'],
            'pillars_intro' => ['required', 'string', 'max:500'],
            'pillars' => ['required', 'array', 'size:3'],
            'pillars.*.number' => ['required', 'string', 'max:8'],
            'pillars.*.title' => ['required', 'string', 'max:120'],
            'pillars.*.body' => ['required', 'string', 'max:400'],

            'show_invite_section' => ['nullable', 'boolean'],
            'invite_title' => ['required', 'string', 'max:200'],
            'invite_body' => ['required', 'string', 'max:500'],
            'invite_cta' => ['required', 'string', 'max:60'],

            'footer_tagline' => ['required', 'string', 'max:160'],
            'footer_copyright_owner' => ['nullable', 'string', 'max:160'],
        ]);

        $baseSlug = Str::slug($validated['name']) ?: 'school';
        $slug = $baseSlug;
        $suffix = 1;
        while (
            $school->newQuery()
                ->where('slug', $slug)
                ->where('id', '!=', $school->id)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        $school->fill([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);
        $school->save();

        if ($request->boolean('remove_logo') && ! $request->hasFile('logo')) {
            $school->deleteLogoFile();
        }

        if ($request->hasFile('logo')) {
            $school->storeLogo($request->file('logo'));
        }

        if ($request->boolean('remove_hero_image') && ! $request->hasFile('hero_image')) {
            $school->deleteLandingHeroFile();
            $school->refresh();
        }

        if ($request->hasFile('hero_image')) {
            $school->storeLandingHero($request->file('hero_image'));
            $school->refresh();
        }

        $settings = $school->settings ?? [];
        $existingHero = $settings['landing']['hero_image'] ?? null;

        $landing = LandingContent::resolve([
            'meta_title_suffix' => $validated['meta_title_suffix'],
            'meta_description' => $validated['meta_description'],
            'nav_sign_in_label' => $validated['nav_sign_in_label'],
            'hero_headline' => $validated['hero_headline'],
            'hero_supporting' => $validated['hero_supporting'],
            'hero_primary_cta' => $validated['hero_primary_cta'],
            'hero_secondary_cta' => $validated['hero_secondary_cta'],
            'hero_image_alt' => $validated['hero_image_alt'],
            'hero_image' => $existingHero,
            'show_logo_in_hero' => $request->boolean('show_logo_in_hero'),
            'show_pillars_section' => $request->boolean('show_pillars_section'),
            'pillars_eyebrow' => $validated['pillars_eyebrow'],
            'pillars_title' => $validated['pillars_title'],
            'pillars_intro' => $validated['pillars_intro'],
            'pillars' => array_values($validated['pillars']),
            'show_invite_section' => $request->boolean('show_invite_section'),
            'invite_title' => $validated['invite_title'],
            'invite_body' => $validated['invite_body'],
            'invite_cta' => $validated['invite_cta'],
            'footer_tagline' => $validated['footer_tagline'],
            'footer_copyright_owner' => $validated['footer_copyright_owner'] ?? '',
        ]);

        // Preserve uploaded hero path (resolve() may null it from defaults merge)
        if ($existingHero) {
            $landing['hero_image'] = $existingHero;
        } else {
            unset($landing['hero_image']);
        }

        $settings['landing'] = $landing;
        $school->forceFill(['settings' => $settings])->save();

        return redirect()
            ->route('admin.control-panel.edit')
            ->with('success', 'Control panel saved. Landing page and school brand are up to date.');
    }
}
