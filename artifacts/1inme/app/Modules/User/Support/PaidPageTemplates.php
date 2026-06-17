<?php

namespace App\Modules\User\Support;

/**
 * Bold, vibrant "official.me"-style design templates for the Paid Page
 * link type. Each template is a self-contained palette of design tokens
 * the public renderer (resources/views/public/paid-page.blade.php) turns
 * into CSS custom properties — so the look is fully per-link and never
 * touches the shared /@handle creator-profile view.
 *
 * Tokens (all CSS-ready strings):
 *  - page_bg      : full-page background (gradient / colour)
 *  - hero_bg      : hero banner background (gradient)
 *  - accent       : primary accent (buttons, highlights)
 *  - accent_soft  : translucent accent (chips, rings)
 *  - text         : default body text colour on the page surface
 *  - text_muted   : secondary text colour
 *  - card_bg      : feed card surface
 *  - card_text    : feed card text colour
 *  - radius       : card / button corner radius
 *  - font         : Google font family for headings
 *  - hero_style   : 'glow' | 'wave' | 'grid' | 'spotlight' | 'aurora'
 *
 * `motion` flags whether the template ships ambient animation. The view
 * always wraps motion in `@media (prefers-reduced-motion: no-preference)`.
 */
class PaidPageTemplates
{
    public const DEFAULT_ID = 'aurora';

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            'aurora' => [
                'id'         => 'aurora',
                'name'       => 'Aurora',
                'tagline'    => 'Northern-lights gradients on deep space black.',
                'page_bg'    => 'radial-gradient(120% 120% at 50% 0%, #1b1147 0%, #0a0a18 55%, #05050d 100%)',
                'hero_bg'    => 'linear-gradient(120deg, #7c3aed 0%, #db2777 45%, #06b6d4 100%)',
                'accent'     => '#a855f7',
                'accent_soft'=> 'rgba(168,85,247,0.18)',
                'text'       => '#f5f3ff',
                'text_muted' => 'rgba(245,243,255,0.62)',
                'card_bg'    => 'rgba(255,255,255,0.96)',
                'card_text'  => '#1e1b4b',
                'radius'     => '1.5rem',
                'font'       => 'Space Grotesk',
                'hero_style' => 'aurora',
                'motion'     => true,
            ],
            'sunset' => [
                'id'         => 'sunset',
                'name'       => 'Sunset Blvd',
                'tagline'    => 'Warm Miami-sunset glow with neon edges.',
                'page_bg'    => 'radial-gradient(120% 120% at 50% 0%, #2a0a1f 0%, #160512 60%, #0c0309 100%)',
                'hero_bg'    => 'linear-gradient(120deg, #f97316 0%, #ec4899 50%, #8b5cf6 100%)',
                'accent'     => '#fb7185',
                'accent_soft'=> 'rgba(251,113,133,0.18)',
                'text'       => '#fff1f2',
                'text_muted' => 'rgba(255,241,242,0.6)',
                'card_bg'    => 'rgba(255,255,255,0.97)',
                'card_text'  => '#3b0a2a',
                'radius'     => '1.75rem',
                'font'       => 'Space Grotesk',
                'hero_style' => 'glow',
                'motion'     => true,
            ],
            'electric' => [
                'id'         => 'electric',
                'name'       => 'Electric',
                'tagline'    => 'High-voltage cyan + lime on graphite.',
                'page_bg'    => 'linear-gradient(180deg, #07131a 0%, #04090d 100%)',
                'hero_bg'    => 'linear-gradient(120deg, #22d3ee 0%, #2563eb 45%, #a3e635 100%)',
                'accent'     => '#22d3ee',
                'accent_soft'=> 'rgba(34,211,238,0.18)',
                'text'       => '#ecfeff',
                'text_muted' => 'rgba(236,254,255,0.6)',
                'card_bg'    => 'rgba(255,255,255,0.97)',
                'card_text'  => '#0c2330',
                'radius'     => '1rem',
                'font'       => 'Space Grotesk',
                'hero_style' => 'grid',
                'motion'     => true,
            ],
            'mono' => [
                'id'         => 'mono',
                'name'       => 'Mono Bold',
                'tagline'    => 'Editorial black & white with a single hot accent.',
                'page_bg'    => 'linear-gradient(180deg, #0b0b0c 0%, #050505 100%)',
                'hero_bg'    => 'linear-gradient(120deg, #18181b 0%, #27272a 60%, #f43f5e 140%)',
                'accent'     => '#f43f5e',
                'accent_soft'=> 'rgba(244,63,94,0.18)',
                'text'       => '#fafafa',
                'text_muted' => 'rgba(250,250,250,0.55)',
                'card_bg'    => 'rgba(255,255,255,0.98)',
                'card_text'  => '#111113',
                'radius'     => '0.5rem',
                'font'       => 'Space Grotesk',
                'hero_style' => 'spotlight',
                'motion'     => false,
            ],
            'candy' => [
                'id'         => 'candy',
                'name'       => 'Candy Pop',
                'tagline'    => 'Playful pastel-to-neon bubblegum energy.',
                'page_bg'    => 'radial-gradient(120% 120% at 50% 0%, #2d1b45 0%, #1a1030 55%, #0d0820 100%)',
                'hero_bg'    => 'linear-gradient(120deg, #f472b6 0%, #c084fc 40%, #38bdf8 100%)',
                'accent'     => '#e879f9',
                'accent_soft'=> 'rgba(232,121,249,0.2)',
                'text'       => '#fdf4ff',
                'text_muted' => 'rgba(253,244,255,0.62)',
                'card_bg'    => 'rgba(255,255,255,0.97)',
                'card_text'  => '#3b0764',
                'radius'     => '2rem',
                'font'       => 'Space Grotesk',
                'hero_style' => 'wave',
                'motion'     => true,
            ],
        ];
    }

    /** All template ids (for validation rules). */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    /** Whether the given id is a known template. */
    public static function exists(?string $id): bool
    {
        return $id !== null && array_key_exists($id, self::all());
    }

    /** Resolve a template by id, falling back to the default. */
    public static function get(?string $id): array
    {
        $all = self::all();
        return $all[$id] ?? $all[self::DEFAULT_ID];
    }

    public static function default(): array
    {
        return self::all()[self::DEFAULT_ID];
    }
}
