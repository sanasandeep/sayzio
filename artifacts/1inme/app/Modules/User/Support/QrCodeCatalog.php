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
}
