<?php

namespace App\Modules\User\Support;

/**
 * Authoritative catalogs of QR Studio shape and frame IDs.
 *
 * IDs are the single source of truth shared between PHP validation
 * (sanitizeDesign / sanitizeFrame) and the JavaScript renderer. If a
 * new shape or frame is added on the JS side, its ID must be added
 * here, otherwise it will be sanitized away on save.
 *
 * Each list is ordered & grouped by category for the UI pickers.
 */
class QrCodeCatalog
{
    // -------------------- DOT (module) shapes --------------------
    // 60 distinct dot shapes across 4 categories.
    public static function dotShapes(): array
    {
        return [
            'Geometric' => [
                'square','dot','rounded','rounded-lg','diamond','plus','x-mark',
                'square-tilted','oval-h','oval-v','pill-h','pill-v','dash-h','dash-v',
                'square-tl','square-tr','square-bl','square-br',
                'square-top','square-bottom','square-left','square-right',
                'half-circle-top','half-circle-bottom','half-circle-left','half-circle-right',
            ],
            'Polygons' => [
                'triangle-up','triangle-down','triangle-left','triangle-right',
                'hexagon','hexagon-rotated','octagon','pentagon','kite','parallelogram','chevron-right','chevron-up',
            ],
            'Decorative' => [
                'star4','star5','star6','star8','heart','flower','flower6','gear','sparkle','cross-pattee',
            ],
            'Organic' => [
                'leaf','leaf-mirror','drop','drop-rotated','blob','arrow-up','arrow-right','arrow-left','arrow-down',
                'ring','donut','square-with-hole','plus-thick','plus-rounded','dotted-square','double-square',
            ],
        ];
    }

    // -------------------- OUTER eye (corner-square) shapes --------------------
    public static function outerEyeShapes(): array
    {
        return [
            'Classic' => [
                'square','rounded','extra-rounded','dot','soft-square','beveled-square','double-square','double-circle',
            ],
            'Asymmetric' => [
                'leaf-tl','leaf-tr','leaf-bl','leaf-br',
                'rounded-tl','rounded-tr','rounded-bl','rounded-br',
                'rounded-tl-br','rounded-tr-bl',
                'cut-tl','cut-tr','cut-bl','cut-br',
            ],
            'Polygons' => [
                'hexagon','octagon','diamond','triangle','pentagon','star','plus-frame','x-frame',
                'shield','badge','ticket','plaque',
            ],
            'Decorative' => [
                'flower','gear','sparkle-frame','heart-frame','ribbon-frame','dotted-frame','dashed-frame','double-line',
                'thick-circle','thick-square','soft-rounded','rounded-pill-h','rounded-pill-v',
                'half-rounded-top','half-rounded-bottom','half-rounded-left','half-rounded-right',
            ],
        ];
    }

    // -------------------- INNER eye (corner-dot) shapes --------------------
    public static function innerEyeShapes(): array
    {
        return [
            'Geometric' => [
                'dot','square','rounded','extra-rounded','diamond','oval-h','oval-v','pill-h','pill-v',
                'square-tl','square-tr','square-bl','square-br',
                'half-circle-top','half-circle-bottom','half-circle-left','half-circle-right',
            ],
            'Polygons' => [
                'triangle-up','triangle-down','triangle-left','triangle-right',
                'hexagon','octagon','pentagon','kite','chevron-right','chevron-up','parallelogram',
            ],
            'Decorative' => [
                'star4','star5','star6','star8','heart','plus','x','flower','gear','sparkle','cross-pattee',
            ],
            'Organic' => [
                'leaf','leaf-mirror','drop','drop-rotated','blob','arrow-up','arrow-right',
                'ring','donut','square-with-hole','plus-thick','double-dot','double-square',
            ],
        ];
    }

    // -------------------- FRAME templates --------------------
    public static function frames(): array
    {
        return [
            'None' => [
                'none',
            ],
            'Scan-Me Bars' => [
                'scan-me-bottom','scan-me-top','scan-me-rounded','scan-me-pill','scan-me-double',
                'scan-me-bar','scan-me-classic','scan-me-bold',
            ],
            'Speech & Bubbles' => [
                'bubble-down','bubble-up','bubble-left','bubble-right','cloud','thought-bubble',
            ],
            'Ribbons & Banners' => [
                'ribbon-bottom','ribbon-top','banner-curve-top','banner-curve-bottom','banner-fold',
                'ribbon-bow','tape-top','tape-bottom',
            ],
            'Tickets & Tags' => [
                'ticket','ticket-perforated','price-tag','tag-left','tag-right','luggage-tag','coupon',
            ],
            'Badges & Plaques' => [
                'badge-circle','badge-square','starburst','shield','plaque','seal','medal',
            ],
            'Arrows & Callouts' => [
                'arrow-down','arrow-up','arrow-left','arrow-right','callout-box',
                'chevron-down','chevron-up',
            ],
            'Devices' => [
                'phone-frame','tablet-frame','laptop-frame','watch-frame','tv-frame','polaroid','photo',
            ],
            'Geometric Frames' => [
                'rounded-card','minimal-line','double-border','dashed-border','dotted-border','corners-only',
                'diamond-frame','hexagon-frame','octagon-frame','gradient-border','neon-border','shadow-soft',
            ],
        ];
    }

    /** Flat list of all valid IDs for a given catalog. */
    public static function flatIds(array $grouped): array
    {
        $out = [];
        foreach ($grouped as $items) {
            foreach ($items as $id) $out[] = $id;
        }
        return $out;
    }

    public static function dotIds(): array      { return self::flatIds(self::dotShapes()); }
    public static function outerEyeIds(): array { return self::flatIds(self::outerEyeShapes()); }
    public static function innerEyeIds(): array { return self::flatIds(self::innerEyeShapes()); }
    public static function frameIds(): array    { return self::flatIds(self::frames()); }

    /** Whitelisted font families for frame text. */
    public static function fonts(): array
    {
        return [
            'Inter','Roboto','Poppins','Montserrat','Playfair Display','Bebas Neue','Pacifico',
            'Oswald','Raleway','Lobster','Anton','Dancing Script','Caveat','Fira Sans','Nunito',
        ];
    }

    // -------------------- DESIGN PRESETS (Templates) --------------------
    /**
     * Curated one-click design presets that set shapes, colors, gradients
     * and an optional frame. These are partial designs — when applied in
     * the builder, they overwrite only the keys they declare and leave
     * payload, uploaded logos, output size, and quiet zone untouched.
     *
     * Every shape/frame/font ID below MUST exist in the matching catalog
     * above; otherwise sanitizeDesign() will reject it on save.
     */
    public static function presets(): array
    {
        $g = fn(string $type, string $from, string $to, int $angle, bool $on = true) => [
            'enabled' => $on, 'type' => $type, 'from' => $from, 'to' => $to, 'angle' => $angle,
        ];
        $off = ['enabled' => false, 'type' => 'linear', 'from' => '#000000', 'to' => '#000000', 'angle' => 0];
        $frame = fn(string $tpl, string $bg, string $text, string $font = 'Inter') => [
            'template' => $tpl, 'font' => $font, 'bg_color' => $bg, 'text_color' => $text,
        ];

        return [
            [
                'id' => 'classic', 'name' => 'Classic', 'tagline' => 'Print-ready black & white',
                'design' => [
                    'dot_style' => 'square', 'corner_square_style' => 'square', 'corner_dot_style' => 'square',
                    'fg_color' => '#000000', 'bg_color' => '#ffffff', 'transparent_bg' => false,
                    'corner_square_color' => '#000000', 'corner_dot_color' => '#000000',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('none', '#000000', '#ffffff'),
                ],
            ],
            [
                'id' => 'midnight', 'name' => 'Midnight', 'tagline' => 'Soft rounded on deep navy',
                'design' => [
                    'dot_style' => 'rounded', 'corner_square_style' => 'extra-rounded', 'corner_dot_style' => 'dot',
                    'fg_color' => '#e2e8f0', 'bg_color' => '#0b1226', 'transparent_bg' => false,
                    'corner_square_color' => '#5b8def', 'corner_dot_color' => '#5b8def',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#0b1226', '#1e2a52', 180),
                    'frame' => $frame('none', '#0b1226', '#e2e8f0'),
                ],
            ],
            [
                'id' => 'neon', 'name' => 'Neon', 'tagline' => 'Cyan/magenta glow on black',
                'design' => [
                    'dot_style' => 'dot', 'corner_square_style' => 'thick-circle', 'corner_dot_style' => 'dot',
                    'fg_color' => '#22d3ee', 'bg_color' => '#0a0a12', 'transparent_bg' => false,
                    'corner_square_color' => '#f472b6', 'corner_dot_color' => '#22d3ee',
                    'gradient' => $g('linear', '#22d3ee', '#f472b6', 135),
                    'eye_outer_gradient' => $g('linear', '#f472b6', '#a78bfa', 90),
                    'eye_inner_gradient' => $off,
                    'bg_gradient' => $off,
                    'frame' => $frame('neon-border', '#0a0a12', '#22d3ee', 'Bebas Neue'),
                ],
            ],
            [
                'id' => 'sunset', 'name' => 'Sunset', 'tagline' => 'Warm orange to pink gradient',
                'design' => [
                    'dot_style' => 'rounded-lg', 'corner_square_style' => 'extra-rounded', 'corner_dot_style' => 'rounded',
                    'fg_color' => '#f97316', 'bg_color' => '#fff7ed', 'transparent_bg' => false,
                    'corner_square_color' => '#db2777', 'corner_dot_color' => '#f97316',
                    'gradient' => $g('linear', '#f97316', '#db2777', 45),
                    'eye_outer_gradient' => $g('linear', '#db2777', '#7c3aed', 90),
                    'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#fff7ed', '#fde2e4', 180),
                    'frame' => $frame('scan-me-rounded', '#db2777', '#ffffff', 'Poppins'),
                ],
            ],
            [
                'id' => 'forest', 'name' => 'Forest', 'tagline' => 'Organic leaves in deep green',
                'design' => [
                    'dot_style' => 'leaf', 'corner_square_style' => 'leaf-tl', 'corner_dot_style' => 'leaf',
                    'fg_color' => '#14532d', 'bg_color' => '#f0fdf4', 'transparent_bg' => false,
                    'corner_square_color' => '#166534', 'corner_dot_color' => '#15803d',
                    'gradient' => $g('linear', '#14532d', '#65a30d', 135),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $off,
                    'frame' => $frame('badge-circle', '#14532d', '#f0fdf4', 'Playfair Display'),
                ],
            ],
            [
                'id' => 'corporate', 'name' => 'Corporate', 'tagline' => 'Crisp navy, scan-me bar',
                'design' => [
                    'dot_style' => 'square', 'corner_square_style' => 'soft-square', 'corner_dot_style' => 'square',
                    'fg_color' => '#1e293b', 'bg_color' => '#ffffff', 'transparent_bg' => false,
                    'corner_square_color' => '#0ea5e9', 'corner_dot_color' => '#1e293b',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('scan-me-classic', '#1e293b', '#ffffff', 'Inter'),
                ],
            ],
            [
                'id' => 'pastel-sticker', 'name' => 'Pastel Sticker', 'tagline' => 'Soft cloud bubble',
                'design' => [
                    'dot_style' => 'rounded', 'corner_square_style' => 'rounded', 'corner_dot_style' => 'rounded',
                    'fg_color' => '#7c3aed', 'bg_color' => '#fdf4ff', 'transparent_bg' => false,
                    'corner_square_color' => '#ec4899', 'corner_dot_color' => '#06b6d4',
                    'gradient' => $g('linear', '#a78bfa', '#f0abfc', 60),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('radial', '#fdf4ff', '#fce7f3', 0),
                    'frame' => $frame('cloud', '#fbcfe8', '#7c3aed', 'Pacifico'),
                ],
            ],
            [
                'id' => 'retro-arcade', 'name' => 'Retro Arcade', 'tagline' => 'Pixel power, bold yellow',
                'design' => [
                    'dot_style' => 'square', 'corner_square_style' => 'square', 'corner_dot_style' => 'square',
                    'fg_color' => '#facc15', 'bg_color' => '#1e1b4b', 'transparent_bg' => false,
                    'corner_square_color' => '#ec4899', 'corner_dot_color' => '#22d3ee',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#1e1b4b', '#312e81', 180),
                    'frame' => $frame('scan-me-bold', '#ec4899', '#facc15', 'Bebas Neue'),
                ],
            ],
            [
                'id' => 'royal', 'name' => 'Royal', 'tagline' => 'Purple & gold elegance',
                'design' => [
                    'dot_style' => 'diamond', 'corner_square_style' => 'beveled-square', 'corner_dot_style' => 'diamond',
                    'fg_color' => '#4c1d95', 'bg_color' => '#fffbeb', 'transparent_bg' => false,
                    'corner_square_color' => '#b45309', 'corner_dot_color' => '#b45309',
                    'gradient' => $g('linear', '#4c1d95', '#7c3aed', 90),
                    'eye_outer_gradient' => $g('linear', '#b45309', '#facc15', 45),
                    'eye_inner_gradient' => $off,
                    'bg_gradient' => $off,
                    'frame' => $frame('plaque', '#4c1d95', '#facc15', 'Playfair Display'),
                ],
            ],
            [
                'id' => 'coral-pop', 'name' => 'Coral Pop', 'tagline' => 'Friendly coral with banner',
                'design' => [
                    'dot_style' => 'rounded', 'corner_square_style' => 'extra-rounded', 'corner_dot_style' => 'dot',
                    'fg_color' => '#fb7185', 'bg_color' => '#ffffff', 'transparent_bg' => false,
                    'corner_square_color' => '#fb7185', 'corner_dot_color' => '#fb7185',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('ribbon-bottom', '#fb7185', '#ffffff', 'Montserrat'),
                ],
            ],
            [
                'id' => 'mono-line', 'name' => 'Mono Line', 'tagline' => 'Minimal double border',
                'design' => [
                    'dot_style' => 'square', 'corner_square_style' => 'double-square', 'corner_dot_style' => 'square',
                    'fg_color' => '#111827', 'bg_color' => '#ffffff', 'transparent_bg' => false,
                    'corner_square_color' => '#111827', 'corner_dot_color' => '#111827',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('double-border', '#ffffff', '#111827', 'Inter'),
                ],
            ],
            [
                'id' => 'mint-fresh', 'name' => 'Mint Fresh', 'tagline' => 'Clean teal on cream',
                'design' => [
                    'dot_style' => 'rounded-lg', 'corner_square_style' => 'soft-rounded', 'corner_dot_style' => 'rounded',
                    'fg_color' => '#0d9488', 'bg_color' => '#f0fdfa', 'transparent_bg' => false,
                    'corner_square_color' => '#0d9488', 'corner_dot_color' => '#0d9488',
                    'gradient' => $g('linear', '#0d9488', '#22d3ee', 135),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#f0fdfa', '#ccfbf1', 180),
                    'frame' => $frame('rounded-card', '#0d9488', '#ffffff', 'Nunito'),
                ],
            ],
            [
                'id' => 'inkblot', 'name' => 'Inkblot', 'tagline' => 'Organic blobs, hand-drawn vibe',
                'design' => [
                    'dot_style' => 'blob', 'corner_square_style' => 'soft-rounded', 'corner_dot_style' => 'blob',
                    'fg_color' => '#1f2937', 'bg_color' => '#fef3c7', 'transparent_bg' => false,
                    'corner_square_color' => '#1f2937', 'corner_dot_color' => '#b45309',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('thought-bubble', '#fef3c7', '#1f2937', 'Caveat'),
                ],
            ],
            [
                'id' => 'holiday-red', 'name' => 'Holiday Red', 'tagline' => 'Festive red on cream',
                'design' => [
                    'dot_style' => 'star5', 'corner_square_style' => 'star', 'corner_dot_style' => 'star5',
                    'fg_color' => '#b91c1c', 'bg_color' => '#fff1e6', 'transparent_bg' => false,
                    'corner_square_color' => '#15803d', 'corner_dot_color' => '#b91c1c',
                    'gradient' => $g('linear', '#b91c1c', '#7f1d1d', 180),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('ribbon-bow', '#b91c1c', '#fff1e6', 'Lobster'),
                ],
            ],
            [
                'id' => 'ocean-wave', 'name' => 'Ocean Wave', 'tagline' => 'Cool blue gradient drops',
                'design' => [
                    'dot_style' => 'drop', 'corner_square_style' => 'extra-rounded', 'corner_dot_style' => 'drop',
                    'fg_color' => '#0369a1', 'bg_color' => '#f0f9ff', 'transparent_bg' => false,
                    'corner_square_color' => '#0ea5e9', 'corner_dot_color' => '#0369a1',
                    'gradient' => $g('linear', '#0369a1', '#06b6d4', 135),
                    'eye_outer_gradient' => $g('linear', '#0ea5e9', '#0369a1', 90), 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#f0f9ff', '#e0f2fe', 180),
                    'frame' => $frame('bubble-down', '#0369a1', '#f0f9ff', 'Nunito'),
                ],
            ],
            [
                'id' => 'gold-luxe', 'name' => 'Gold Luxe', 'tagline' => 'Black & gold premium seal',
                'design' => [
                    'dot_style' => 'diamond', 'corner_square_style' => 'badge', 'corner_dot_style' => 'diamond',
                    'fg_color' => '#facc15', 'bg_color' => '#0a0a0a', 'transparent_bg' => false,
                    'corner_square_color' => '#facc15', 'corner_dot_color' => '#fde68a',
                    'gradient' => $g('linear', '#facc15', '#b45309', 45),
                    'eye_outer_gradient' => $g('linear', '#fde68a', '#b45309', 90), 'eye_inner_gradient' => $off,
                    'bg_gradient' => $off,
                    'frame' => $frame('seal', '#0a0a0a', '#facc15', 'Playfair Display'),
                ],
            ],
            [
                'id' => 'bubblegum', 'name' => 'Bubblegum', 'tagline' => 'Playful pink pills',
                'design' => [
                    'dot_style' => 'pill-v', 'corner_square_style' => 'rounded-pill-v', 'corner_dot_style' => 'pill-v',
                    'fg_color' => '#db2777', 'bg_color' => '#fdf2f8', 'transparent_bg' => false,
                    'corner_square_color' => '#db2777', 'corner_dot_color' => '#f472b6',
                    'gradient' => $g('linear', '#db2777', '#f472b6', 60),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('radial', '#fdf2f8', '#fce7f3', 0),
                    'frame' => $frame('scan-me-pill', '#db2777', '#ffffff', 'Pacifico'),
                ],
            ],
            [
                'id' => 'matrix', 'name' => 'Matrix', 'tagline' => 'Neon green on black grid',
                'design' => [
                    'dot_style' => 'square', 'corner_square_style' => 'square', 'corner_dot_style' => 'square',
                    'fg_color' => '#22c55e', 'bg_color' => '#020617', 'transparent_bg' => false,
                    'corner_square_color' => '#4ade80', 'corner_dot_color' => '#22c55e',
                    'gradient' => $g('linear', '#22c55e', '#16a34a', 90),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#020617', '#0f172a', 180),
                    'frame' => $frame('corners-only', '#020617', '#22c55e', 'Oswald'),
                ],
            ],
            [
                'id' => 'terracotta', 'name' => 'Terracotta', 'tagline' => 'Earthy clay & sand',
                'design' => [
                    'dot_style' => 'rounded', 'corner_square_style' => 'soft-rounded', 'corner_dot_style' => 'rounded',
                    'fg_color' => '#9a3412', 'bg_color' => '#fef6ee', 'transparent_bg' => false,
                    'corner_square_color' => '#c2410c', 'corner_dot_color' => '#9a3412',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#fef6ee', '#fde9d7', 180),
                    'frame' => $frame('rounded-card', '#9a3412', '#fef6ee', 'Raleway'),
                ],
            ],
            [
                'id' => 'arctic', 'name' => 'Arctic', 'tagline' => 'Icy minimal hexagons',
                'design' => [
                    'dot_style' => 'hexagon', 'corner_square_style' => 'hexagon', 'corner_dot_style' => 'hexagon',
                    'fg_color' => '#1e3a8a', 'bg_color' => '#f8fafc', 'transparent_bg' => false,
                    'corner_square_color' => '#3b82f6', 'corner_dot_color' => '#1e3a8a',
                    'gradient' => $g('linear', '#1e3a8a', '#60a5fa', 135),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#f8fafc', '#e2e8f0', 180),
                    'frame' => $frame('hexagon-frame', '#1e3a8a', '#f8fafc', 'Montserrat'),
                ],
            ],
            [
                'id' => 'vaporwave', 'name' => 'Vaporwave', 'tagline' => 'Retro pink-purple haze',
                'design' => [
                    'dot_style' => 'rounded-lg', 'corner_square_style' => 'extra-rounded', 'corner_dot_style' => 'rounded',
                    'fg_color' => '#a21caf', 'bg_color' => '#1e1b4b', 'transparent_bg' => false,
                    'corner_square_color' => '#22d3ee', 'corner_dot_color' => '#f472b6',
                    'gradient' => $g('linear', '#f472b6', '#22d3ee', 120),
                    'eye_outer_gradient' => $g('linear', '#22d3ee', '#a78bfa', 90), 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#1e1b4b', '#4c1d95', 160),
                    'frame' => $frame('neon-border', '#1e1b4b', '#f472b6', 'Bebas Neue'),
                ],
            ],
            [
                'id' => 'cafe', 'name' => 'Café', 'tagline' => 'Warm coffee tones, tag',
                'design' => [
                    'dot_style' => 'dot', 'corner_square_style' => 'thick-circle', 'corner_dot_style' => 'dot',
                    'fg_color' => '#44403c', 'bg_color' => '#faf6f0', 'transparent_bg' => false,
                    'corner_square_color' => '#78350f', 'corner_dot_color' => '#44403c',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('price-tag', '#78350f', '#faf6f0', 'Caveat'),
                ],
            ],
            [
                'id' => 'lime-punch', 'name' => 'Lime Punch', 'tagline' => 'Zesty green energy',
                'design' => [
                    'dot_style' => 'chevron-up', 'corner_square_style' => 'cut-tl', 'corner_dot_style' => 'chevron-up',
                    'fg_color' => '#4d7c0f', 'bg_color' => '#f7fee7', 'transparent_bg' => false,
                    'corner_square_color' => '#65a30d', 'corner_dot_color' => '#4d7c0f',
                    'gradient' => $g('linear', '#65a30d', '#a3e635', 90),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $off,
                    'frame' => $frame('arrow-down', '#4d7c0f', '#f7fee7', 'Anton'),
                ],
            ],
            [
                'id' => 'noir', 'name' => 'Noir', 'tagline' => 'Pure minimal monochrome dots',
                'design' => [
                    'dot_style' => 'dot', 'corner_square_style' => 'dot', 'corner_dot_style' => 'dot',
                    'fg_color' => '#18181b', 'bg_color' => '#fafafa', 'transparent_bg' => false,
                    'corner_square_color' => '#18181b', 'corner_dot_color' => '#18181b',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('minimal-line', '#fafafa', '#18181b', 'Fira Sans'),
                ],
            ],
            [
                'id' => 'candy-shop', 'name' => 'Candy Shop', 'tagline' => 'Sweet multi-color hearts',
                'design' => [
                    'dot_style' => 'heart', 'corner_square_style' => 'heart-frame', 'corner_dot_style' => 'heart',
                    'fg_color' => '#e11d48', 'bg_color' => '#fff0f3', 'transparent_bg' => false,
                    'corner_square_color' => '#e11d48', 'corner_dot_color' => '#fb7185',
                    'gradient' => $g('linear', '#e11d48', '#fb7185', 45),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('scan-me-rounded', '#e11d48', '#ffffff', 'Lobster'),
                ],
            ],
            [
                'id' => 'blueprint', 'name' => 'Blueprint', 'tagline' => 'Technical cyan on navy',
                'design' => [
                    'dot_style' => 'plus', 'corner_square_style' => 'plus-frame', 'corner_dot_style' => 'plus',
                    'fg_color' => '#7dd3fc', 'bg_color' => '#0c2340', 'transparent_bg' => false,
                    'corner_square_color' => '#38bdf8', 'corner_dot_color' => '#7dd3fc',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('linear', '#0c2340', '#143a5e', 180),
                    'frame' => $frame('double-border', '#0c2340', '#7dd3fc', 'Oswald'),
                ],
            ],
            [
                'id' => 'sakura', 'name' => 'Sakura', 'tagline' => 'Cherry-blossom flowers',
                'design' => [
                    'dot_style' => 'flower', 'corner_square_style' => 'flower', 'corner_dot_style' => 'flower',
                    'fg_color' => '#be185d', 'bg_color' => '#fff5f7', 'transparent_bg' => false,
                    'corner_square_color' => '#ec4899', 'corner_dot_color' => '#be185d',
                    'gradient' => $g('linear', '#be185d', '#f9a8d4', 60),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('radial', '#fff5f7', '#fde4ec', 0),
                    'frame' => $frame('cloud', '#f9a8d4', '#be185d', 'Dancing Script'),
                ],
            ],
            [
                'id' => 'graphite', 'name' => 'Graphite', 'tagline' => 'Sleek grey soft-squares',
                'design' => [
                    'dot_style' => 'rounded', 'corner_square_style' => 'soft-square', 'corner_dot_style' => 'rounded',
                    'fg_color' => '#334155', 'bg_color' => '#f1f5f9', 'transparent_bg' => false,
                    'corner_square_color' => '#475569', 'corner_dot_color' => '#334155',
                    'gradient' => $g('linear', '#334155', '#64748b', 135),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off,
                    'bg_gradient' => $off,
                    'frame' => $frame('shadow-soft', '#334155', '#f1f5f9', 'Inter'),
                ],
            ],
            [
                'id' => 'festival', 'name' => 'Festival', 'tagline' => 'Bright triangles, ticket',
                'design' => [
                    'dot_style' => 'triangle-up', 'corner_square_style' => 'triangle', 'corner_dot_style' => 'triangle-up',
                    'fg_color' => '#7c3aed', 'bg_color' => '#fefce8', 'transparent_bg' => false,
                    'corner_square_color' => '#f59e0b', 'corner_dot_color' => '#7c3aed',
                    'gradient' => $g('linear', '#7c3aed', '#f59e0b', 120),
                    'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('ticket-perforated', '#7c3aed', '#fefce8', 'Bebas Neue'),
                ],
            ],
            [
                'id' => 'emerald-card', 'name' => 'Emerald Card', 'tagline' => 'Rich green business card',
                'design' => [
                    'dot_style' => 'square', 'corner_square_style' => 'beveled-square', 'corner_dot_style' => 'square',
                    'fg_color' => '#065f46', 'bg_color' => '#ffffff', 'transparent_bg' => false,
                    'corner_square_color' => '#047857', 'corner_dot_color' => '#065f46',
                    'gradient' => $off, 'eye_outer_gradient' => $off, 'eye_inner_gradient' => $off, 'bg_gradient' => $off,
                    'frame' => $frame('scan-me-classic', '#065f46', '#ffffff', 'Montserrat'),
                ],
            ],
            [
                'id' => 'cosmic', 'name' => 'Cosmic', 'tagline' => 'Starry sparkle galaxy',
                'design' => [
                    'dot_style' => 'sparkle', 'corner_square_style' => 'sparkle-frame', 'corner_dot_style' => 'star4',
                    'fg_color' => '#c4b5fd', 'bg_color' => '#0b0a1f', 'transparent_bg' => false,
                    'corner_square_color' => '#a78bfa', 'corner_dot_color' => '#f0abfc',
                    'gradient' => $g('linear', '#a78bfa', '#f0abfc', 135),
                    'eye_outer_gradient' => $g('linear', '#7c3aed', '#22d3ee', 90), 'eye_inner_gradient' => $off,
                    'bg_gradient' => $g('radial', '#1e1b4b', '#0b0a1f', 0),
                    'frame' => $frame('starburst', '#0b0a1f', '#c4b5fd', 'Anton'),
                ],
            ],
        ];
    }
}
