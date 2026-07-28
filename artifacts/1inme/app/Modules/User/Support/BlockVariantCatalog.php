<?php

namespace App\Modules\User\Support;

/**
 * Curated catalog of design variants ("skins") that can be applied to a
 * biolink block with a single click. A variant only changes how the block
 * looks — its content/data is untouched.
 *
 * Each variant entry shape:
 *   key         — short stable id, persisted on the block as `_variant`.
 *   name        — display name in the gallery.
 *   tags        — list of style-tag filters (Minimal, Bold, Glass, Dark, ...).
 *   style       — partial map of BiolinkBlock::STYLE_DEFAULTS that gets
 *                 merged into the block's `_style` when applied.
 *   preview     — small CSS hints used to render the gallery thumbnail
 *                 (bg/text/accent/shape) without spinning up the full block.
 *
 * Variants are intentionally opaque strings so we can rename or extend the
 * style payload over time without breaking already-saved pages: if the key
 * isn't found in the catalog we fall back to whatever `_style` the block
 * already has, which is the same as picking "Custom".
 */
class BlockVariantCatalog
{
    /**
     * Catalog schema version. Bumped whenever a variant's style payload
     * changes in a way that should be re-applied to existing blocks (a
     * new shadow tier, a renamed CSS prop, etc.). The version is stored
     * alongside the variant key on each block as `_variant_version` so
     * we can detect drift between the catalog the block was last styled
     * with and the catalog the renderer is reading from. The applyVariant
     * pipeline always writes the *current* VERSION so newly-applied or
     * re-applied variants stay in sync.
     */
    public const VERSION = 10;

    /**
     * Shape filters for link-style blocks. Orthogonal to theme TAGS:
     * a variant declares both a `shape` (what physical form the button
     * takes) and `tags` (what vibe the colors/borders give off). The
     * Designs gallery surfaces a "Shape" filter row separate from the
     * theme chips so creators can scan "what does this look like?"
     * without parsing 16 colour theme labels first.
     */
    public const SHAPES = [
        'card'       => 'Card',
        'pill'       => 'Pill',
        'square'     => 'Square',
        'outline'    => 'Outline',
        'plain_text' => 'Text Link',
        'image_full' => 'Image',
    ];

    public const TAGS = [
        'minimal'      => 'Minimal',
        'bold'         => 'Bold',
        'playful'      => 'Playful',
        'pro'          => 'Pro',
        'corporate'    => 'Corporate',
        'dark'         => 'Dark',
        'retro'        => 'Retro',
        'y2k'          => 'Y2K',
        'glass'        => 'Glass',
        'three_d'      => '3D',
        'neon'         => 'Neon',
        'handwritten'  => 'Handwritten',
        'brutalist'    => 'Brutalist',
        'editorial'    => 'Editorial',
        'maximalist'   => 'Maximalist',
    ];

    /**
     * Variants every block type gets for free. Per-type variants below are
     * merged on top, allowing the catalog to opt in extra type-specific
     * looks (e.g. polaroid for image, neon ticket for coupon).
     */
    private static function commonVariants(): array
    {
        return [
            [
                'key' => 'classic',
                'name' => 'Classic',
                'tags' => ['minimal'],
                'style' => [
                    'display_mode' => 'card',
                    'bg_color' => '#ffffff0d',
                    'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff15',
                    'border_radius' => '12', 'shadow_preset' => 'soft', 'glass_preset' => 'off',
                    'effect' => 'none', 'padding' => '16',
                ],
                'preview' => ['bg' => '#1a1a2e', 'text' => '#fff', 'radius' => 12, 'border' => '#ffffff20'],
            ],
            [
                'key' => 'minimal_mono',
                'name' => 'Minimal Mono',
                'tags' => ['minimal', 'editorial'],
                'style' => [
                    'display_mode' => 'content', 'bg_color' => 'transparent',
                    'border_style' => 'none', 'border_radius' => '0',
                    'shadow_preset' => 'none', 'glass_preset' => 'off',
                    'effect' => 'none', 'padding' => '8', 'font_weight' => '400',
                ],
                'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 0],
            ],
            [
                'key' => 'glass_card',
                'name' => 'Glass Card',
                'tags' => ['glass', 'pro'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#ffffff0a',
                    'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff20',
                    'border_radius' => '20', 'shadow_preset' => 'medium',
                    'glass_preset' => 'heavy', 'effect' => 'glass', 'padding' => '20',
                ],
                'preview' => ['bg' => 'rgba(255,255,255,0.06)', 'text' => '#fff', 'radius' => 20, 'border' => '#ffffff30'],
            ],
            [
                'key' => 'frosted_pill',
                'name' => 'Frosted Pill',
                'tags' => ['glass', 'minimal'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#ffffff14',
                    'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff25',
                    'border_radius' => '999', 'shadow_preset' => 'soft',
                    'glass_preset' => 'light', 'effect' => 'glass', 'padding' => '14',
                ],
                'preview' => ['bg' => 'rgba(255,255,255,0.08)', 'text' => '#fff', 'radius' => 999, 'border' => '#ffffff40'],
            ],
            [
                'key' => 'neon_outline',
                'name' => 'Neon Outline',
                'tags' => ['neon', 'dark', 'bold'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#0a0a0a',
                    'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#8b5cf6',
                    'border_radius' => '12', 'shadow_type' => 'neon',
                    'shadow_color' => '#8b5cf680', 'shadow_blur' => '24',
                    'effect' => 'none', 'text_color' => '#a78bfa', 'padding' => '16',
                ],
                'preview' => ['bg' => '#0a0a0a', 'text' => '#a78bfa', 'radius' => 12, 'border' => '#8b5cf6'],
            ],
            [
                'key' => 'brutalist',
                'name' => 'Brutalist',
                'tags' => ['brutalist', 'bold'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#ffffff',
                    'border_style' => 'solid', 'border_width' => '3', 'border_color' => '#000000',
                    'border_radius' => '0', 'shadow_type' => 'hard',
                    'shadow_color' => '#000000', 'shadow_x' => 6, 'shadow_y' => 6, 'shadow_blur' => 0,
                    'effect' => 'none', 'text_color' => '#000', 'padding' => '18',
                    'font_weight' => '700',
                ],
                'preview' => ['bg' => '#fff', 'text' => '#000', 'radius' => 0, 'border' => '#000', 'shadow' => '6px 6px 0 #000'],
            ],
            [
                'key' => 'soft_pastel',
                'name' => 'Soft Pastel',
                'tags' => ['playful', 'minimal'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#fde8ff',
                    'border_style' => 'none', 'border_radius' => '24',
                    'shadow_type' => 'soft', 'shadow_color' => '#ec489940',
                    'shadow_y' => 8, 'shadow_blur' => 24,
                    'effect' => 'none', 'text_color' => '#7e22ce', 'padding' => '18',
                ],
                'preview' => ['bg' => '#fde8ff', 'text' => '#7e22ce', 'radius' => 24],
            ],
            [
                'key' => 'editorial_serif',
                'name' => 'Editorial',
                'tags' => ['editorial', 'pro'],
                'style' => [
                    'display_mode' => 'content', 'bg_color' => 'transparent',
                    'border_style' => 'none', 'border_radius' => '0',
                    'shadow_preset' => 'none', 'effect' => 'none',
                    'padding' => '6', 'font_family' => 'Playfair Display',
                ],
                'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 0, 'serif' => true],
            ],
            [
                'key' => 'retro_sticker',
                'name' => 'Retro Sticker',
                'tags' => ['retro', 'playful'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#fef3c7',
                    'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#92400e',
                    'border_radius' => '14', 'shadow_type' => 'hard',
                    'shadow_color' => '#92400e', 'shadow_x' => 4, 'shadow_y' => 4, 'shadow_blur' => 0,
                    'effect' => 'none', 'text_color' => '#78350f', 'padding' => '16',
                    'font_weight' => '700',
                ],
                'preview' => ['bg' => '#fef3c7', 'text' => '#78350f', 'radius' => 14, 'border' => '#92400e', 'shadow' => '4px 4px 0 #92400e'],
            ],
            [
                'key' => 'three_d_layered',
                'name' => '3D Layered',
                'tags' => ['three_d', 'bold'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#7c3aed',
                    'border_style' => 'none', 'border_radius' => '16',
                    'shadow_type' => 'hard', 'shadow_color' => '#3b0764',
                    'shadow_x' => 0, 'shadow_y' => 8, 'shadow_blur' => 0,
                    'effect' => 'none', 'text_color' => '#fff', 'padding' => '18',
                    'font_weight' => '700',
                ],
                'preview' => ['bg' => '#7c3aed', 'text' => '#fff', 'radius' => 16, 'shadow' => '0 8px 0 #3b0764'],
            ],
            [
                'key' => 'handwritten_note',
                'name' => 'Handwritten',
                'tags' => ['handwritten', 'playful'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#fffaf0',
                    'border_style' => 'dashed', 'border_width' => '2', 'border_color' => '#1f2937',
                    'border_radius' => '8', 'shadow_preset' => 'none',
                    'effect' => 'none', 'text_color' => '#1f2937', 'padding' => '16',
                    'font_family' => 'Caveat',
                ],
                'preview' => ['bg' => '#fffaf0', 'text' => '#1f2937', 'radius' => 8, 'border' => '#1f2937', 'dashed' => true],
            ],
            [
                'key' => 'gradient_pop',
                'name' => 'Gradient Pop',
                'tags' => ['bold', 'playful'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#ec4899',
                    'border_style' => 'none', 'border_radius' => '16',
                    'shadow_type' => 'glow', 'shadow_color' => '#ec489966', 'shadow_blur' => 30,
                    'effect' => 'none', 'text_color' => '#ffffff', 'padding' => '18',
                    'font_weight' => '700',
                ],
                'preview' => ['bg' => 'linear-gradient(135deg,#ec4899,#8b5cf6)', 'text' => '#fff', 'radius' => 16],
            ],

            // ===== Unique shapes / colors / themes =====
            // The eight variants below were added to give every block a
            // wider visual range (ticket cut, ribbon banner, polaroid, hex
            // sticker, holographic, cyberpunk, terminal, paper craft) so
            // the gallery actually feels distinctive instead of "20 cards
            // with different bg colors". Order: shape-driven first, then
            // color/theme-driven.

            [
                'key' => 'ticket_stub',
                'name' => 'Ticket Stub',
                'tags' => ['playful', 'retro'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#fef3c7',
                    'border_style' => 'dashed', 'border_width' => '2', 'border_color' => '#b45309',
                    'border_radius' => '4', 'shadow_type' => 'soft',
                    'shadow_color' => '#b4530933', 'shadow_y' => 6, 'shadow_blur' => 14,
                    'effect' => 'none', 'text_color' => '#78350f', 'padding' => '16',
                    'font_weight' => '700', 'font_family' => 'JetBrains Mono',
                ],
                'preview' => ['bg' => '#fef3c7', 'text' => '#78350f', 'radius' => 4, 'border' => '#b45309', 'dashed' => true],
            ],
            [
                'key' => 'polaroid',
                'name' => 'Polaroid',
                'tags' => ['playful', 'editorial'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#ffffff',
                    'border_style' => 'solid', 'border_width' => '6', 'border_color' => '#ffffff',
                    'border_radius' => '4', 'shadow_type' => 'hard',
                    'shadow_color' => '#00000055', 'shadow_x' => 4, 'shadow_y' => 8, 'shadow_blur' => 18,
                    'effect' => 'none', 'text_color' => '#1f2937', 'padding' => '20',
                    'font_family' => 'Caveat', 'font_weight' => '600',
                ],
                'preview' => ['bg' => '#ffffff', 'text' => '#1f2937', 'radius' => 4, 'border' => '#ffffff', 'shadow' => '4px 8px 18px #00000055'],
            ],
            [
                'key' => 'hex_sticker',
                'name' => 'Hex Sticker',
                'tags' => ['playful', 'three_d'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#10b981',
                    'border_style' => 'solid', 'border_width' => '3', 'border_color' => '#ffffff',
                    'border_radius' => '14', 'shadow_type' => 'hard',
                    'shadow_color' => '#064e3b', 'shadow_x' => 0, 'shadow_y' => 5, 'shadow_blur' => 0,
                    'effect' => 'none', 'text_color' => '#ffffff', 'padding' => '16',
                    'font_weight' => '800',
                ],
                'preview' => ['bg' => '#10b981', 'text' => '#ffffff', 'radius' => 14, 'border' => '#ffffff', 'shadow' => '0 5px 0 #064e3b'],
            ],
            [
                'key' => 'ribbon_banner',
                'name' => 'Ribbon Banner',
                'tags' => ['bold', 'editorial', 'pro'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#dc2626',
                    'border_style' => 'solid', 'border_width' => '0',
                    'border_color' => '#7f1d1d', 'border_radius' => '2',
                    'shadow_type' => 'hard', 'shadow_color' => '#7f1d1d',
                    'shadow_x' => -4, 'shadow_y' => 4, 'shadow_blur' => 0,
                    'effect' => 'none', 'text_color' => '#ffffff', 'padding' => '14',
                    'font_weight' => '800', 'font_family' => 'Playfair Display',
                ],
                'preview' => ['bg' => '#dc2626', 'text' => '#ffffff', 'radius' => 2, 'shadow' => '-4px 4px 0 #7f1d1d', 'serif' => true],
            ],
            [
                'key' => 'holographic',
                'name' => 'Holographic',
                'tags' => ['y2k', 'maximalist', 'three_d'],
                'style' => [
                    'display_mode' => 'card',
                    'bg_color' => 'linear-gradient(135deg,#fbcfe8,#a5b4fc,#67e8f9,#bef264)',
                    'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#ffffff80',
                    'border_radius' => '20', 'shadow_type' => 'glow',
                    'shadow_color' => '#a78bfa66', 'shadow_blur' => 28,
                    'effect' => 'none', 'text_color' => '#1e1b4b', 'padding' => '18',
                    'font_weight' => '800',
                ],
                'preview' => ['bg' => 'linear-gradient(135deg,#fbcfe8,#a5b4fc,#67e8f9,#bef264)', 'text' => '#1e1b4b', 'radius' => 20, 'border' => '#ffffff80'],
            ],
            [
                'key' => 'cyberpunk_grid',
                'name' => 'Cyberpunk',
                'tags' => ['neon', 'dark', 'maximalist', 'y2k'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#0b0420',
                    'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#f0abfc',
                    'border_radius' => '6', 'shadow_type' => 'neon',
                    'shadow_color' => '#f0abfc99', 'shadow_blur' => 24,
                    'effect' => 'none', 'text_color' => '#5eead4', 'padding' => '16',
                    'font_weight' => '700', 'font_family' => 'JetBrains Mono',
                ],
                'preview' => ['bg' => '#0b0420', 'text' => '#5eead4', 'radius' => 6, 'border' => '#f0abfc'],
            ],
            [
                'key' => 'terminal',
                'name' => 'Terminal',
                'tags' => ['minimal', 'dark', 'pro'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#020617',
                    'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#22c55e',
                    'border_radius' => '4', 'shadow_type' => 'glow',
                    'shadow_color' => '#22c55e44', 'shadow_blur' => 18,
                    'effect' => 'none', 'text_color' => '#4ade80', 'padding' => '14',
                    'font_weight' => '500', 'font_family' => 'JetBrains Mono',
                ],
                'preview' => ['bg' => '#020617', 'text' => '#4ade80', 'radius' => 4, 'border' => '#22c55e'],
            ],
            [
                'key' => 'paper_craft',
                'name' => 'Paper Craft',
                'tags' => ['minimal', 'editorial', 'handwritten'],
                'style' => [
                    'display_mode' => 'card', 'bg_color' => '#fafaf6',
                    'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e7e5e4',
                    'border_radius' => '10', 'shadow_type' => 'soft',
                    'shadow_color' => '#a8a29e55', 'shadow_y' => 2, 'shadow_blur' => 8,
                    'effect' => 'none', 'text_color' => '#1c1917', 'padding' => '18',
                    'font_family' => 'Lora',
                ],
                'preview' => ['bg' => '#fafaf6', 'text' => '#1c1917', 'radius' => 10, 'border' => '#e7e5e4', 'serif' => true],
            ],
        ];
    }

    /**
     * Reusable variant bundles that apply to a whole family of block
     * types (e.g. every video platform shares the cinema-strip and
     * CRT looks). Defining them once keeps the per-type map below a
     * pure routing table — no copy-pasted style payloads to drift.
     */
    private static function bundles(): array
    {
        return [
            // Link-style buttons (link, link_big, cta_button, featured_pin).
            // The `shape` field powers the new Shape filter row in the
            // Designs gallery — orthogonal to the colour `tags`. Variants
            // missing `shape` default to 'card' in the renderer.
            'link_actions' => [
                [
                    'key' => 'corporate_row',
                    'name' => 'Corporate Row',
                    'tags' => ['corporate', 'minimal', 'pro'],
                    'shape' => 'square',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '6', 'shadow_preset' => 'none',
                        'text_color' => '#111827', 'padding' => '14', 'font_weight' => '600',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 6, 'border' => '#e5e7eb'],
                ],
                [
                    'key' => 'cta_glow',
                    'name' => 'CTA Glow',
                    'tags' => ['neon', 'bold'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#22d3ee',
                        'border_radius' => '14', 'shadow_type' => 'glow',
                        'shadow_color' => '#22d3eeaa', 'shadow_blur' => 28,
                        'text_color' => '#67e8f9', 'padding' => '18', 'font_weight' => '700',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#0f172a', 'text' => '#67e8f9', 'radius' => 14, 'border' => '#22d3ee'],
                ],
                [
                    'key' => 'y2k_chrome',
                    'name' => 'Y2K Chrome',
                    'tags' => ['y2k', 'retro', 'three_d'],
                    'shape' => 'pill',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#c0c0d8',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#7280a8',
                        'border_radius' => '999', 'shadow_type' => 'soft',
                        'shadow_color' => '#3b82f680', 'shadow_y' => 6, 'shadow_blur' => 14,
                        'text_color' => '#1e1b4b', 'padding' => '14', 'font_weight' => '700',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => 'linear-gradient(180deg,#e0e7ff,#94a3b8)', 'text' => '#1e1b4b', 'radius' => 999, 'border' => '#7280a8'],
                ],

                // ── New shape-first variants ──────────────────────────────
                // Filled pill — the most "instagram bio link" look.
                [
                    'key' => 'pill_solid',
                    'name' => 'Solid Pill',
                    'tags' => ['minimal', 'bold'],
                    'shape' => 'pill',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#7c3aed',
                        'border_style' => 'none', 'border_width' => '0', 'border_color' => 'transparent',
                        'border_radius' => '999', 'shadow_preset' => 'soft',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '600',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#7c3aed', 'text' => '#ffffff', 'radius' => 999],
                ],
                // Sharp square — brutalist newspaper button.
                [
                    'key' => 'square_sharp',
                    'name' => 'Sharp Square',
                    'tags' => ['brutalist', 'bold', 'editorial'],
                    'shape' => 'square',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#000000',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#000000',
                        'border_radius' => '0', 'shadow_preset' => 'none',
                        'text_color' => '#ffffff', 'padding' => '16', 'font_weight' => '700',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#000000', 'text' => '#ffffff', 'radius' => 0, 'border' => '#000000'],
                ],
                // Hollow outline pill — quieter alternative to Solid Pill.
                [
                    'key' => 'outline_pill',
                    'name' => 'Outline Pill',
                    'tags' => ['minimal', 'pro'],
                    'shape' => 'outline',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#ffffff',
                        'border_radius' => '999', 'shadow_preset' => 'none',
                        'text_color' => '#ffffff', 'padding' => '12', 'font_weight' => '500',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 999, 'border' => '#ffffff'],
                ],
                // Hollow outline square — corporate quiet variant.
                [
                    'key' => 'outline_square',
                    'name' => 'Outline Square',
                    'tags' => ['minimal', 'corporate'],
                    'shape' => 'outline',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff66',
                        'border_radius' => '6', 'shadow_preset' => 'none',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '500',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 6, 'border' => '#ffffff66'],
                ],
                // Pure inline link — no card chrome, just underlined text.
                // Renderer reads `link_layout=plain_text` and skips bio-btn
                // styling entirely so the result reads as a sentence link.
                [
                    'key' => 'plain_text_link',
                    'name' => 'Plain Text Link',
                    'tags' => ['minimal', 'editorial'],
                    'shape' => 'plain_text',
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_width' => '0', 'border_color' => 'transparent',
                        'border_radius' => '0', 'shadow_preset' => 'none',
                        'text_color' => '#a78bfa', 'padding' => '4', 'font_weight' => '500',
                        'link_layout' => 'plain_text',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#a78bfa', 'radius' => 0],
                ],
                // Full-bleed image button — uses the block's thumbnail as a
                // cover background with a darkened overlay and the title
                // centred on top. Falls back to a solid card when no
                // thumbnail is set on the block.
                [
                    'key' => 'image_cover',
                    'name' => 'Full Image',
                    'tags' => ['bold', 'maximalist'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#1a1a2e',
                        'border_style' => 'none', 'border_width' => '0', 'border_color' => 'transparent',
                        'border_radius' => '16', 'shadow_preset' => 'medium',
                        'text_color' => '#ffffff', 'padding' => '0', 'font_weight' => '700',
                        'link_layout' => 'image_cover',
                    ],
                    'preview' => ['bg' => 'linear-gradient(135deg,#7c3aed,#ec4899)', 'text' => '#ffffff', 'radius' => 16],
                ],
            ],

            // Heading / title looks (heading, heading_logo).
            'headings' => [
                [
                    'key' => 'magazine_title',
                    'name' => 'Magazine Title',
                    'tags' => ['editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '4',
                        'text_color' => '#ffffff', 'font_family' => 'Playfair Display',
                        'font_weight' => '700',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 0, 'serif' => true],
                ],
                [
                    'key' => 'underline_band',
                    'name' => 'Underline Band',
                    'tags' => ['minimal', 'editorial'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '0',
                        'border_color' => '#a78bfa', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '6',
                        'text_color' => '#ffffff',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 0, 'border' => '#a78bfa'],
                ],
                [
                    'key' => 'spotlight_band',
                    'name' => 'Spotlight Band',
                    'tags' => ['bold', 'three_d'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#1e1b4b',
                        'border_style' => 'none', 'border_radius' => '4',
                        'shadow_type' => 'glow', 'shadow_color' => '#a78bfa66',
                        'shadow_blur' => 30, 'text_color' => '#ffffff',
                        'padding' => '14', 'font_weight' => '800',
                    ],
                    'preview' => ['bg' => '#1e1b4b', 'text' => '#fff', 'radius' => 4],
                ],
            ],

            // Body text styles (paragraph, paragraph_rich, markdown, list).
            'body_text' => [
                [
                    'key' => 'manuscript',
                    'name' => 'Manuscript',
                    'tags' => ['editorial', 'minimal'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '6',
                        'text_color' => '#e5e7eb', 'font_family' => 'Lora',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#e5e7eb', 'radius' => 0, 'serif' => true],
                ],
                [
                    'key' => 'sticky_note',
                    'name' => 'Sticky Note',
                    'tags' => ['playful', 'handwritten'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fef08a',
                        'border_style' => 'none', 'border_radius' => '4',
                        'shadow_type' => 'soft', 'shadow_color' => '#92400e55',
                        'shadow_y' => 6, 'shadow_blur' => 18,
                        'text_color' => '#422006', 'padding' => '16',
                        'font_family' => 'Caveat',
                    ],
                    'preview' => ['bg' => '#fef08a', 'text' => '#422006', 'radius' => 4],
                ],
            ],

            // Social rows / icons (socials, socials_multi, socials_custom).
            'socials' => [
                [
                    'key' => 'icon_pills',
                    'name' => 'Icon Pills',
                    'tags' => ['playful', 'glass'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff10',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff22',
                        'border_radius' => '999', 'shadow_preset' => 'soft',
                        'glass_preset' => 'light', 'effect' => 'glass', 'padding' => '12',
                    ],
                    'preview' => ['bg' => 'rgba(255,255,255,0.08)', 'text' => '#fff', 'radius' => 999, 'border' => '#ffffff30'],
                ],
                [
                    'key' => 'mono_chrome',
                    'name' => 'Mono Chrome',
                    'tags' => ['minimal', 'corporate'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '8',
                        'text_color' => '#ffffff',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 0],
                ],
                [
                    'key' => 'rainbow_row',
                    'name' => 'Rainbow Row',
                    'tags' => ['maximalist', 'playful', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ec4899',
                        'border_style' => 'none', 'border_radius' => '20',
                        'shadow_type' => 'glow', 'shadow_color' => '#f472b699', 'shadow_blur' => 30,
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => 'linear-gradient(90deg,#f59e0b,#ec4899,#8b5cf6,#22d3ee)', 'text' => '#fff', 'radius' => 20],
                ],
            ],

            // Video / streaming embeds (video, header_video, youtube, latest_youtube,
            // youtube_feed, vimeo, twitch, kick, rumble_video, vk_video, tiktok_video,
            // twitter_video).
            'video' => [
                [
                    'key' => 'cinema_strip',
                    'name' => 'Cinema Strip',
                    'tags' => ['dark', 'editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#000000',
                        'border_style' => 'solid', 'border_width' => '8', 'border_color' => '#000000',
                        'border_radius' => '4', 'shadow_preset' => 'medium',
                        'text_color' => '#fafaf9', 'padding' => '0',
                    ],
                    'preview' => ['bg' => '#000', 'text' => '#fafaf9', 'radius' => 4, 'border' => '#000'],
                ],
                [
                    'key' => 'crt_screen',
                    'name' => 'CRT Screen',
                    'tags' => ['retro', 'y2k', 'dark'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a14',
                        'border_style' => 'solid', 'border_width' => '6', 'border_color' => '#1f2937',
                        'border_radius' => '24', 'shadow_type' => 'neon',
                        'shadow_color' => '#22d3ee66', 'shadow_blur' => 28,
                        'text_color' => '#86efac', 'padding' => '6',
                        'font_family' => 'JetBrains Mono',
                    ],
                    'preview' => ['bg' => '#0a0a14', 'text' => '#86efac', 'radius' => 24, 'border' => '#1f2937'],
                ],
                [
                    'key' => 'broadcast_card',
                    'name' => 'Broadcast Card',
                    'tags' => ['corporate', 'pro', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#1e293b',
                        'border_radius' => '10', 'shadow_preset' => 'soft',
                        'text_color' => '#f1f5f9', 'padding' => '12',
                    ],
                    'preview' => ['bg' => '#0f172a', 'text' => '#f1f5f9', 'radius' => 10, 'border' => '#1e293b'],
                ],
            ],

            // Embeds (iframe_embed, custom_html, facebook_post, reddit_post,
            // telegram_post, discord_server, instagram_media, latest_instagram,
            // twitter_tweet, twitter_profile, pinterest_profile, snapchat,
            // tiktok_profile).
            'embed' => [
                [
                    'key' => 'window_chrome',
                    'name' => 'Window Chrome',
                    'tags' => ['corporate', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#d1d5db',
                        'border_radius' => '10', 'shadow_preset' => 'medium',
                        'text_color' => '#111827', 'padding' => '12',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 10, 'border' => '#d1d5db'],
                ],
                [
                    'key' => 'terminal',
                    'name' => 'Terminal',
                    'tags' => ['dark', 'brutalist'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#020617',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#22c55e',
                        'border_radius' => '6', 'shadow_type' => 'neon',
                        'shadow_color' => '#22c55e55', 'shadow_blur' => 18,
                        'text_color' => '#86efac', 'padding' => '14',
                        'font_family' => 'JetBrains Mono',
                    ],
                    'preview' => ['bg' => '#020617', 'text' => '#86efac', 'radius' => 6, 'border' => '#22c55e'],
                ],
            ],

            // Form-style blocks (form, contact_form, email_collector,
            // email_subscribe, phone_collector, direct_message).
            'form' => [
                [
                    'key' => 'paper_form',
                    'name' => 'Paper Form',
                    'tags' => ['minimal', 'corporate', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '8', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '20',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 8, 'border' => '#e5e7eb'],
                ],
                [
                    'key' => 'pop_form',
                    'name' => 'Pop Form',
                    'tags' => ['bold', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fef3c7',
                        'border_style' => 'solid', 'border_width' => '3', 'border_color' => '#7c2d12',
                        'border_radius' => '18', 'shadow_type' => 'hard',
                        'shadow_color' => '#7c2d12', 'shadow_x' => 5, 'shadow_y' => 5, 'shadow_blur' => 0,
                        'text_color' => '#7c2d12', 'padding' => '20', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => '#fef3c7', 'text' => '#7c2d12', 'radius' => 18, 'border' => '#7c2d12', 'shadow' => '5px 5px 0 #7c2d12'],
                ],
                [
                    'key' => 'glass_inbox',
                    'name' => 'Glass Inbox',
                    'tags' => ['glass', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff14',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff30',
                        'border_radius' => '20', 'shadow_preset' => 'medium',
                        'glass_preset' => 'heavy', 'effect' => 'glass', 'padding' => '22',
                    ],
                    'preview' => ['bg' => 'rgba(255,255,255,0.08)', 'text' => '#fff', 'radius' => 20, 'border' => '#ffffff40'],
                ],
            ],

            // Galleries (image_grid, image_slider, image_slider_v2).
            'gallery' => [
                [
                    'key' => 'contact_sheet',
                    'name' => 'Contact Sheet',
                    'tags' => ['editorial', 'retro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#1c1917',
                        'border_style' => 'solid', 'border_width' => '6', 'border_color' => '#1c1917',
                        'border_radius' => '4', 'shadow_preset' => 'medium',
                        'text_color' => '#fafaf9', 'padding' => '6',
                    ],
                    'preview' => ['bg' => '#1c1917', 'text' => '#fafaf9', 'radius' => 4, 'border' => '#1c1917'],
                ],
                [
                    'key' => 'maximalist_collage',
                    'name' => 'Maximalist Collage',
                    'tags' => ['maximalist', 'bold', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fb7185',
                        'border_style' => 'solid', 'border_width' => '4', 'border_color' => '#facc15',
                        'border_radius' => '20', 'shadow_type' => 'hard',
                        'shadow_color' => '#7c3aed', 'shadow_x' => 6, 'shadow_y' => 6, 'shadow_blur' => 0,
                        'text_color' => '#1e1b4b', 'padding' => '14', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => 'linear-gradient(135deg,#fb7185,#facc15,#22d3ee)', 'text' => '#1e1b4b', 'radius' => 20, 'border' => '#facc15'],
                ],
            ],

            // Music players (spotify, apple_music, soundcloud, tidal,
            // mixcloud, anchor_fm, audio).
            'music' => [
                [
                    'key' => 'vinyl',
                    'name' => 'Vinyl',
                    'tags' => ['retro', 'three_d'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a0a',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#27272a',
                        'border_radius' => '999', 'shadow_type' => 'soft',
                        'shadow_color' => '#00000099', 'shadow_y' => 10, 'shadow_blur' => 24,
                        'text_color' => '#fafaf9', 'padding' => '16',
                    ],
                    'preview' => ['bg' => '#0a0a0a', 'text' => '#fafaf9', 'radius' => 999, 'border' => '#27272a'],
                ],
                [
                    'key' => 'cassette',
                    'name' => 'Cassette',
                    'tags' => ['y2k', 'retro', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fbbf24',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#7c2d12',
                        'border_radius' => '6', 'shadow_type' => 'hard',
                        'shadow_color' => '#7c2d12', 'shadow_x' => 4, 'shadow_y' => 4, 'shadow_blur' => 0,
                        'text_color' => '#7c2d12', 'padding' => '14', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => '#fbbf24', 'text' => '#7c2d12', 'radius' => 6, 'border' => '#7c2d12', 'shadow' => '4px 4px 0 #7c2d12'],
                ],
                [
                    'key' => 'studio_dark',
                    'name' => 'Studio Dark',
                    'tags' => ['dark', 'pro', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#1e293b',
                        'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#cbd5e1', 'padding' => '14',
                    ],
                    'preview' => ['bg' => '#0f172a', 'text' => '#cbd5e1', 'radius' => 14, 'border' => '#1e293b'],
                ],
            ],

            // Calendar / booking (calendly, calendly_embed).
            'calendar' => [
                [
                    'key' => 'agenda_card',
                    'name' => 'Agenda Card',
                    'tags' => ['corporate', 'minimal', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '10', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '18',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 10, 'border' => '#e5e7eb'],
                ],
                [
                    'key' => 'studio_booking',
                    'name' => 'Studio Booking',
                    'tags' => ['editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0c0a09',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#292524',
                        'border_radius' => '6', 'shadow_preset' => 'medium',
                        'text_color' => '#fafaf9', 'padding' => '20',
                        'font_family' => 'Playfair Display',
                    ],
                    'preview' => ['bg' => '#0c0a09', 'text' => '#fafaf9', 'radius' => 6, 'border' => '#292524', 'serif' => true],
                ],
            ],

            // Tip jar / fan support (donation, buy_me_coffee, patreon, ko_fi,
            // paypal).
            'tip' => [
                [
                    'key' => 'tip_jar',
                    'name' => 'Tip Jar',
                    'tags' => ['playful', 'retro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fef3c7',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#b45309',
                        'border_radius' => '18', 'shadow_type' => 'soft',
                        'shadow_color' => '#b4530933', 'shadow_y' => 6, 'shadow_blur' => 16,
                        'text_color' => '#7c2d12', 'padding' => '16', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => '#fef3c7', 'text' => '#7c2d12', 'radius' => 18, 'border' => '#b45309'],
                ],
            ],

            // Storefront / catalog (product, service, catalog, market, price).
            'commerce' => [
                [
                    'key' => 'boutique_tag',
                    'name' => 'Boutique Tag',
                    'tags' => ['editorial', 'pro', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fafaf9',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#d6d3d1',
                        'border_radius' => '4', 'shadow_preset' => 'soft',
                        'text_color' => '#1c1917', 'padding' => '16',
                        'font_family' => 'Playfair Display',
                    ],
                    'preview' => ['bg' => '#fafaf9', 'text' => '#1c1917', 'radius' => 4, 'border' => '#d6d3d1', 'serif' => true],
                ],
                [
                    'key' => 'maximalist_card',
                    'name' => 'Maximalist Card',
                    'tags' => ['maximalist', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#7c3aed',
                        'border_style' => 'solid', 'border_width' => '3', 'border_color' => '#facc15',
                        'border_radius' => '22', 'shadow_type' => 'hard',
                        'shadow_color' => '#ec4899', 'shadow_x' => 6, 'shadow_y' => 6, 'shadow_blur' => 0,
                        'text_color' => '#fef3c7', 'padding' => '18', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => 'linear-gradient(135deg,#7c3aed,#ec4899)', 'text' => '#fef3c7', 'radius' => 22, 'border' => '#facc15'],
                ],
            ],

            // Timeline / roadmap (timeline, timeline_staged, roadmap).
            'timeline' => [
                [
                    'key' => 'milestone_rail',
                    'name' => 'Milestone Rail',
                    'tags' => ['corporate', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff08',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff20',
                        'border_radius' => '10', 'shadow_preset' => 'soft',
                        'text_color' => '#f1f5f9', 'padding' => '18',
                    ],
                    'preview' => ['bg' => 'rgba(255,255,255,0.04)', 'text' => '#f1f5f9', 'radius' => 10, 'border' => '#ffffff30'],
                ],
            ],

            // ─── Task #1041: expanded link-in-bio shape library ──────────
            //
            // The bundles below are additive to `link_actions`,
            // `headings`, `gallery`, and `socials`, and introduce a new
            // `cover_profile` bundle for `profile_card_v2`. They reuse
            // existing style keys (incl. `link_layout=image_cover`) so
            // no renderer changes are required — the public Blade and
            // mobile renderers already merge `_style` from the variant
            // into the block container. New variant keys MUST stay in
            // sync with the mobile mirror in `lib/blockVariants.ts`.

            // More shaped link buttons (link, link_big, cta_button,
            // featured_pin, external_item). Mixes pure shape variations
            // (split, arch, ribbon, tab) with full-image cover modes
            // that opt into `link_layout=image_cover` so the renderer
            // promotes the block's thumbnail to the background.
            'link_shapes' => [
                [
                    'key' => 'pill_gradient',
                    'name' => 'Gradient Pill',
                    'tags' => ['bold', 'playful'],
                    'shape' => 'pill',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ec4899',
                        'border_style' => 'none', 'border_radius' => '999',
                        'shadow_type' => 'glow', 'shadow_color' => '#ec489966', 'shadow_blur' => 28,
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '700',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => 'linear-gradient(90deg,#ec4899,#7c3aed)', 'text' => '#ffffff', 'radius' => 999],
                ],
                [
                    'key' => 'pill_dotted',
                    'name' => 'Dotted Pill',
                    'tags' => ['playful', 'handwritten'],
                    'shape' => 'pill',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'dotted', 'border_width' => '2', 'border_color' => '#ffffffaa',
                        'border_radius' => '999', 'shadow_preset' => 'none',
                        'text_color' => '#ffffff', 'padding' => '12', 'font_weight' => '500',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 999, 'border' => '#ffffffaa'],
                ],
                [
                    'key' => 'square_double',
                    'name' => 'Double Border',
                    'tags' => ['editorial', 'pro'],
                    'shape' => 'square',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'double', 'border_width' => '6', 'border_color' => '#1f2937',
                        'border_radius' => '4', 'shadow_preset' => 'none',
                        'text_color' => '#111827', 'padding' => '14', 'font_weight' => '600',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 4, 'border' => '#1f2937'],
                ],
                [
                    'key' => 'tab_underline',
                    'name' => 'Tab Underline',
                    'tags' => ['minimal', 'editorial'],
                    'shape' => 'plain_text',
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '0', 'border_color' => '#a78bfa',
                        'border_radius' => '0', 'shadow_preset' => 'none',
                        'text_color' => '#ffffff', 'padding' => '6', 'font_weight' => '600',
                        'link_layout' => 'plain_text',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0, 'border' => '#a78bfa'],
                ],
                // Bold action-word row — big uppercase accent word +
                // smaller inline description (Lillian-Pratt split-layout
                // style). Renderer reads `link_layout=action_row`.
                [
                    'key' => 'action_word_row',
                    'name' => 'Action Word Row',
                    'tags' => ['bold', 'editorial', 'minimal'],
                    'shape' => 'plain_text',
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_width' => '0', 'border_color' => 'transparent',
                        'border_radius' => '0', 'shadow_preset' => 'none',
                        'text_color' => '#e3f77e', 'padding' => '4', 'font_weight' => '800',
                        'link_layout' => 'action_row',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#e3f77e', 'radius' => 0],
                ],
                [
                    'key' => 'card_lifted',
                    'name' => 'Lifted Card',
                    'tags' => ['three_d', 'pro'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '14',
                        'shadow_type' => 'soft', 'shadow_color' => '#0000003d',
                        'shadow_x' => 0, 'shadow_y' => 14, 'shadow_blur' => 32,
                        'text_color' => '#111827', 'padding' => '18', 'font_weight' => '600',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 14, 'shadow' => '0 14px 32px #0000003d'],
                ],
                [
                    'key' => 'card_arch',
                    'name' => 'Arch Card',
                    'tags' => ['playful', 'editorial'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fafaf9',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e7e5e4',
                        'border_radius' => '32', 'shadow_preset' => 'soft',
                        'text_color' => '#1c1917', 'padding' => '20', 'font_weight' => '600',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#fafaf9', 'text' => '#1c1917', 'radius' => 32, 'border' => '#e7e5e4'],
                ],
                [
                    'key' => 'square_neumorphic',
                    'name' => 'Soft Neumorphic',
                    'tags' => ['minimal', 'three_d'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#1a1a2e',
                        'border_style' => 'none', 'border_radius' => '20',
                        'shadow_type' => 'neumorphic',
                        'text_color' => '#cbd5e1', 'padding' => '18', 'font_weight' => '500',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => '#1a1a2e', 'text' => '#cbd5e1', 'radius' => 20],
                ],
                [
                    'key' => 'pill_glass_dark',
                    'name' => 'Dark Glass Pill',
                    'tags' => ['glass', 'dark', 'pro'],
                    'shape' => 'pill',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a0a55',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff22',
                        'border_radius' => '999', 'shadow_preset' => 'soft',
                        'glass_preset' => 'heavy', 'effect' => 'glass',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '500',
                        'link_layout' => '',
                    ],
                    'preview' => ['bg' => 'rgba(0,0,0,0.4)', 'text' => '#ffffff', 'radius' => 999, 'border' => '#ffffff22'],
                ],
                // ── Image-mode buttons ────────────────────────────────────
                // Each opts into `link_layout=image_cover` so the renderer
                // promotes the block's thumbnail to a full-bleed cover.
                [
                    'key' => 'image_cover_dark',
                    'name' => 'Cover · Dark Overlay',
                    'tags' => ['dark', 'editorial', 'maximalist'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0612',
                        'border_style' => 'none', 'border_radius' => '20',
                        'shadow_preset' => 'medium',
                        'text_color' => '#ffffff', 'padding' => '0', 'font_weight' => '700',
                        'link_layout' => 'image_cover',
                    ],
                    'preview' => ['bg' => 'linear-gradient(180deg,#1a1a2e,#0a0612)', 'text' => '#ffffff', 'radius' => 20],
                ],
                [
                    'key' => 'image_cover_polaroid',
                    'name' => 'Cover · Polaroid',
                    'tags' => ['retro', 'playful', 'editorial'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '6', 'border_color' => '#ffffff',
                        'border_radius' => '6', 'shadow_type' => 'hard',
                        'shadow_color' => '#00000055', 'shadow_x' => 4, 'shadow_y' => 8, 'shadow_blur' => 18,
                        'text_color' => '#1f2937', 'padding' => '0', 'font_weight' => '700',
                        'font_family' => 'Caveat',
                        'link_layout' => 'image_cover',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#1f2937', 'radius' => 6, 'border' => '#ffffff', 'shadow' => '4px 8px 18px #00000055'],
                ],
                [
                    'key' => 'image_cover_neon',
                    'name' => 'Cover · Neon Frame',
                    'tags' => ['neon', 'bold', 'maximalist'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0b0420',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#22d3ee',
                        'border_radius' => '14', 'shadow_type' => 'neon',
                        'shadow_color' => '#22d3eeaa', 'shadow_blur' => 30,
                        'text_color' => '#a5f3fc', 'padding' => '0', 'font_weight' => '800',
                        'link_layout' => 'image_cover',
                    ],
                    'preview' => ['bg' => '#0b0420', 'text' => '#a5f3fc', 'radius' => 14, 'border' => '#22d3ee'],
                ],
                [
                    'key' => 'image_cover_arch',
                    'name' => 'Cover · Arch',
                    'tags' => ['editorial', 'pro', 'minimal'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#1a1a2e',
                        'border_style' => 'none', 'border_radius' => '40',
                        'shadow_preset' => 'medium',
                        'text_color' => '#ffffff', 'padding' => '0', 'font_weight' => '700',
                        'link_layout' => 'image_cover',
                    ],
                    'preview' => ['bg' => 'linear-gradient(135deg,#1a1a2e,#7c3aed)', 'text' => '#ffffff', 'radius' => 40],
                ],
            ],

            // ─── Button styles: icon & image placement ──────────────────
            //
            // Additive bundle for link-family blocks. Each variant carries a
            // `link_layout` placement token (rendered by the new branches in
            // common/blocks/link.blade.php) plus colour/border treatment via
            // the standard `_style` keys. The Designs gallery's live preview
            // renders the real partial, so the icon/image arrangement shows
            // correctly. `border_color` doubles as the badge accent for the
            // circle/box icon layouts when `border_style` is `none`.
            'link_buttons' => [
                [
                    'key' => 'icon_left_solid',
                    'name' => 'Icon Left',
                    'tags' => ['minimal', 'bold'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#7c3aed',
                        'border_style' => 'none', 'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '600',
                        'link_layout' => 'icon_left',
                    ],
                    'preview' => ['bg' => '#7c3aed', 'text' => '#ffffff', 'radius' => 14],
                ],
                [
                    'key' => 'icon_right_solid',
                    'name' => 'Icon Right',
                    'tags' => ['minimal', 'bold'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#2563eb',
                        'border_style' => 'none', 'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '600',
                        'link_layout' => 'icon_right',
                    ],
                    'preview' => ['bg' => '#2563eb', 'text' => '#ffffff', 'radius' => 14],
                ],
                [
                    'key' => 'icon_both_solid',
                    'name' => 'Icon Both Sides',
                    'tags' => ['bold', 'pro'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0f172a',
                        'border_style' => 'none', 'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '600',
                        'link_layout' => 'icon_both',
                    ],
                    'preview' => ['bg' => '#0f172a', 'text' => '#ffffff', 'radius' => 14],
                ],
                [
                    'key' => 'icon_only_dark',
                    'name' => 'Icon Only',
                    'tags' => ['minimal', 'bold'],
                    'shape' => 'square',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#111827',
                        'border_style' => 'none', 'border_radius' => '12', 'shadow_preset' => 'soft',
                        'text_color' => '#ffffff', 'padding' => '12', 'font_weight' => '600',
                        'link_layout' => 'icon_only',
                    ],
                    'preview' => ['bg' => '#111827', 'text' => '#ffffff', 'radius' => 12],
                ],
                [
                    'key' => 'icon_circle_left',
                    'name' => 'Icon Circle Left',
                    'tags' => ['minimal', 'pro'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_color' => '#2563eb',
                        'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '12', 'font_weight' => '600',
                        'link_layout' => 'icon_circle_left',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 14, 'border' => '#2563eb'],
                ],
                [
                    'key' => 'icon_circle_right',
                    'name' => 'Icon Circle Right',
                    'tags' => ['minimal', 'pro'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_color' => '#16a34a',
                        'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '12', 'font_weight' => '600',
                        'link_layout' => 'icon_circle_right',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 14, 'border' => '#16a34a'],
                ],
                [
                    'key' => 'icon_box_purple',
                    'name' => 'Icon in Box',
                    'tags' => ['pro', 'corporate'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_color' => '#6d28d9',
                        'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '12', 'font_weight' => '600',
                        'link_layout' => 'icon_box',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 14, 'border' => '#6d28d9'],
                ],
                [
                    'key' => 'icon_box_solid',
                    'name' => 'Solid Icon Box',
                    'tags' => ['bold', 'playful'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fff7ed',
                        'border_style' => 'none', 'border_color' => '#f97316',
                        'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#7c2d12', 'padding' => '12', 'font_weight' => '600',
                        'link_layout' => 'icon_box',
                    ],
                    'preview' => ['bg' => '#fff7ed', 'text' => '#7c2d12', 'radius' => 14, 'border' => '#f97316'],
                ],
                [
                    'key' => 'gradient_icon_left',
                    'name' => 'Gradient · Icon Left',
                    'tags' => ['bold', 'playful'],
                    'shape' => 'pill',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'linear-gradient(135deg,#ec4899,#8b5cf6)',
                        'border_style' => 'none', 'border_radius' => '999', 'shadow_preset' => 'soft',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '700',
                        'link_layout' => 'icon_left',
                    ],
                    'preview' => ['bg' => 'linear-gradient(135deg,#ec4899,#8b5cf6)', 'text' => '#ffffff', 'radius' => 999],
                ],
                [
                    'key' => 'gradient_icon_right',
                    'name' => 'Gradient · Icon Right',
                    'tags' => ['bold', 'playful'],
                    'shape' => 'pill',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'linear-gradient(135deg,#22d3ee,#3b82f6)',
                        'border_style' => 'none', 'border_radius' => '999', 'shadow_preset' => 'soft',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '700',
                        'link_layout' => 'icon_right',
                    ],
                    'preview' => ['bg' => 'linear-gradient(135deg,#22d3ee,#3b82f6)', 'text' => '#ffffff', 'radius' => 999],
                ],
                [
                    'key' => 'outline_icon_left',
                    'name' => 'Outline · Icon Left',
                    'tags' => ['minimal', 'pro'],
                    'shape' => 'outline',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#7c3aed',
                        'border_radius' => '12', 'shadow_preset' => 'none',
                        'text_color' => '#7c3aed', 'padding' => '12', 'font_weight' => '600',
                        'link_layout' => 'icon_left',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#7c3aed', 'radius' => 12, 'border' => '#7c3aed'],
                ],
                [
                    'key' => 'outline_icon_right',
                    'name' => 'Outline · Icon Right',
                    'tags' => ['minimal', 'pro'],
                    'shape' => 'outline',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#2563eb',
                        'border_radius' => '12', 'shadow_preset' => 'none',
                        'text_color' => '#2563eb', 'padding' => '12', 'font_weight' => '600',
                        'link_layout' => 'icon_right',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#2563eb', 'radius' => 12, 'border' => '#2563eb'],
                ],
                [
                    'key' => 'transparent_icon',
                    'name' => 'Transparent',
                    'tags' => ['minimal'],
                    'shape' => 'outline',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff55',
                        'border_radius' => '14', 'shadow_preset' => 'none',
                        'text_color' => '#ffffff', 'padding' => '14', 'font_weight' => '500',
                        'link_layout' => 'icon_left',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 14, 'border' => '#ffffff55'],
                ],
                [
                    'key' => 'dotted_icon',
                    'name' => 'Dotted Border',
                    'tags' => ['playful', 'editorial'],
                    'shape' => 'outline',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'dotted', 'border_width' => '2', 'border_color' => '#7c3aed',
                        'border_radius' => '14', 'shadow_preset' => 'none',
                        'text_color' => '#7c3aed', 'padding' => '14', 'font_weight' => '600',
                        'link_layout' => 'icon_left',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#7c3aed', 'radius' => 14, 'border' => '#7c3aed'],
                ],
                [
                    'key' => 'image_left',
                    'name' => 'Image Left',
                    'tags' => ['pro', 'editorial'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '0', 'font_weight' => '600',
                        'link_layout' => 'image_left',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 14],
                ],
                [
                    'key' => 'image_right',
                    'name' => 'Image Right',
                    'tags' => ['pro', 'editorial'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '0', 'font_weight' => '600',
                        'link_layout' => 'image_right',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 14],
                ],
                [
                    'key' => 'image_top',
                    'name' => 'Image Top',
                    'tags' => ['maximalist', 'editorial'],
                    'shape' => 'image_full',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#1a1a2e',
                        'border_style' => 'none', 'border_radius' => '16', 'shadow_preset' => 'medium',
                        'text_color' => '#ffffff', 'padding' => '0', 'font_weight' => '700',
                        'link_layout' => 'image_top',
                    ],
                    'preview' => ['bg' => 'linear-gradient(180deg,#7c3aed,#1a1a2e)', 'text' => '#ffffff', 'radius' => 16],
                ],
                [
                    'key' => 'image_icon_rounded',
                    'name' => 'Rounded Image Icon',
                    'tags' => ['minimal', 'pro'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '14', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '8', 'font_weight' => '600',
                        'link_layout' => 'image_icon_rounded',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 14],
                ],
                [
                    'key' => 'image_icon_square',
                    'name' => 'Square Image Icon',
                    'tags' => ['minimal', 'corporate'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '8', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '8', 'font_weight' => '600',
                        'link_layout' => 'image_icon_square',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 8],
                ],
                [
                    'key' => 'image_icon_circle',
                    'name' => 'Circular Image Icon',
                    'tags' => ['minimal', 'playful'],
                    'shape' => 'card',
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '999', 'shadow_preset' => 'soft',
                        'text_color' => '#111827', 'padding' => '8', 'font_weight' => '600',
                        'link_layout' => 'image_icon_circle',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 999],
                ],
            ],

            // Heading variants. Style keys only — animation cues live
            // in `_animation` which is added to STYLE_DEFAULTS so the
            // sanitizer accepts it; the public renderer reads it as a
            // CSS class hook (no-op until styled in biolink.blade.php).
            'heading_styles' => [
                [
                    'key' => 'oversize_serif',
                    'name' => 'Oversize Serif',
                    'tags' => ['editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '4',
                        'text_color' => '#ffffff', 'font_family' => 'Playfair Display',
                        'font_weight' => '900', 'font_size' => '48',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0, 'serif' => true],
                ],
                [
                    'key' => 'gradient_swipe',
                    'name' => 'Gradient Swipe',
                    'tags' => ['bold', 'playful'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '6',
                        'text_color' => '#ec4899', 'font_weight' => '800',
                        '_animation' => 'gradient_swipe',
                    ],
                    'preview' => ['bg' => 'linear-gradient(90deg,#ec4899,#7c3aed,#22d3ee)', 'text' => '#ffffff', 'radius' => 0],
                ],
                [
                    'key' => 'neon_glitch',
                    'name' => 'Neon Glitch',
                    'tags' => ['neon', 'y2k', 'bold'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_type' => 'neon', 'shadow_color' => '#f0abfcaa', 'shadow_blur' => 18,
                        'padding' => '4', 'text_color' => '#5eead4', 'font_weight' => '900',
                        'font_family' => 'JetBrains Mono',
                        '_animation' => 'neon_glitch',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#5eead4', 'radius' => 0],
                ],
                [
                    'key' => 'typewriter',
                    'name' => 'Typewriter',
                    'tags' => ['minimal', 'retro'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '4',
                        'text_color' => '#ffffff', 'font_family' => 'JetBrains Mono',
                        'font_weight' => '500',
                        '_animation' => 'typewriter',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0],
                ],
                [
                    'key' => 'wave_letters',
                    'name' => 'Wave Letters',
                    'tags' => ['playful', 'maximalist'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '4',
                        'text_color' => '#fbbf24', 'font_weight' => '800',
                        '_animation' => 'wave_letters',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fbbf24', 'radius' => 0],
                ],
                [
                    'key' => 'extrude_3d',
                    'name' => '3D Extrude',
                    'tags' => ['three_d', 'bold'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_type' => 'hard', 'shadow_color' => '#7c3aed',
                        'shadow_x' => 4, 'shadow_y' => 4, 'shadow_blur' => 0,
                        'padding' => '6', 'text_color' => '#ffffff', 'font_weight' => '900',
                        '_animation' => 'extrude_3d',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0, 'shadow' => '4px 4px 0 #7c3aed'],
                ],
                [
                    'key' => 'ticker_marquee',
                    'name' => 'Ticker Marquee',
                    'tags' => ['retro', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#1e293b',
                        'border_radius' => '4', 'shadow_preset' => 'none',
                        'text_color' => '#fbbf24', 'padding' => '10', 'font_weight' => '700',
                        'font_family' => 'JetBrains Mono',
                        '_animation' => 'ticker_marquee',
                    ],
                    'preview' => ['bg' => '#0f172a', 'text' => '#fbbf24', 'radius' => 4, 'border' => '#1e293b'],
                ],
                [
                    'key' => 'fade_in_up',
                    'name' => 'Fade In',
                    'tags' => ['minimal', 'pro'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '4',
                        'text_color' => '#ffffff', 'font_weight' => '600',
                        '_animation' => 'fade_in',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0],
                ],
            ],

            // Gallery layouts (image_grid, image_slider, image_slider_v2).
            // The visual differences come from container chrome; the
            // `_gallery_layout` hint travels with the variant for the
            // renderer to consume in a future iteration.
            'gallery_layouts' => [
                [
                    'key' => 'grid_two',
                    'name' => 'Grid · 2 Up',
                    'tags' => ['minimal', 'editorial'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '8',
                        'shadow_preset' => 'none', 'padding' => '4',
                        '_gallery_layout' => 'grid_2',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 8],
                ],
                [
                    'key' => 'grid_three',
                    'name' => 'Grid · 3 Up',
                    'tags' => ['minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '6',
                        'shadow_preset' => 'none', 'padding' => '4',
                        '_gallery_layout' => 'grid_3',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 6],
                ],
                [
                    'key' => 'grid_four',
                    'name' => 'Grid · 4 Up',
                    'tags' => ['minimal', 'corporate'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '4',
                        'shadow_preset' => 'none', 'padding' => '4',
                        '_gallery_layout' => 'grid_4',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 4],
                ],
                [
                    'key' => 'masonry',
                    'name' => 'Masonry',
                    'tags' => ['editorial', 'maximalist'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '8',
                        'shadow_preset' => 'soft', 'padding' => '4',
                        '_gallery_layout' => 'masonry',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 8],
                ],
                [
                    'key' => 'carousel_peek',
                    'name' => 'Carousel · Peek',
                    'tags' => ['playful', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '14',
                        'shadow_preset' => 'soft', 'padding' => '0',
                        '_gallery_layout' => 'carousel_peek',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 14],
                ],
                [
                    'key' => 'stacked_polaroids',
                    'name' => 'Stacked Polaroids',
                    'tags' => ['retro', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '6', 'border_color' => '#ffffff',
                        'border_radius' => '6', 'shadow_type' => 'hard',
                        'shadow_color' => '#00000055', 'shadow_x' => 4, 'shadow_y' => 8, 'shadow_blur' => 18,
                        'padding' => '10',
                        '_gallery_layout' => 'stacked_polaroids',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#1f2937', 'radius' => 6, 'border' => '#ffffff'],
                ],
                [
                    'key' => 'marquee_strip',
                    'name' => 'Marquee Strip',
                    'tags' => ['bold', 'maximalist'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a14',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '4',
                        '_gallery_layout' => 'marquee_strip',
                    ],
                    'preview' => ['bg' => '#0a0a14', 'text' => '#ffffff', 'radius' => 0],
                ],
                [
                    'key' => 'lightbox_grid',
                    'name' => 'Lightbox Grid',
                    'tags' => ['pro', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a14',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff15',
                        'border_radius' => '10', 'shadow_preset' => 'medium', 'padding' => '6',
                        '_gallery_layout' => 'lightbox_grid',
                    ],
                    'preview' => ['bg' => '#0a0a14', 'text' => '#ffffff', 'radius' => 10, 'border' => '#ffffff15'],
                ],
            ],

            // Many icon style sets for socials / socials_multi / socials_custom.
            // Sets describe the chrome around the row; the renderer
            // honours `_social_set` to pick the icon treatment, with a
            // safe no-op fallback (= today's brand-coloured icons).
            'social_sets' => [
                [
                    'key' => 'mono_line',
                    'name' => 'Mono · Line',
                    'tags' => ['minimal', 'editorial'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '6',
                        'text_color' => '#ffffff',
                        '_social_set' => 'mono_line',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0],
                ],
                [
                    'key' => 'mono_solid',
                    'name' => 'Mono · Solid',
                    'tags' => ['minimal', 'corporate'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff10',
                        'border_style' => 'none', 'border_radius' => '12',
                        'shadow_preset' => 'none', 'padding' => '10',
                        'text_color' => '#ffffff',
                        '_social_set' => 'mono_solid',
                    ],
                    'preview' => ['bg' => 'rgba(255,255,255,0.1)', 'text' => '#ffffff', 'radius' => 12],
                ],
                [
                    'key' => 'sketch',
                    'name' => 'Sketch',
                    'tags' => ['handwritten', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fffaf0',
                        'border_style' => 'dashed', 'border_width' => '2', 'border_color' => '#1f2937',
                        'border_radius' => '14', 'shadow_preset' => 'none',
                        'text_color' => '#1f2937', 'padding' => '12', 'font_family' => 'Caveat',
                        '_social_set' => 'sketch',
                    ],
                    'preview' => ['bg' => '#fffaf0', 'text' => '#1f2937', 'radius' => 14, 'border' => '#1f2937', 'dashed' => true],
                ],
                [
                    'key' => 'brand_color',
                    'name' => 'Brand Color',
                    'tags' => ['playful', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '12',
                        'shadow_preset' => 'none', 'padding' => '8',
                        '_social_set' => 'brand_color',
                    ],
                    'preview' => ['bg' => 'linear-gradient(90deg,#E4405F,#1877F2,#1DB954)', 'text' => '#ffffff', 'radius' => 12],
                ],
                [
                    'key' => 'tile_brand',
                    'name' => 'Brand Tiles',
                    'tags' => ['bold', 'maximalist'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a14',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff15',
                        'border_radius' => '14', 'shadow_preset' => 'soft', 'padding' => '10',
                        '_social_set' => 'brand_tiles',
                    ],
                    'preview' => ['bg' => '#0a0a14', 'text' => '#ffffff', 'radius' => 14, 'border' => '#ffffff15'],
                ],
                [
                    'key' => 'wordmark',
                    'name' => 'Wordmark',
                    'tags' => ['editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'content', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none', 'padding' => '6',
                        'text_color' => '#ffffff', 'font_family' => 'Playfair Display',
                        'font_weight' => '700',
                        '_social_set' => 'wordmark',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0, 'serif' => true],
                ],
                [
                    'key' => 'glassy',
                    'name' => 'Glassy',
                    'tags' => ['glass', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff14',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff30',
                        'border_radius' => '20', 'shadow_preset' => 'soft',
                        'glass_preset' => 'heavy', 'effect' => 'glass', 'padding' => '14',
                        'text_color' => '#ffffff',
                        '_social_set' => 'glassy',
                    ],
                    'preview' => ['bg' => 'rgba(255,255,255,0.08)', 'text' => '#ffffff', 'radius' => 20, 'border' => '#ffffff40'],
                ],
                [
                    'key' => 'neon_pop',
                    'name' => 'Neon Pop',
                    'tags' => ['neon', 'dark', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a0a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#a78bfa',
                        'border_radius' => '14', 'shadow_type' => 'neon',
                        'shadow_color' => '#a78bfaaa', 'shadow_blur' => 24,
                        'text_color' => '#a78bfa', 'padding' => '12',
                        '_social_set' => 'neon_pop',
                    ],
                    'preview' => ['bg' => '#0a0a0a', 'text' => '#a78bfa', 'radius' => 14, 'border' => '#a78bfa'],
                ],
                [
                    'key' => 'animated_pulse',
                    'name' => 'Animated Pulse',
                    'tags' => ['playful', 'three_d'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ec489922',
                        'border_style' => 'none', 'border_radius' => '999',
                        'shadow_type' => 'glow', 'shadow_color' => '#ec489966', 'shadow_blur' => 24,
                        'padding' => '12',
                        '_social_set' => 'animated_pulse',
                    ],
                    'preview' => ['bg' => 'rgba(236,72,153,0.13)', 'text' => '#ec4899', 'radius' => 999],
                ],
            ],

            // Cover + profile combo block (profile_card_v2). Variants
            // colour the cover band and profile chrome; the renderer
            // already supports cover/avatar/name/title content.
            'cover_profile' => [
                [
                    'key' => 'cover_aurora',
                    'name' => 'Aurora Cover',
                    'tags' => ['bold', 'playful'],
                    'style' => [
                        'display_mode' => 'card',
                        'bg_color' => 'linear-gradient(135deg,#7c3aed,#ec4899,#22d3ee)',
                        'border_style' => 'none', 'border_radius' => '20',
                        'shadow_preset' => 'medium',
                        'text_color' => '#ffffff', 'padding' => '0',
                    ],
                    'preview' => ['bg' => 'linear-gradient(135deg,#7c3aed,#ec4899,#22d3ee)', 'text' => '#ffffff', 'radius' => 20],
                ],
                [
                    'key' => 'cover_editorial',
                    'name' => 'Editorial Cover',
                    'tags' => ['editorial', 'pro', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fafaf9',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e7e5e4',
                        'border_radius' => '12', 'shadow_preset' => 'soft',
                        'text_color' => '#1c1917', 'padding' => '0',
                        'font_family' => 'Playfair Display',
                    ],
                    'preview' => ['bg' => '#fafaf9', 'text' => '#1c1917', 'radius' => 12, 'border' => '#e7e5e4', 'serif' => true],
                ],
                [
                    'key' => 'cover_dark_neon',
                    'name' => 'Dark Neon Cover',
                    'tags' => ['neon', 'dark', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#05010f',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#a78bfa',
                        'border_radius' => '20', 'shadow_type' => 'neon',
                        'shadow_color' => '#a78bfa66', 'shadow_blur' => 30,
                        'text_color' => '#a78bfa', 'padding' => '0',
                    ],
                    'preview' => ['bg' => '#05010f', 'text' => '#a78bfa', 'radius' => 20, 'border' => '#a78bfa'],
                ],
                [
                    'key' => 'cover_glass',
                    'name' => 'Glass Cover',
                    'tags' => ['glass', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff10',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff30',
                        'border_radius' => '24', 'shadow_preset' => 'medium',
                        'glass_preset' => 'heavy', 'effect' => 'glass',
                        'text_color' => '#ffffff', 'padding' => '0',
                    ],
                    'preview' => ['bg' => 'rgba(255,255,255,0.08)', 'text' => '#ffffff', 'radius' => 24, 'border' => '#ffffff40'],
                ],
                [
                    'key' => 'cover_brutalist',
                    'name' => 'Brutalist Cover',
                    'tags' => ['brutalist', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '3', 'border_color' => '#000000',
                        'border_radius' => '0', 'shadow_type' => 'hard',
                        'shadow_color' => '#000000', 'shadow_x' => 8, 'shadow_y' => 8, 'shadow_blur' => 0,
                        'text_color' => '#000000', 'padding' => '0', 'font_weight' => '900',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#000000', 'radius' => 0, 'border' => '#000000', 'shadow' => '8px 8px 0 #000'],
                ],
                [
                    'key' => 'cover_y2k',
                    'name' => 'Y2K Cover',
                    'tags' => ['y2k', 'retro', 'playful'],
                    'style' => [
                        'display_mode' => 'card',
                        'bg_color' => 'linear-gradient(180deg,#a5f3fc,#fbcfe8)',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#7c3aed',
                        'border_radius' => '24', 'shadow_type' => 'hard',
                        'shadow_color' => '#7c3aed', 'shadow_x' => 4, 'shadow_y' => 4, 'shadow_blur' => 0,
                        'text_color' => '#1e1b4b', 'padding' => '0', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => 'linear-gradient(180deg,#a5f3fc,#fbcfe8)', 'text' => '#1e1b4b', 'radius' => 24, 'border' => '#7c3aed'],
                ],
            ],

            // ─── Task #1740: ready-made identity / profile-card designs ──
            //
            // Ten one-click profile-card looks for the profile_card_v1..v4
            // family. Unlike `cover_profile` (which only re-colours the
            // existing cover layout) each of these carries a structural
            // `_profile_layout` token that the public renderer
            // (common/biolink-profile-card.blade.php) dispatches on to
            // reposition the avatar / cover / text / socials. The standard
            // style keys here (bg/border/radius/shadow/text/effect) skin
            // the card; `padding => '0'` because the renderer owns all
            // internal spacing per layout. Web-only — no mobile mirror.
            'profile_identity' => [
                [
                    'key' => 'identity_classic',
                    'name' => 'Classic Creator',
                    'tags' => ['minimal', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '20', 'shadow_preset' => 'soft',
                        'padding' => '0', '_profile_layout' => 'classic_creator',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#0f172a', 'radius' => 20, 'border' => '#e5e7eb'],
                ],
                [
                    'key' => 'identity_glass',
                    'name' => 'Modern Glassmorphism',
                    'tags' => ['glass', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff12',
                        'text_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff33',
                        'border_radius' => '24', 'shadow_preset' => 'medium',
                        'glass_preset' => 'heavy', 'effect' => 'glass',
                        'padding' => '0', '_profile_layout' => 'glass',
                    ],
                    'preview' => ['bg' => 'rgba(255,255,255,0.10)', 'text' => '#ffffff', 'radius' => 24, 'border' => '#ffffff40'],
                ],
                [
                    'key' => 'identity_cover_hero',
                    'name' => 'Cover Overlay Hero',
                    'tags' => ['bold', 'editorial'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0b0b0f',
                        'text_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '20',
                        'shadow_preset' => 'medium',
                        'padding' => '0', '_profile_layout' => 'cover_hero',
                    ],
                    'preview' => ['bg' => '#0b0b0f', 'text' => '#ffffff', 'radius' => 20],
                ],
                [
                    'key' => 'identity_split',
                    'name' => 'Split Card',
                    'tags' => ['minimal', 'editorial'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#f8fafc',
                        'text_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e2e8f0',
                        'border_radius' => '18', 'shadow_preset' => 'soft',
                        'padding' => '0', '_profile_layout' => 'split',
                    ],
                    'preview' => ['bg' => '#f8fafc', 'text' => '#0f172a', 'radius' => 18, 'border' => '#e2e8f0'],
                ],
                [
                    'key' => 'identity_floating',
                    'name' => 'Floating Avatar',
                    'tags' => ['playful', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '22', 'shadow_preset' => 'medium',
                        'padding' => '0', '_profile_layout' => 'floating',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#0f172a', 'radius' => 22, 'border' => '#e5e7eb'],
                ],
                [
                    // Task #5876: photo-first hero column for split desktop
                    // layouts — big circular avatar + social icons only, on
                    // a transparent surface so the page background (usually
                    // a blurred photo) shows through. Name/tagline/links
                    // live in sibling blocks in the page's other column.
                    'key' => 'identity_split_hero',
                    'name' => 'Split Hero',
                    'tags' => ['bold', 'editorial'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'text_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none',
                        'padding' => '0', '_profile_layout' => 'split_hero',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ffffff', 'radius' => 0],
                ],
                [
                    // Screenshot-inspired (July 2026): tall cover with the
                    // white card pulled up over it and the avatar straddling
                    // the card's top edge. The block surface is transparent —
                    // the layout paints its own white card internally.
                    'key' => 'identity_overlap_hero',
                    'name' => 'Overlap Hero',
                    'tags' => ['bold', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'text_color' => '#0f172a',
                        'border_style' => 'none', 'border_radius' => '24',
                        'shadow_preset' => 'none',
                        'padding' => '0', '_profile_layout' => 'overlap_hero',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#0f172a', 'radius' => 24],
                ],
                [
                    // Task #5885: split-hero tile grid. A tall solid-colour
                    // hero panel (script name, letter-spaced tagline, big
                    // photo) designed to sit beside a grid of flat link
                    // tiles on desktop. Square corners on purpose — the
                    // reference look is edge-to-edge flat panels.
                    'key' => 'identity_split_hero_panel',
                    'name' => 'Split Hero Panel',
                    'tags' => ['bold', 'editorial'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#f4c531',
                        'text_color' => '#111827',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_preset' => 'none',
                        'padding' => '0', '_profile_layout' => 'split_hero_panel',
                    ],
                    'preview' => ['bg' => '#f4c531', 'text' => '#111827', 'radius' => 0],
                ],
                [
                    'key' => 'identity_gradient',
                    'name' => 'Gradient Identity Card',
                    'tags' => ['bold', 'playful'],
                    'style' => [
                        'display_mode' => 'card',
                        'bg_color' => 'linear-gradient(150deg,#7c3aed,#d946ef,#fb7185)',
                        'text_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '22',
                        'shadow_preset' => 'medium',
                        'padding' => '0', '_profile_layout' => 'gradient',
                    ],
                    'preview' => ['bg' => 'linear-gradient(150deg,#7c3aed,#d946ef,#fb7185)', 'text' => '#ffffff', 'radius' => 22],
                ],
                [
                    'key' => 'identity_founder',
                    'name' => 'Premium Founder Card',
                    'tags' => ['pro', 'dark'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a0c',
                        'text_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#d4af3766',
                        'border_radius' => '20', 'shadow_type' => 'glow',
                        'shadow_color' => '#d4af3733', 'shadow_blur' => 26,
                        'padding' => '0', '_profile_layout' => 'founder',
                    ],
                    'preview' => ['bg' => '#0a0a0c', 'text' => '#d4af37', 'radius' => 20, 'border' => '#d4af37'],
                ],
                [
                    'key' => 'identity_minimal_dark',
                    'name' => 'Minimal Dark',
                    'tags' => ['minimal', 'dark'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0b0b0f',
                        'text_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#ffffff14',
                        'border_radius' => '18', 'shadow_preset' => 'soft',
                        'padding' => '0', '_profile_layout' => 'minimal_dark',
                    ],
                    'preview' => ['bg' => '#0b0b0f', 'text' => '#ffffff', 'radius' => 18, 'border' => '#ffffff20'],
                ],
                [
                    'key' => 'identity_magazine',
                    'name' => 'Magazine Layout',
                    'tags' => ['editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#1c1917',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e7e5e4',
                        'border_radius' => '14', 'shadow_preset' => 'soft',
                        'font_family' => 'Playfair Display',
                        'padding' => '0', '_profile_layout' => 'magazine',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#1c1917', 'radius' => 14, 'border' => '#e7e5e4', 'serif' => true],
                ],
                [
                    'key' => 'identity_social',
                    'name' => 'Social Profile Style',
                    'tags' => ['minimal', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '18', 'shadow_preset' => 'soft',
                        'padding' => '0', '_profile_layout' => 'social_profile',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#3b82f6', 'radius' => 18, 'border' => '#e5e7eb'],
                ],

                // ─── Task #1745: six layout-driven identity designs ──────
                //
                // These extend the ten looks above and are distinguished
                // primarily by STRUCTURE (not colour): a horizontal name
                // card, a corporate ID badge with a lanyard, a perforated
                // ticket stub, a tilted polaroid, a developer terminal, and
                // a left sidebar accent bar. Each carries a `_profile_layout`
                // token the public renderer dispatches on; padding stays 0
                // because each layout owns its internal spacing.
                [
                    'key' => 'identity_business',
                    'name' => 'Business Card',
                    'tags' => ['pro', 'corporate', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '14', 'shadow_preset' => 'soft',
                        'padding' => '0', '_profile_layout' => 'business_card',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#0f172a', 'radius' => 14, 'border' => '#e5e7eb'],
                ],
                [
                    'key' => 'identity_id_badge',
                    'name' => 'ID Badge',
                    'tags' => ['corporate', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#cbd5e1',
                        'border_radius' => '16', 'shadow_preset' => 'medium',
                        'padding' => '0', '_profile_layout' => 'id_badge',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#2563eb', 'radius' => 16, 'border' => '#cbd5e1'],
                ],
                [
                    'key' => 'identity_ticket',
                    'name' => 'Ticket Stub',
                    'tags' => ['playful', 'retro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fffbeb',
                        'text_color' => '#78350f',
                        'border_style' => 'none', 'border_radius' => '14',
                        'shadow_preset' => 'soft',
                        'font_family' => 'JetBrains Mono',
                        'padding' => '0', '_profile_layout' => 'ticket_stub',
                    ],
                    'preview' => ['bg' => '#fffbeb', 'text' => '#78350f', 'radius' => 14, 'border' => '#f59e0b', 'dashed' => true],
                ],
                [
                    'key' => 'identity_polaroid',
                    'name' => 'Polaroid',
                    'tags' => ['playful', 'handwritten'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#1f2937',
                        'border_style' => 'none', 'border_radius' => '6',
                        'shadow_type' => 'hard', 'shadow_color' => '#00000033',
                        'shadow_x' => 0, 'shadow_y' => 10, 'shadow_blur' => 24,
                        'font_family' => 'Caveat',
                        'padding' => '0', '_profile_layout' => 'polaroid',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#1f2937', 'radius' => 6, 'shadow' => '0 10px 24px #00000033'],
                ],
                [
                    'key' => 'identity_terminal',
                    'name' => 'Terminal',
                    'tags' => ['dark', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#020617',
                        'text_color' => '#4ade80',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#22c55e55',
                        'border_radius' => '10', 'shadow_type' => 'glow',
                        'shadow_color' => '#22c55e33', 'shadow_blur' => 20,
                        'font_family' => 'JetBrains Mono',
                        'padding' => '0', '_profile_layout' => 'terminal',
                    ],
                    'preview' => ['bg' => '#020617', 'text' => '#4ade80', 'radius' => 10, 'border' => '#22c55e'],
                ],
                [
                    'key' => 'identity_sidebar',
                    'name' => 'Sidebar Accent',
                    'tags' => ['minimal', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'text_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e5e7eb',
                        'border_radius' => '16', 'shadow_preset' => 'soft',
                        'padding' => '0', '_profile_layout' => 'sidebar_accent',
                    ],
                    'preview' => ['bg' => 'linear-gradient(90deg,#7c3aed 0,#7c3aed 14%,#ffffff 14%)', 'text' => '#0f172a', 'radius' => 16, 'border' => '#e5e7eb'],
                ],
            ],
        ];
    }

    /**
     * Per-type extras. Keys are block types; values are the bundle ids
     * (resolved via bundles() above) plus optional inline one-offs that
     * are truly type-specific (polaroid for image, neon ticket for
     * coupon). One block type can pull from several bundles.
     */
    private static function typeBundleMap(): array
    {
        return [
            // Links / CTAs.
            'link'             => ['link_actions', 'link_shapes', 'link_buttons'],
            'link_big'         => ['link_actions', 'link_shapes', 'link_buttons', 'headings', 'heading_styles'],
            'featured_pin'     => ['link_actions', 'link_shapes', 'link_buttons'],
            'cta_button'       => ['link_actions', 'link_shapes', 'link_buttons'],
            'external_item'    => ['link_actions', 'link_shapes', 'link_buttons'],

            // Headings.
            'heading'          => ['headings', 'heading_styles'],
            'heading_logo'     => ['headings', 'heading_styles'],
            'verified_heading' => ['headings', 'heading_styles'],

            // Body text / lists / markdown.
            'paragraph'        => ['body_text'],
            'paragraph_rich'   => ['body_text'],
            'markdown'         => ['body_text'],
            'list'             => ['body_text'],
            'list_numbered'    => ['body_text'],
            'list_pricing'     => ['body_text', 'commerce'],

            // Social rows / embeds.
            'socials'          => ['socials', 'social_sets'],
            'socials_multi'    => ['socials', 'social_sets'],
            'socials_custom'   => ['socials', 'social_sets'],
            'instagram'        => ['embed', 'socials', 'social_sets'],
            'instagram_media'  => ['embed', 'socials', 'social_sets'],
            'latest_instagram' => ['embed', 'socials', 'social_sets'],
            'tiktok_profile'   => ['embed', 'socials', 'social_sets'],
            'twitter_profile'  => ['embed', 'socials', 'social_sets'],
            'pinterest_profile'=> ['embed', 'socials', 'social_sets'],
            'snapchat'         => ['embed', 'socials', 'social_sets'],
            'twitter_tweet'    => ['embed'],

            // Video.
            'video'            => ['video'],
            'header_video'     => ['video'],
            'youtube'          => ['video'],
            'youtube_feed'     => ['video'],
            'latest_youtube'   => ['video'],
            'vimeo'            => ['video'],
            'twitch'           => ['video'],
            'kick'             => ['video'],
            'rumble_video'     => ['video'],
            'vk_video'         => ['video'],
            'tiktok_video'     => ['video'],
            'twitter_video'    => ['video'],

            // Embeds / integrations / iframes.
            'iframe_embed'     => ['embed'],
            'custom_html'      => ['embed'],
            'facebook_post'    => ['embed'],
            'reddit_post'      => ['embed'],
            'telegram_post'    => ['embed'],
            'discord_server'   => ['embed'],

            // Forms / lead capture.
            'form'                       => ['form'],
            'contact_form'               => ['form'],
            'email_collector'            => ['form'],
            'email_subscribe'            => ['form'],
            'phone_collector'            => ['form'],
            'direct_message'             => ['form'],
            'whatsapp_widget'            => ['form'],
            'whatsapp_channel_subscribe' => ['form'],
            'whatsapp_number_subscribe'  => ['form'],
            'typeform'                   => ['form', 'embed'],

            // Galleries.
            'image_grid'       => ['gallery', 'gallery_layouts'],
            // image_slider* uses absolute-positioned <img> markup, not a
            // CSS grid container, so the [data-gallery-layout] selectors
            // would be no-ops there. Sliders keep the base `gallery`
            // bundle (transitions, dot variants) only.
            'image_slider'     => ['gallery'],
            'image_slider_v2'  => ['gallery'],

            // Profile / cover combo (cover band + avatar + name + title + bio).
            // All four legacy slots share the Task #1740 `profile_identity`
            // structural designs; v2 also keeps its original `cover_profile`
            // re-colour bundle for back-compat with blocks styled before.
            'profile_card_v1'  => ['profile_identity'],
            'profile_card_v2'  => ['profile_identity', 'cover_profile'],
            'profile_card_v3'  => ['profile_identity'],
            'profile_card_v4'  => ['profile_identity'],

            // Music / audio.
            'spotify'          => ['music'],
            'apple_music'      => ['music'],
            'soundcloud'       => ['music'],
            'tidal'            => ['music'],
            'mixcloud'         => ['music'],
            'anchor_fm'        => ['music'],
            'audio'            => ['music'],

            // Calendar / booking.
            'calendly'         => ['calendar'],
            'calendly_embed'   => ['calendar'],

            // Tip / support.
            'donation'         => ['tip'],
            'buy_me_coffee'    => ['tip'],
            'patreon'          => ['tip'],
            'ko_fi'            => ['tip'],
            'paypal'           => ['tip', 'form'],

            // Commerce / catalog.
            'product'          => ['commerce'],
            'service'          => ['commerce'],
            'catalog'          => ['commerce'],
            'market'           => ['commerce'],
            'price'            => ['commerce'],

            // Timeline / roadmap.
            'timeline'         => ['timeline'],
            'timeline_staged'  => ['timeline'],
            'roadmap'          => ['timeline'],
        ];
    }

    /**
     * Per-type one-off extras kept for backwards compatibility with
     * already-saved blocks that picked these keys before bundles
     * existed. Renaming or removing any of these keys would orphan
     * those blocks (they'd silently fall back to "Custom"), so prefer
     * adding new looks via bundles() instead.
     */
    private static function typeOneOffs(): array
    {
        return [
            'image' => [
                [
                    'key' => 'polaroid',
                    'name' => 'Polaroid',
                    'tags' => ['retro', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'none', 'border_radius' => '6',
                        'shadow_type' => 'soft', 'shadow_color' => '#00000044',
                        'shadow_y' => 8, 'shadow_blur' => 22,
                        'padding_top' => 10, 'padding_left' => 10, 'padding_right' => 10, 'padding_bottom' => 36,
                    ],
                    'preview' => ['bg' => '#fff', 'text' => '#000', 'radius' => 6, 'shadow' => '0 8px 22px #00000044'],
                ],
                [
                    'key' => 'magazine_cutout',
                    'name' => 'Magazine Cutout',
                    'tags' => ['maximalist', 'editorial', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '4', 'border_color' => '#facc15',
                        'border_radius' => '0', 'shadow_type' => 'hard',
                        'shadow_color' => '#000000', 'shadow_x' => 6, 'shadow_y' => 6, 'shadow_blur' => 0,
                        'padding' => '6',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#000', 'radius' => 0, 'border' => '#facc15', 'shadow' => '6px 6px 0 #000'],
                ],
                // ── Mask presets (Task #1041). The mask itself is applied
                //    by `BiolinkBlock::buildImageInlineStyle()` via
                //    `_image_style.mask_shape` (already supports circle,
                //    diamond, hexagon, octagon, star, blob, arch). The
                //    variant only colours the surrounding chrome — the
                //    creator picks the mask separately in the image form
                //    so the variant stays content-agnostic.
                [
                    'key' => 'mask_circle',
                    'name' => 'Mask · Circle',
                    'tags' => ['minimal', 'editorial'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '999',
                        'shadow_preset' => 'soft', 'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 999],
                ],
                [
                    'key' => 'mask_arch',
                    'name' => 'Mask · Arch',
                    'tags' => ['editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '40',
                        'shadow_preset' => 'soft', 'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 40],
                ],
                [
                    'key' => 'mask_blob',
                    'name' => 'Mask · Blob',
                    'tags' => ['playful', 'maximalist'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '60',
                        'shadow_preset' => 'soft', 'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 60],
                ],
                [
                    'key' => 'mask_hexagon',
                    'name' => 'Mask · Hexagon',
                    'tags' => ['three_d', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '12',
                        'shadow_preset' => 'medium', 'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 12],
                ],
                [
                    'key' => 'mask_diamond',
                    'name' => 'Mask · Diamond',
                    'tags' => ['editorial', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '8',
                        'shadow_preset' => 'soft', 'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 8],
                ],
                [
                    'key' => 'mask_star',
                    'name' => 'Mask · Star',
                    'tags' => ['playful', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '8',
                        'shadow_type' => 'glow', 'shadow_color' => '#fbbf2466', 'shadow_blur' => 22,
                        'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fbbf24', 'radius' => 8],
                ],
                [
                    'key' => 'mask_heart',
                    'name' => 'Mask · Heart',
                    'tags' => ['playful', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_type' => 'glow', 'shadow_color' => '#ec489966', 'shadow_blur' => 18,
                        'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#ec4899', 'radius' => 0],
                ],
                [
                    'key' => 'mask_torn',
                    'name' => 'Mask · Torn Edge',
                    'tags' => ['editorial', 'maximalist'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'none', 'border_radius' => '0',
                        'shadow_type' => 'soft', 'shadow_color' => '#00000040', 'shadow_y' => 6, 'shadow_blur' => 14,
                        'padding' => '0',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 0],
                ],
                [
                    'key' => 'film_strip',
                    'name' => 'Film Strip',
                    'tags' => ['retro', 'editorial'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a0a',
                        'border_style' => 'solid', 'border_width' => '12', 'border_color' => '#0a0a0a',
                        'border_radius' => '4', 'shadow_preset' => 'medium',
                        'padding' => '4',
                    ],
                    'preview' => ['bg' => '#0a0a0a', 'text' => '#fafaf9', 'radius' => 4, 'border' => '#0a0a0a'],
                ],
            ],
            'avatar' => [
                [
                    'key' => 'ring_glow',
                    'name' => 'Ring Glow',
                    'tags' => ['neon', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#a78bfa',
                        'border_radius' => '999', 'shadow_type' => 'neon',
                        'shadow_color' => '#a78bfa80', 'shadow_blur' => 30,
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#a78bfa', 'radius' => 999, 'border' => '#a78bfa'],
                ],
                [
                    'key' => 'mono_frame',
                    'name' => 'Mono Frame',
                    'tags' => ['corporate', 'minimal'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => 'transparent',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#e5e7eb',
                        'border_radius' => '999', 'shadow_preset' => 'soft',
                    ],
                    'preview' => ['bg' => 'transparent', 'text' => '#fff', 'radius' => 999, 'border' => '#e5e7eb'],
                ],
            ],
            'product' => [
                [
                    'key' => 'price_tag',
                    'name' => 'Price Tag',
                    'tags' => ['retro', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fef3c7',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#dc2626',
                        'border_radius' => '4', 'shadow_type' => 'hard',
                        'shadow_color' => '#dc2626', 'shadow_x' => 3, 'shadow_y' => 3, 'shadow_blur' => 0,
                        'text_color' => '#7f1d1d', 'padding' => '14', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => '#fef3c7', 'text' => '#7f1d1d', 'radius' => 4, 'border' => '#dc2626'],
                ],
            ],
            'coupon' => [
                [
                    'key' => 'neon_ticket',
                    'name' => 'Neon Ticket',
                    'tags' => ['neon', 'bold'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a14',
                        'border_style' => 'dashed', 'border_width' => '2', 'border_color' => '#22d3ee',
                        'border_radius' => '12', 'shadow_type' => 'neon',
                        'shadow_color' => '#22d3ee99', 'shadow_blur' => 24,
                        'text_color' => '#67e8f9', 'padding' => '16', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => '#0a0a14', 'text' => '#67e8f9', 'radius' => 12, 'border' => '#22d3ee', 'dashed' => true],
                ],
                [
                    'key' => 'y2k_voucher',
                    'name' => 'Y2K Voucher',
                    'tags' => ['y2k', 'retro', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#a5f3fc',
                        'border_style' => 'dashed', 'border_width' => '2', 'border_color' => '#7c3aed',
                        'border_radius' => '14', 'shadow_type' => 'hard',
                        'shadow_color' => '#ec4899', 'shadow_x' => 4, 'shadow_y' => 4, 'shadow_blur' => 0,
                        'text_color' => '#581c87', 'padding' => '14', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => '#a5f3fc', 'text' => '#581c87', 'radius' => 14, 'border' => '#7c3aed', 'dashed' => true],
                ],
            ],
            'testimonials' => [
                [
                    'key' => 'quote_card',
                    'name' => 'Quote Card',
                    'tags' => ['editorial', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#1e1b4b',
                        'border_style' => 'none', 'border_radius' => '20',
                        'shadow_type' => 'soft', 'shadow_color' => '#00000033',
                        'shadow_y' => 10, 'shadow_blur' => 30,
                        'text_color' => '#e0e7ff', 'padding' => '24', 'font_family' => 'Playfair Display',
                    ],
                    'preview' => ['bg' => '#1e1b4b', 'text' => '#e0e7ff', 'radius' => 20, 'serif' => true],
                ],
                [
                    'key' => 'sticky_quote',
                    'name' => 'Sticky Quote',
                    'tags' => ['handwritten', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fef08a',
                        'border_style' => 'none', 'border_radius' => '4',
                        'shadow_type' => 'soft', 'shadow_color' => '#92400e55',
                        'shadow_y' => 6, 'shadow_blur' => 14,
                        'text_color' => '#422006', 'padding' => '18', 'font_family' => 'Caveat',
                    ],
                    'preview' => ['bg' => '#fef08a', 'text' => '#422006', 'radius' => 4],
                ],
            ],
            'faq' => [
                [
                    'key' => 'paper_card',
                    'name' => 'Paper Card',
                    'tags' => ['minimal', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#fafaf9',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#e7e5e4',
                        'border_radius' => '12', 'shadow_preset' => 'soft',
                        'text_color' => '#1c1917', 'padding' => '18',
                    ],
                    'preview' => ['bg' => '#fafaf9', 'text' => '#1c1917', 'radius' => 12, 'border' => '#e7e5e4'],
                ],
                [
                    'key' => 'corporate_qa',
                    'name' => 'Corporate Q&A',
                    'tags' => ['corporate', 'minimal', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#d1d5db',
                        'border_radius' => '6', 'shadow_preset' => 'none',
                        'text_color' => '#111827', 'padding' => '16',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 6, 'border' => '#d1d5db'],
                ],
            ],
            'faq_v2' => [
                [
                    'key' => 'corporate_qa',
                    'name' => 'Corporate Q&A',
                    'tags' => ['corporate', 'minimal', 'pro'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ffffff',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#d1d5db',
                        'border_radius' => '6', 'shadow_preset' => 'none',
                        'text_color' => '#111827', 'padding' => '16',
                    ],
                    'preview' => ['bg' => '#ffffff', 'text' => '#111827', 'radius' => 6, 'border' => '#d1d5db'],
                ],
            ],
            'countdown' => [
                [
                    'key' => 'flip_clock',
                    'name' => 'Flip Clock',
                    'tags' => ['retro', 'three_d'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0a0a0a',
                        'border_style' => 'solid', 'border_width' => '1', 'border_color' => '#27272a',
                        'border_radius' => '8', 'shadow_type' => 'hard',
                        'shadow_color' => '#000', 'shadow_y' => 4, 'shadow_blur' => 0,
                        'text_color' => '#fbbf24', 'padding' => '16', 'font_family' => 'JetBrains Mono',
                    ],
                    'preview' => ['bg' => '#0a0a0a', 'text' => '#fbbf24', 'radius' => 8, 'border' => '#27272a'],
                ],
                [
                    'key' => 'pixel_clock',
                    'name' => 'Pixel Clock',
                    'tags' => ['y2k', 'retro', 'playful'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#0f172a',
                        'border_style' => 'solid', 'border_width' => '2', 'border_color' => '#22d3ee',
                        'border_radius' => '4', 'shadow_type' => 'neon',
                        'shadow_color' => '#22d3ee99', 'shadow_blur' => 18,
                        'text_color' => '#a5f3fc', 'padding' => '14', 'font_family' => 'JetBrains Mono',
                    ],
                    'preview' => ['bg' => '#0f172a', 'text' => '#a5f3fc', 'radius' => 4, 'border' => '#22d3ee'],
                ],
            ],
            'cta_button' => [
                [
                    'key' => 'big_action',
                    'name' => 'Big Action',
                    'tags' => ['bold', 'three_d'],
                    'style' => [
                        'display_mode' => 'card', 'bg_color' => '#ef4444',
                        'border_style' => 'none', 'border_radius' => '14',
                        'shadow_type' => 'hard', 'shadow_color' => '#7f1d1d',
                        'shadow_x' => 0, 'shadow_y' => 6, 'shadow_blur' => 0,
                        'text_color' => '#fff', 'padding' => '20', 'font_weight' => '700',
                    ],
                    'preview' => ['bg' => '#ef4444', 'text' => '#fff', 'radius' => 14, 'shadow' => '0 6px 0 #7f1d1d'],
                ],
            ],
        ];
    }

    /**
     * Returns the variants that should be offered for the given block type.
     * Common variants always come first, followed by any type-specific
     * extras (bundles + one-offs). Order is stable so the gallery doesn't
     * shuffle on save. Variant keys are de-duplicated by first occurrence
     * so a variant a block has already saved can never be hidden by a
     * later bundle that happens to ship the same key.
     */
    public static function forType(string $type): array
    {
        $variants = self::commonVariants();

        $bundles = self::bundles();
        $bundleIds = self::typeBundleMap()[$type] ?? [];
        foreach ($bundleIds as $bundleId) {
            foreach (($bundles[$bundleId] ?? []) as $v) {
                $variants[] = $v;
            }
        }

        foreach ((self::typeOneOffs()[$type] ?? []) as $v) {
            $variants[] = $v;
        }

        $seen = [];
        $unique = [];
        foreach ($variants as $v) {
            if (isset($seen[$v['key']])) continue;
            $seen[$v['key']] = true;
            $unique[] = $v;
        }
        return $unique;
    }

    /**
     * Map a (block type, variant shape) pair to one of the small sketch
     * "kinds" the Designs gallery knows how to render — button, heading,
     * image, avatar, divider, plain_link, image_btn, button_outline, or
     * a generic text sample. Kept here (instead of in the Blade view or
     * the JS gallery) so the static fallback thumbnails and the live-
     * preview thumbnails always pick the same kind for the same block
     * — no double-rendered "text on white" surprises when the live
     * preview swaps in for an image or divider block.
     */
    public static function shapeKindFor(string $blockType, ?string $variantShape): string
    {
        return match (true) {
            $variantShape === 'plain_text' => 'plain_link',
            $variantShape === 'image_full' => 'image_btn',
            $variantShape === 'outline'    => 'button_outline',
            in_array($blockType, ['avatar'], true) => 'avatar',
            in_array($blockType, ['image', 'photo', 'banner', 'header_image', 'image_grid', 'image_slider', 'image_slider_v2', 'verified_avatar'], true) => 'image',
            in_array($blockType, ['link', 'link_big', 'button', 'cta', 'cta_button', 'social', 'url', 'featured_pin', 'external_item'], true) => 'button',
            in_array($blockType, ['heading', 'title', 'heading_logo', 'verified_heading'], true) => 'heading',
            in_array($blockType, ['divider', 'spacer'], true) => 'divider',
            default => 'text',
        };
    }

    public static function find(string $type, string $key): ?array
    {
        foreach (self::forType($type) as $v) {
            if ($v['key'] === $key) return $v;
        }
        return null;
    }

    /** All variant keys valid for a given block type. */
    public static function validKeys(string $type): array
    {
        return array_map(fn($v) => $v['key'], self::forType($type));
    }
}
