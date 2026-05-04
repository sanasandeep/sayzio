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
    public const VERSION = 1;

    public const TAGS = [
        'minimal'      => 'Minimal',
        'bold'         => 'Bold',
        'playful'      => 'Playful',
        'pro'          => 'Pro',
        'dark'         => 'Dark',
        'retro'        => 'Retro',
        'glass'        => 'Glass',
        'three_d'      => '3D',
        'neon'         => 'Neon',
        'handwritten'  => 'Handwritten',
        'brutalist'    => 'Brutalist',
        'editorial'    => 'Editorial',
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
        ];
    }

    /**
     * Per-type extras. Keys are block types; values are extra variants that
     * make sense only for that type (e.g. polaroid for image/avatar).
     */
    private static function typeExtras(): array
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
     * extras. Order is stable so the gallery doesn't shuffle on save.
     */
    public static function forType(string $type): array
    {
        $variants = self::commonVariants();
        $extras = self::typeExtras()[$type] ?? [];
        return array_merge($variants, $extras);
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
