<?php

namespace App\Support;

/**
 * Default copy and structure for the public landing page.
 * Overrides live in schools.settings['landing'].
 */
class LandingContent
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'meta_title_suffix' => 'Strategy Monitor',
            'meta_description' => 'Strategy monitor — track Academic Excellence, Christocentric Education, and Digital Innovation for the Board.',
            'nav_sign_in_label' => 'Sign in',
            'hero_headline' => 'Strategy made visible for the Board.',
            'hero_supporting' => 'Twenty-one measures across learning, faith, and digital practice — drawn from live school evidence, not end-of-term guesswork.',
            'hero_primary_cta' => 'Enter the portal',
            'hero_secondary_cta' => 'See the three pillars',
            'hero_image' => null,
            'hero_image_alt' => 'Campus grounds',
            'show_logo_in_hero' => true,
            'pillars_eyebrow' => 'What we measure',
            'pillars_title' => 'Three pillars. One school story.',
            'pillars_intro' => 'Every figure on the Executive Dashboard rolls up from live evidence — not slide decks written for a meeting.',
            'pillars' => [
                [
                    'number' => '01',
                    'title' => 'Academic Excellence',
                    'body' => 'Coverage, lessons, attendance, exams, observation, and learner support — eight academic KPIs.',
                ],
                [
                    'number' => '02',
                    'title' => 'Christocentric Education',
                    'body' => 'Chapel, character, service, restorative discipline, scripture, and parent partnership — seven faith KPIs.',
                ],
                [
                    'number' => '03',
                    'title' => 'Digital Innovation',
                    'body' => 'LMS adoption, STEM, ethics, staff competency, e-assessment, and parent portal engagement — six digital KPIs.',
                ],
            ],
            'invite_title' => 'Built for the people who keep the numbers honest.',
            'invite_body' => 'Teachers, officers, coordinators, and leaders each see the work that belongs to them — then the Board sees the whole.',
            'invite_cta' => 'Sign in',
            'footer_tagline' => 'Strategy Monitor · Confidential school use',
            'footer_copyright_owner' => '',
            'show_pillars_section' => true,
            'show_invite_section' => true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        $stored = $stored ?? [];

        $merged = array_replace($defaults, array_intersect_key($stored, $defaults));

        $pillars = $defaults['pillars'];
        if (isset($stored['pillars']) && is_array($stored['pillars'])) {
            foreach ($pillars as $index => $pillar) {
                if (! isset($stored['pillars'][$index]) || ! is_array($stored['pillars'][$index])) {
                    continue;
                }
                $pillars[$index] = array_replace($pillar, array_intersect_key(
                    $stored['pillars'][$index],
                    $pillar
                ));
            }
        }
        $merged['pillars'] = $pillars;

        $merged['show_logo_in_hero'] = filter_var($merged['show_logo_in_hero'], FILTER_VALIDATE_BOOLEAN);
        $merged['show_pillars_section'] = filter_var($merged['show_pillars_section'], FILTER_VALIDATE_BOOLEAN);
        $merged['show_invite_section'] = filter_var($merged['show_invite_section'], FILTER_VALIDATE_BOOLEAN);

        return $merged;
    }
}
