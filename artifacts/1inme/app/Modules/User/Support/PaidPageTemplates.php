<?php

namespace App\Modules\User\Support;

/**
 * Rich, biolink-grade design templates for the Paid Page / "Bizs Profile"
 * link type. Each template is a self-contained palette of design tokens the
 * public renderer (resources/views/public/paid-page.blade.php) turns into CSS
 * custom properties — so the look is fully per-link and never touches the
 * shared /@handle creator-profile view.
 *
 * Tokens (all CSS-ready strings unless noted):
 *  - id / name / tagline / category : metadata (category drives the picker groups)
 *  - page_bg          : full-page base background (gradient / colour)
 *  - hero_bg          : hero banner background (gradient)
 *  - accent           : primary accent (buttons, highlights)
 *  - accent_soft      : translucent accent (chips, rings)
 *  - text             : default body text colour on the page surface
 *  - text_muted       : secondary text colour
 *  - card_bg          : feed card surface
 *  - card_text        : feed card text colour
 *  - card_border      : feed card border colour
 *  - card_muted       : muted text inside cards (timestamps, meta)
 *  - card_input_bg    : comment / reply input background
 *  - card_input_border: comment / reply input border
 *  - card_glass       : (bool) whether the card surface is a translucent glass panel
 *  - radius           : card / button corner radius (rem)
 *  - font             : Google font family for headings
 *  - hero_style       : 'glow' | 'wave' | 'grid' | 'spotlight' | 'aurora' (hero ambient)
 *  - bg_pattern       : page-level animated layer — 'none' | 'aurora' | 'mesh' |
 *                       'waves' | 'grid' | 'particles' | 'blobs' | 'noise' |
 *                       'spotlight' | 'rays' | 'orbs'
 *  - bg_image         : optional relative public asset path for an image background
 *  - bg_video         : optional relative public asset path for a looping video background
 *  - bg_overlay       : optional overlay (over image/video) for legibility
 *  - motion           : (bool) whether the template ships ambient animation
 *
 * The view always wraps motion in `@media (prefers-reduced-motion: no-preference)`.
 */
class PaidPageTemplates
{
    public const DEFAULT_ID = 'aurora';

    /**
     * Grouped, ordered template catalog. Each group is a labelled category
     * shown as its own section in the editor picker.
     *
     * @return list<array{key:string,label:string,icon:string,templates:list<array<string,mixed>>}>
     */
    public static function categories(): array
    {
        $groups = [
            ['gradient', 'Gradient', 'fa-droplet', self::gradientThemes()],
            ['neon', 'Neon', 'fa-bolt', self::neonThemes()],
            ['minimal', 'Minimal', 'fa-minus', self::minimalThemes()],
            ['nature', 'Nature', 'fa-mountain-sun', self::natureThemes()],
            ['dark', 'Dark', 'fa-moon', self::darkThemes()],
            ['playful', 'Playful', 'fa-ice-cream', self::playfulThemes()],
            ['luxury', 'Luxury', 'fa-gem', self::luxuryThemes()],
            ['animated', 'Animated', 'fa-wand-magic-sparkles', self::animatedThemes()],
            ['retro', 'Retro', 'fa-compact-disc', self::retroThemes()],
            ['glass', 'Glass', 'fa-layer-group', self::glassThemes()],
        ];

        $out = [];
        foreach ($groups as [$key, $label, $icon, $themes]) {
            $built = [];
            foreach ($themes as $t) {
                $t['category'] = $key;
                $built[] = self::make($t);
            }
            $out[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'templates' => $built];
        }
        return $out;
    }

    /**
     * Flat id => template map (single source of truth for resolution).
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        $all = [];
        foreach (self::categories() as $group) {
            foreach ($group['templates'] as $t) {
                $all[$t['id']] = $t;
            }
        }
        return $all;
    }

    /** All template ids (for validation rules). */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    /* ── Theme groups ──────────────────────────────────────────────── */

    private static function gradientThemes(): array
    {
        return [
            ['id' => 'aurora', 'name' => 'Aurora', 'tagline' => 'Northern-lights gradients on deep space black.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #1b1147 0%, #0a0a18 55%, #05050d 100%)',
             'hero_bg' => 'linear-gradient(120deg, #7c3aed 0%, #db2777 45%, #06b6d4 100%)',
             'accent' => '#a855f7', 'radius' => '1.5rem', 'bg_pattern' => 'aurora'],
            ['id' => 'sunset', 'name' => 'Sunset Blvd', 'tagline' => 'Warm Miami-sunset glow with neon edges.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #2a0a1f 0%, #160512 60%, #0c0309 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f97316 0%, #ec4899 50%, #8b5cf6 100%)',
             'accent' => '#fb7185', 'radius' => '1.75rem', 'bg_pattern' => 'orbs'],
            ['id' => 'liquid', 'name' => 'Liquid Violet', 'tagline' => 'Marbled liquid-paint gradients.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #221049 0%, #0d0721 60%, #06030f 100%)',
             'hero_bg' => 'linear-gradient(120deg, #6d28d9 0%, #c026d3 50%, #4f46e5 100%)',
             'accent' => '#c084fc', 'radius' => '1.75rem', 'bg_pattern' => 'mesh',
             'bg_image' => 'paid-page-bg/liquid-violet.png'],
            ['id' => 'tropic', 'name' => 'Tropic', 'tagline' => 'Teal-to-lime island energy.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #052e2b 0%, #04120f 60%, #020807 100%)',
             'hero_bg' => 'linear-gradient(120deg, #14b8a6 0%, #22d3ee 45%, #a3e635 100%)',
             'accent' => '#2dd4bf', 'radius' => '1.5rem', 'bg_pattern' => 'mesh'],
            ['id' => 'flamingo', 'name' => 'Flamingo', 'tagline' => 'Hot pink melting into tangerine.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #2c0a1d 0%, #170410 60%, #0b0207 100%)',
             'hero_bg' => 'linear-gradient(120deg, #ec4899 0%, #f43f5e 50%, #fb923c 100%)',
             'accent' => '#fb7185', 'radius' => '2rem', 'bg_pattern' => 'orbs'],
            ['id' => 'ultramarine', 'name' => 'Ultramarine', 'tagline' => 'Electric blue into deep indigo.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #0a1330 0%, #060b1c 60%, #03060f 100%)',
             'hero_bg' => 'linear-gradient(120deg, #2563eb 0%, #4f46e5 50%, #7c3aed 100%)',
             'accent' => '#60a5fa', 'radius' => '1.5rem', 'bg_pattern' => 'mesh'],
            ['id' => 'peachy', 'name' => 'Peachy', 'tagline' => 'Soft peach-to-rose warmth.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #2a1418 0%, #170a0d 60%, #0b0506 100%)',
             'hero_bg' => 'linear-gradient(120deg, #fda4af 0%, #fdba74 50%, #fcd34d 100%)',
             'accent' => '#fb923c', 'radius' => '1.75rem', 'bg_pattern' => 'orbs',
             'card_text' => '#3a1410'],
            ['id' => 'spectrum', 'name' => 'Spectrum', 'tagline' => 'Full prismatic rainbow on black.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #14091f 0%, #0a0512 60%, #050308 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f43f5e 0%, #f59e0b 25%, #22c55e 50%, #06b6d4 75%, #8b5cf6 100%)',
             'accent' => '#e879f9', 'radius' => '1.5rem', 'bg_pattern' => 'mesh'],
        ];
    }

    private static function neonThemes(): array
    {
        return [
            ['id' => 'electric', 'name' => 'Electric', 'tagline' => 'High-voltage cyan + lime on graphite.',
             'page_bg' => 'linear-gradient(180deg, #07131a 0%, #04090d 100%)',
             'hero_bg' => 'linear-gradient(120deg, #22d3ee 0%, #2563eb 45%, #a3e635 100%)',
             'accent' => '#22d3ee', 'radius' => '1rem', 'bg_pattern' => 'grid'],
            ['id' => 'neon-bokeh', 'name' => 'Neon Bokeh', 'tagline' => 'Dreamy city-light bokeh after dark.',
             'page_bg' => 'linear-gradient(180deg, #0c0617 0%, #060310 100%)',
             'hero_bg' => 'linear-gradient(120deg, #ec4899 0%, #8b5cf6 50%, #22d3ee 100%)',
             'accent' => '#e879f9', 'radius' => '1.25rem', 'bg_pattern' => 'particles',
             'bg_image' => 'paid-page-bg/neon-bokeh.png'],
            ['id' => 'cyber-lime', 'name' => 'Cyber Lime', 'tagline' => 'Acid lime on jet black circuitry.',
             'page_bg' => 'linear-gradient(180deg, #0a0f06 0%, #050803 100%)',
             'hero_bg' => 'linear-gradient(120deg, #a3e635 0%, #22c55e 50%, #06b6d4 100%)',
             'accent' => '#a3e635', 'radius' => '0.75rem', 'bg_pattern' => 'grid'],
            ['id' => 'magenta-volt', 'name' => 'Magenta Volt', 'tagline' => 'Magenta + cyan high-contrast neon.',
             'page_bg' => 'linear-gradient(180deg, #120615 0%, #08030a 100%)',
             'hero_bg' => 'linear-gradient(120deg, #d946ef 0%, #ec4899 50%, #22d3ee 100%)',
             'accent' => '#d946ef', 'radius' => '1rem', 'bg_pattern' => 'rays'],
            ['id' => 'acid', 'name' => 'Acid', 'tagline' => 'Toxic green-yellow glow.',
             'page_bg' => 'linear-gradient(180deg, #0d0f05 0%, #060802 100%)',
             'hero_bg' => 'linear-gradient(120deg, #bef264 0%, #facc15 50%, #4ade80 100%)',
             'accent' => '#bef264', 'radius' => '0.85rem', 'bg_pattern' => 'particles'],
            ['id' => 'vapor-neon', 'name' => 'Vapor Neon', 'tagline' => 'Pink-and-blue vaporwave glow.',
             'page_bg' => 'linear-gradient(180deg, #0d0820 0%, #060312 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f472b6 0%, #818cf8 50%, #22d3ee 100%)',
             'accent' => '#f472b6', 'radius' => '1.1rem', 'bg_pattern' => 'rays'],
        ];
    }

    private static function minimalThemes(): array
    {
        return [
            ['id' => 'mono', 'name' => 'Mono Bold', 'tagline' => 'Editorial black & white with a hot accent.',
             'page_bg' => 'linear-gradient(180deg, #0b0b0c 0%, #050505 100%)',
             'hero_bg' => 'linear-gradient(120deg, #18181b 0%, #27272a 60%, #f43f5e 140%)',
             'accent' => '#f43f5e', 'radius' => '0.5rem', 'bg_pattern' => 'spotlight', 'motion' => false],
            ['id' => 'noir-grain', 'name' => 'Noir Grain', 'tagline' => 'Matte charcoal with a filmic grain.',
             'page_bg' => 'linear-gradient(180deg, #0c0c0d 0%, #060606 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1c1c1f 0%, #2a2a2e 70%, #404046 120%)',
             'accent' => '#e5e7eb', 'radius' => '0.75rem', 'bg_pattern' => 'noise',
             'bg_image' => 'paid-page-bg/noir-grain.png', 'motion' => false],
            ['id' => 'slate', 'name' => 'Slate', 'tagline' => 'Cool architectural greys.',
             'page_bg' => 'linear-gradient(180deg, #0f1419 0%, #080b0e 100%)',
             'hero_bg' => 'linear-gradient(120deg, #334155 0%, #475569 60%, #64748b 120%)',
             'accent' => '#38bdf8', 'radius' => '0.85rem', 'bg_pattern' => 'none', 'motion' => false],
            ['id' => 'ink', 'name' => 'Ink', 'tagline' => 'Pure black, single indigo accent.',
             'page_bg' => 'linear-gradient(180deg, #060608 0%, #020203 100%)',
             'hero_bg' => 'linear-gradient(120deg, #111118 0%, #1a1a26 60%, #6366f1 150%)',
             'accent' => '#818cf8', 'radius' => '0.6rem', 'bg_pattern' => 'spotlight', 'motion' => false],
            ['id' => 'graphite', 'name' => 'Graphite', 'tagline' => 'Soft graphite with warm accent.',
             'page_bg' => 'linear-gradient(180deg, #141210 0%, #0a0908 100%)',
             'hero_bg' => 'linear-gradient(120deg, #292524 0%, #3f3a36 60%, #d97706 140%)',
             'accent' => '#f59e0b', 'radius' => '0.9rem', 'bg_pattern' => 'none', 'motion' => false],
            ['id' => 'steel', 'name' => 'Steel', 'tagline' => 'Blue-grey, calm and tidy.',
             'page_bg' => 'linear-gradient(180deg, #0c1117 0%, #06090d 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1e293b 0%, #334155 60%, #0ea5e9 140%)',
             'accent' => '#0ea5e9', 'radius' => '0.85rem', 'bg_pattern' => 'spotlight', 'motion' => false],
        ];
    }

    private static function natureThemes(): array
    {
        return [
            ['id' => 'aurora-sky', 'name' => 'Aurora Sky', 'tagline' => 'Real aurora ribbons over a night sky.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #06231f 0%, #040f14 60%, #02070a 100%)',
             'hero_bg' => 'linear-gradient(120deg, #10b981 0%, #06b6d4 50%, #8b5cf6 100%)',
             'accent' => '#34d399', 'radius' => '1.5rem', 'bg_pattern' => 'aurora',
             'bg_image' => 'paid-page-bg/aurora-sky.png'],
            ['id' => 'forest-mist', 'name' => 'Forest Mist', 'tagline' => 'Misty pines at first light.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #0a1a15 0%, #060f0c 60%, #030806 100%)',
             'hero_bg' => 'linear-gradient(120deg, #047857 0%, #15803d 55%, #4d7c0f 100%)',
             'accent' => '#4ade80', 'radius' => '1.5rem', 'bg_pattern' => 'none',
             'bg_image' => 'paid-page-bg/forest-mist.png'],
            ['id' => 'ocean-deep', 'name' => 'Deep Ocean', 'tagline' => 'Aerial deep-blue open water.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #06182e 0%, #040d1a 60%, #02070f 100%)',
             'hero_bg' => 'linear-gradient(120deg, #0284c7 0%, #0ea5e9 55%, #06b6d4 100%)',
             'accent' => '#38bdf8', 'radius' => '1.5rem', 'bg_pattern' => 'waves',
             'bg_image' => 'paid-page-bg/ocean-deep.png'],
            ['id' => 'sage', 'name' => 'Sage', 'tagline' => 'Calm botanical greens.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #131c12 0%, #0a0f0a 60%, #050805 100%)',
             'hero_bg' => 'linear-gradient(120deg, #65a30d 0%, #16a34a 55%, #0d9488 100%)',
             'accent' => '#84cc16', 'radius' => '1.5rem', 'bg_pattern' => 'blobs'],
            ['id' => 'sunrise', 'name' => 'Sunrise', 'tagline' => 'Golden-hour over the hills.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #2a1608 0%, #170c05 60%, #0b0603 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f59e0b 0%, #f97316 50%, #ef4444 100%)',
             'accent' => '#fbbf24', 'radius' => '1.75rem', 'bg_pattern' => 'orbs',
             'card_text' => '#3a1d05'],
            ['id' => 'glacier', 'name' => 'Glacier', 'tagline' => 'Icy blues and clean light.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #0a1822 0%, #060e14 60%, #03070a 100%)',
             'hero_bg' => 'linear-gradient(120deg, #67e8f9 0%, #38bdf8 50%, #818cf8 100%)',
             'accent' => '#67e8f9', 'radius' => '1.5rem', 'bg_pattern' => 'mesh'],
        ];
    }

    private static function darkThemes(): array
    {
        return [
            ['id' => 'nebula', 'name' => 'Deep Nebula', 'tagline' => 'Cosmic clouds and distant stars.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #140a2e 0%, #0a0518 60%, #04020c 100%)',
             'hero_bg' => 'linear-gradient(120deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%)',
             'accent' => '#a78bfa', 'radius' => '1.5rem', 'bg_pattern' => 'particles',
             'bg_image' => 'paid-page-bg/nebula.png'],
            ['id' => 'obsidian', 'name' => 'Obsidian', 'tagline' => 'Glossy volcanic black.',
             'page_bg' => 'linear-gradient(180deg, #0a0a0c 0%, #040405 100%)',
             'hero_bg' => 'linear-gradient(120deg, #18181b 0%, #312e81 90%, #6d28d9 140%)',
             'accent' => '#8b5cf6', 'radius' => '1rem', 'bg_pattern' => 'spotlight'],
            ['id' => 'carbon', 'name' => 'Carbon', 'tagline' => 'Technical carbon-fibre dark.',
             'page_bg' => 'linear-gradient(180deg, #0c0d0f 0%, #060708 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1f2937 0%, #111827 60%, #0ea5e9 150%)',
             'accent' => '#22d3ee', 'radius' => '0.85rem', 'bg_pattern' => 'grid'],
            ['id' => 'midnight', 'name' => 'Midnight', 'tagline' => 'Deep navy with a moonlit edge.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #0a1024 0%, #060a17 60%, #03050c 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1e3a8a 0%, #3730a3 60%, #5b21b6 120%)',
             'accent' => '#60a5fa', 'radius' => '1.25rem', 'bg_pattern' => 'orbs'],
            ['id' => 'ember', 'name' => 'Ember', 'tagline' => 'Smouldering coals in the dark.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 100%, #2a0e06 0%, #140603 55%, #070202 100%)',
             'hero_bg' => 'linear-gradient(120deg, #b91c1c 0%, #ea580c 55%, #f59e0b 100%)',
             'accent' => '#f97316', 'radius' => '1.1rem', 'bg_pattern' => 'spotlight'],
            ['id' => 'void', 'name' => 'Void', 'tagline' => 'Pure black with a violet rift.',
             'page_bg' => 'radial-gradient(100% 100% at 50% 50%, #0a0612 0%, #050308 60%, #020103 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1e1b4b 0%, #4c1d95 60%, #7c3aed 120%)',
             'accent' => '#a855f7', 'radius' => '1rem', 'bg_pattern' => 'rays'],
        ];
    }

    private static function playfulThemes(): array
    {
        return [
            ['id' => 'candy', 'name' => 'Candy Pop', 'tagline' => 'Playful pastel-to-neon bubblegum energy.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #2d1b45 0%, #1a1030 55%, #0d0820 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f472b6 0%, #c084fc 40%, #38bdf8 100%)',
             'accent' => '#e879f9', 'radius' => '2rem', 'bg_pattern' => 'waves'],
            ['id' => 'confetti', 'name' => 'Confetti', 'tagline' => 'A party of glowing particles.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #1c1330 0%, #100a1e 60%, #07040f 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f59e0b 0%, #ec4899 50%, #6366f1 100%)',
             'accent' => '#fb7185', 'radius' => '1.75rem', 'bg_pattern' => 'particles',
             'bg_image' => 'paid-page-bg/confetti-glow.png'],
            ['id' => 'bubblegum', 'name' => 'Bubblegum', 'tagline' => 'Sweet pink everything.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #2a0f23 0%, #170818 60%, #0b040c 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f9a8d4 0%, #f472b6 50%, #c084fc 100%)',
             'accent' => '#f472b6', 'radius' => '2rem', 'bg_pattern' => 'blobs'],
            ['id' => 'citrus', 'name' => 'Citrus', 'tagline' => 'Zesty orange and lemon.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #241606 0%, #140c04 60%, #080502 100%)',
             'hero_bg' => 'linear-gradient(120deg, #fde047 0%, #fb923c 50%, #f43f5e 100%)',
             'accent' => '#fbbf24', 'radius' => '1.75rem', 'bg_pattern' => 'orbs',
             'card_text' => '#3a2406'],
            ['id' => 'cotton', 'name' => 'Cotton Candy', 'tagline' => 'Dreamy pastel clouds.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #1b1530 0%, #100c1f 60%, #070510 100%)',
             'hero_bg' => 'linear-gradient(120deg, #a5b4fc 0%, #f0abfc 50%, #99f6e4 100%)',
             'accent' => '#c4b5fd', 'radius' => '2rem', 'bg_pattern' => 'blobs'],
            ['id' => 'arcade', 'name' => 'Arcade', 'tagline' => 'Pixel-bright multiplayer joy.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #15092a 0%, #0c0519 60%, #05020c 100%)',
             'hero_bg' => 'linear-gradient(120deg, #22d3ee 0%, #a3e635 35%, #fbbf24 70%, #f472b6 100%)',
             'accent' => '#34d399', 'radius' => '0.85rem', 'bg_pattern' => 'grid'],
        ];
    }

    private static function luxuryThemes(): array
    {
        return [
            ['id' => 'black-marble', 'name' => 'Black Marble', 'tagline' => 'Black marble veined in real gold.',
             'page_bg' => 'linear-gradient(180deg, #0c0b09 0%, #050504 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1c1917 0%, #3f3622 60%, #b8860b 130%)',
             'accent' => '#d4af37', 'radius' => '0.75rem', 'bg_pattern' => 'none', 'font' => 'Playfair Display',
             'bg_image' => 'paid-page-bg/black-marble-gold.png', 'motion' => false],
            ['id' => 'silk-noir', 'name' => 'Silk Noir', 'tagline' => 'Draped black silk with a gold sheen.',
             'page_bg' => 'linear-gradient(180deg, #0b0a0a 0%, #050404 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1a1717 0%, #2e2622 60%, #c79a3c 140%)',
             'accent' => '#e0bf6a', 'radius' => '1rem', 'bg_pattern' => 'spotlight', 'font' => 'Playfair Display',
             'bg_image' => 'paid-page-bg/silk-noir.png'],
            ['id' => 'emerald-lux', 'name' => 'Emerald', 'tagline' => 'Deep emerald and brushed gold.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #04211a 0%, #021310 60%, #010807 100%)',
             'hero_bg' => 'linear-gradient(120deg, #064e3b 0%, #047857 60%, #caa94f 150%)',
             'accent' => '#34d399', 'radius' => '1rem', 'bg_pattern' => 'spotlight', 'font' => 'Playfair Display'],
            ['id' => 'royal', 'name' => 'Royal', 'tagline' => 'Regal purple trimmed in gold.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #1a0e2e 0%, #0e0719 60%, #060310 100%)',
             'hero_bg' => 'linear-gradient(120deg, #4c1d95 0%, #6d28d9 55%, #c79a3c 150%)',
             'accent' => '#d4af37', 'radius' => '1.1rem', 'bg_pattern' => 'orbs', 'font' => 'Playfair Display'],
            ['id' => 'rose-gold', 'name' => 'Rose Gold', 'tagline' => 'Warm rose-gold on charcoal.',
             'page_bg' => 'linear-gradient(180deg, #14100f 0%, #090706 100%)',
             'hero_bg' => 'linear-gradient(120deg, #4a2c2a 0%, #9c5a4d 60%, #e8b4a0 130%)',
             'accent' => '#e8b4a0', 'radius' => '1.1rem', 'bg_pattern' => 'spotlight', 'font' => 'Playfair Display'],
            ['id' => 'platinum', 'name' => 'Platinum', 'tagline' => 'Cool silver on near-black.',
             'page_bg' => 'linear-gradient(180deg, #0d0e10 0%, #060708 100%)',
             'hero_bg' => 'linear-gradient(120deg, #1f2937 0%, #4b5563 60%, #cbd5e1 140%)',
             'accent' => '#cbd5e1', 'radius' => '0.9rem', 'bg_pattern' => 'none', 'font' => 'Playfair Display', 'motion' => false],
        ];
    }

    private static function animatedThemes(): array
    {
        return [
            ['id' => 'aurora-motion', 'name' => 'Aurora Motion', 'tagline' => 'Living aurora video backdrop.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #06231f 0%, #040f14 60%, #02070a 100%)',
             'hero_bg' => 'linear-gradient(120deg, #10b981 0%, #06b6d4 50%, #8b5cf6 100%)',
             'accent' => '#34d399', 'radius' => '1.5rem', 'bg_pattern' => 'aurora',
             'bg_video' => 'paid-page-bg/aurora-loop.mp4'],
            ['id' => 'liquid-motion', 'name' => 'Liquid Motion', 'tagline' => 'Slowly swirling liquid ink video.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #221049 0%, #0d0721 60%, #06030f 100%)',
             'hero_bg' => 'linear-gradient(120deg, #6d28d9 0%, #c026d3 50%, #4f46e5 100%)',
             'accent' => '#c084fc', 'radius' => '1.75rem', 'bg_pattern' => 'mesh',
             'bg_video' => 'paid-page-bg/liquid-loop.mp4'],
            ['id' => 'particle-field', 'name' => 'Particle Field', 'tagline' => 'Drifting luminous particles.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #0a0a1f 0%, #060612 60%, #03030a 100%)',
             'hero_bg' => 'linear-gradient(120deg, #6366f1 0%, #8b5cf6 50%, #06b6d4 100%)',
             'accent' => '#818cf8', 'radius' => '1.25rem', 'bg_pattern' => 'particles'],
            ['id' => 'wave-pool', 'name' => 'Wave Pool', 'tagline' => 'Endless rolling colour waves.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #061826 0%, #040e17 60%, #02070d 100%)',
             'hero_bg' => 'linear-gradient(120deg, #0ea5e9 0%, #06b6d4 50%, #14b8a6 100%)',
             'accent' => '#22d3ee', 'radius' => '1.5rem', 'bg_pattern' => 'waves'],
            ['id' => 'grid-runner', 'name' => 'Grid Runner', 'tagline' => 'Tron-style scrolling grid.',
             'page_bg' => 'linear-gradient(180deg, #060814 0%, #03040a 100%)',
             'hero_bg' => 'linear-gradient(120deg, #2563eb 0%, #06b6d4 50%, #a3e635 100%)',
             'accent' => '#38bdf8', 'radius' => '0.85rem', 'bg_pattern' => 'grid'],
            ['id' => 'ray-burst', 'name' => 'Ray Burst', 'tagline' => 'Rotating beams of light.',
             'page_bg' => 'radial-gradient(100% 100% at 50% 40%, #160a26 0%, #0b0517 60%, #050209 100%)',
             'hero_bg' => 'linear-gradient(120deg, #7c3aed 0%, #db2777 50%, #f59e0b 100%)',
             'accent' => '#c084fc', 'radius' => '1.25rem', 'bg_pattern' => 'rays'],
            ['id' => 'blob-lava', 'name' => 'Lava Lamp', 'tagline' => 'Morphing lava-lamp blobs.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #200a1a 0%, #110510 60%, #07020a 100%)',
             'hero_bg' => 'linear-gradient(120deg, #db2777 0%, #f97316 50%, #facc15 100%)',
             'accent' => '#fb7185', 'radius' => '1.75rem', 'bg_pattern' => 'blobs'],
        ];
    }

    private static function retroThemes(): array
    {
        return [
            ['id' => 'synthwave', 'name' => 'Synthwave', 'tagline' => 'Neon grid and a setting retro sun.',
             'page_bg' => 'linear-gradient(180deg, #1a0833 0%, #0c041c 55%, #05020c 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f0457e 0%, #b1267e 50%, #5b21b6 100%)',
             'accent' => '#f472b6', 'radius' => '0.85rem', 'bg_pattern' => 'grid',
             'bg_image' => 'paid-page-bg/synthwave.png'],
            ['id' => 'vaporwave', 'name' => 'Vaporwave', 'tagline' => 'Pastel pink-and-cyan nostalgia.',
             'page_bg' => 'linear-gradient(180deg, #14082a 0%, #0a0418 60%, #05020c 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f5a9d6 0%, #c4a3f0 50%, #8ce0e0 100%)',
             'accent' => '#f0abfc', 'radius' => '1rem', 'bg_pattern' => 'rays'],
            ['id' => 'eightbit', 'name' => '8-Bit', 'tagline' => 'Pixel-arcade primary colours.',
             'page_bg' => 'linear-gradient(180deg, #0a0a16 0%, #05050d 100%)',
             'hero_bg' => 'linear-gradient(120deg, #ef4444 0%, #3b82f6 50%, #22c55e 100%)',
             'accent' => '#fbbf24', 'radius' => '0.4rem', 'bg_pattern' => 'grid'],
            ['id' => 'disco', 'name' => 'Disco', 'tagline' => 'Mirror-ball multicolour shimmer.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #15102a 0%, #0b0819 60%, #050310 100%)',
             'hero_bg' => 'linear-gradient(120deg, #a855f7 0%, #ec4899 33%, #f59e0b 66%, #22d3ee 100%)',
             'accent' => '#e879f9', 'radius' => '1.5rem', 'bg_pattern' => 'particles'],
            ['id' => 'sepia', 'name' => 'Sepia', 'tagline' => 'Warm vintage paper tones.',
             'page_bg' => 'linear-gradient(180deg, #1a130b 0%, #0d0905 100%)',
             'hero_bg' => 'linear-gradient(120deg, #92400e 0%, #b45309 55%, #d97706 100%)',
             'accent' => '#f59e0b', 'radius' => '0.85rem', 'bg_pattern' => 'noise', 'font' => 'Playfair Display',
             'card_text' => '#3a2a14', 'motion' => false],
        ];
    }

    private static function glassThemes(): array
    {
        return [
            ['id' => 'frost', 'name' => 'Frost Glass', 'tagline' => 'Frosted panes over icy blue.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #0c1a2e 0%, #060f1c 60%, #03070f 100%)',
             'hero_bg' => 'linear-gradient(120deg, #38bdf8 0%, #818cf8 50%, #22d3ee 100%)',
             'accent' => '#7dd3fc', 'radius' => '1.5rem', 'bg_pattern' => 'mesh', 'dark_card' => true],
            ['id' => 'smoke', 'name' => 'Smoke Glass', 'tagline' => 'Tinted glass over rolling smoke.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #16161a 0%, #0c0c0f 60%, #060607 100%)',
             'hero_bg' => 'linear-gradient(120deg, #475569 0%, #64748b 55%, #94a3b8 100%)',
             'accent' => '#94a3b8', 'radius' => '1.25rem', 'bg_pattern' => 'blobs', 'dark_card' => true],
            ['id' => 'prism', 'name' => 'Prism Glass', 'tagline' => 'Rainbow refraction through glass.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #15102a 0%, #0b0819 60%, #050310 100%)',
             'hero_bg' => 'linear-gradient(120deg, #f472b6 0%, #818cf8 33%, #34d399 66%, #fbbf24 100%)',
             'accent' => '#c4b5fd', 'radius' => '1.5rem', 'bg_pattern' => 'rays', 'dark_card' => true],
            ['id' => 'aqua-glass', 'name' => 'Aqua Glass', 'tagline' => 'Liquid-glass teal serenity.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #062420 0%, #041311 60%, #020807 100%)',
             'hero_bg' => 'linear-gradient(120deg, #14b8a6 0%, #06b6d4 55%, #0ea5e9 100%)',
             'accent' => '#2dd4bf', 'radius' => '1.5rem', 'bg_pattern' => 'waves', 'dark_card' => true],
            ['id' => 'noir-glass', 'name' => 'Noir Glass', 'tagline' => 'Smoked black glass, violet glow.',
             'page_bg' => 'radial-gradient(120% 120% at 50% 0%, #0c0a14 0%, #07050d 60%, #030206 100%)',
             'hero_bg' => 'linear-gradient(120deg, #312e81 0%, #6d28d9 60%, #a855f7 120%)',
             'accent' => '#a78bfa', 'radius' => '1.25rem', 'bg_pattern' => 'spotlight', 'dark_card' => true],
        ];
    }

    /* ── Builders & helpers ────────────────────────────────────────── */

    /**
     * Fill a compact theme spec with sane defaults so every template exposes
     * the full token set the renderer expects.
     *
     * @param array<string,mixed> $s
     * @return array<string,mixed>
     */
    private static function make(array $s): array
    {
        $accent   = $s['accent'] ?? '#a855f7';
        $darkCard = (bool) ($s['dark_card'] ?? false);

        $base = [
            'tagline'     => '',
            'accent_soft' => self::soft($accent, 0.18),
            'text'        => '#f5f7ff',
            'text_muted'  => 'rgba(245,247,255,0.62)',
            'radius'      => '1.25rem',
            'font'        => 'Space Grotesk',
            'hero_style'  => null,
            'bg_pattern'  => 'orbs',
            'bg_image'    => null,
            'bg_video'    => null,
            'bg_overlay'  => null,
            'motion'      => true,
        ];

        if ($darkCard) {
            $base += [
                'card_bg'           => 'rgba(20,22,34,0.55)',
                'card_text'         => '#f5f7ff',
                'card_border'       => 'rgba(255,255,255,0.14)',
                'card_muted'        => 'rgba(245,247,255,0.55)',
                'card_input_bg'     => 'rgba(255,255,255,0.06)',
                'card_input_border' => 'rgba(255,255,255,0.18)',
                'card_glass'        => true,
            ];
        } else {
            $base += [
                'card_bg'           => 'rgba(255,255,255,0.96)',
                'card_text'         => '#15182b',
                'card_border'       => 'rgba(15,23,42,0.08)',
                'card_muted'        => 'rgba(15,23,42,0.55)',
                'card_input_bg'     => '#ffffff',
                'card_input_border' => 'rgba(15,23,42,0.12)',
                'card_glass'        => false,
            ];
        }

        $merged = array_merge($base, $s);
        unset($merged['dark_card']);

        // Derive a hero ambient style from the page pattern if not set.
        if (empty($merged['hero_style'])) {
            $heroMap = [
                'aurora' => 'aurora', 'orbs' => 'glow', 'mesh' => 'glow', 'waves' => 'wave',
                'grid' => 'grid', 'particles' => 'glow', 'blobs' => 'glow', 'noise' => 'spotlight',
                'spotlight' => 'spotlight', 'rays' => 'spotlight', 'none' => 'glow',
            ];
            $merged['hero_style'] = $heroMap[$merged['bg_pattern']] ?? 'glow';
        }

        return $merged;
    }

    /** Build an `rgba()` string from a hex colour + alpha. */
    private static function soft(string $hex, float $alpha): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) < 6) {
            return "rgba(168,85,247,$alpha)";
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r,$g,$b,$alpha)";
    }

    /**
     * A mobile-friendly projection of a template. The Expo app can't parse
     * CSS gradient strings or `rem` units, so we decompose `page_bg` /
     * `hero_bg` into ordered colour stops (for expo-linear-gradient) and
     * convert the radius to pixels. Image/video background paths are resolved
     * to absolute URLs so a future mobile renderer can use them; all other
     * tokens are already RN-compatible colour strings and pass through.
     *
     * @param array<string,mixed> $t A template from self::all()/get().
     * @return array<string,mixed>
     */
    public static function mobileTokens(array $t): array
    {
        return [
            'id'          => $t['id'] ?? self::DEFAULT_ID,
            'name'        => $t['name'] ?? '',
            'category'    => $t['category'] ?? 'gradient',
            'page_colors' => self::extractColors((string) ($t['page_bg'] ?? '')),
            'hero_colors' => self::extractColors((string) ($t['hero_bg'] ?? '')),
            'accent'      => $t['accent'] ?? '#a855f7',
            'accent_soft' => $t['accent_soft'] ?? 'rgba(168,85,247,0.18)',
            'text'        => $t['text'] ?? '#ffffff',
            'text_muted'  => $t['text_muted'] ?? 'rgba(255,255,255,0.62)',
            'card_bg'     => $t['card_bg'] ?? '#ffffff',
            'card_text'   => $t['card_text'] ?? '#1e1b4b',
            'card_glass'  => (bool) ($t['card_glass'] ?? false),
            'radius'      => self::remToPx((string) ($t['radius'] ?? '1rem')),
            'font'        => $t['font'] ?? 'Space Grotesk',
            'hero_style'  => $t['hero_style'] ?? 'glow',
            'bg_image'    => self::resolveBgUrl($t['bg_image'] ?? null),
            'bg_video'    => self::resolveBgUrl($t['bg_video'] ?? null),
            'motion'      => (bool) ($t['motion'] ?? false),
        ];
    }

    /**
     * Pull the ordered colour stops out of a CSS gradient string. Both
     * linear- and radial-gradients are flattened to a simple stop list —
     * the mobile renderer always paints them as a linear gradient, which is
     * a faithful-enough approximation of the web look. Always returns at
     * least two stops so expo-linear-gradient has a valid range.
     *
     * @return list<string>
     */
    private static function extractColors(string $css): array
    {
        preg_match_all('/#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)/', $css, $m);
        $colors = array_values($m[0] ?? []);
        if (count($colors) === 0) {
            return ['#0a0a18', '#05050d'];
        }
        if (count($colors) === 1) {
            $colors[] = $colors[0];
        }
        return $colors;
    }

    /** Convert a CSS `rem` length to integer pixels (1rem = 16px). */
    private static function remToPx(string $rem): int
    {
        return (int) round(((float) $rem) * 16);
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

    /**
     * Overlay an owner-supplied custom image/video background on top of a
     * resolved template. Custom values win over the template's baked media.
     * Values are stored (and returned) as-is — absolute URLs for custom
     * media, relative public paths for bundled media — and the renderer
     * resolves each appropriately.
     *
     * @param array<string,mixed> $template
     * @param array<string,mixed> $settings The link's settings['paid_page'] payload.
     * @return array<string,mixed>
     */
    public static function applyCustomBackground(array $template, array $settings): array
    {
        $img = trim((string) ($settings['bg_image_url'] ?? ''));
        $vid = trim((string) ($settings['bg_video_url'] ?? ''));
        if ($img !== '') {
            $template['bg_image'] = $img;
        }
        if ($vid !== '') {
            $template['bg_video'] = $vid;
        }
        return $template;
    }

    /**
     * Resolve a background media reference to a usable URL. Owner-supplied
     * custom media is stored as an absolute URL (returned as-is); bundled
     * theme media is a relative public path resolved through asset().
     */
    private static function resolveBgUrl(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        return preg_match('#^(https?:)?//#i', $value) ? $value : asset($value);
    }

}
