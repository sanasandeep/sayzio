<?php

namespace App\Modules\User\Support;

use App\Modules\Admin\Models\BgTemplate;

/**
 * Renders a lightweight, theme-aware SVG preview card for a seeded
 * Page Template. The preview is generated straight from the template's
 * stored snapshot (biolink theme + block list), so every card in the
 * gallery reflects its actual background, button style/colors and block
 * layout — no external photo CDNs, no headless-browser screenshots.
 *
 * Served by PublicTemplateThumbController at
 * `GET /template-thumbs/{slug}.svg`; seeders point `thumbnail_url` at
 * that route (with a `?v=SEED_VERSION` cache-buster) so a blueprint
 * redesign automatically re-renders every thumbnail.
 */
class TemplateThumbnailRenderer
{
    private const W = 640;
    private const H = 800;

    /** Content column inset. */
    private const PAD = 72;

    /** @var array<string,int|null>|null slug-keyed BgTemplate css cache */
    private static ?array $bgCssCache = null;

    /**
     * @param  array<string,mixed>  $snapshot  Template snapshot ({biolink, blocks, meta}).
     */
    public static function render(array $snapshot, string $slug = ''): string
    {
        $theme  = is_array($snapshot['biolink'] ?? null) ? $snapshot['biolink'] : [];
        $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];

        $font   = self::color($theme['font_color'] ?? null, '#ffffff');
        $accent = self::color($theme['theme_color'] ?? null, '#3d6bff');
        $btnBg  = self::color($theme['button_color'] ?? null, $accent);
        $btnFg  = self::color($theme['button_text_color'] ?? null, '#ffffff');
        $shape  = (string) ($theme['button_style'] ?? 'rounded');

        $light = self::isLight($font) === false; // light font ⇒ dark page and vice versa
        // Muted overlay tint derived from the font color so bars stay legible
        // on both light and dark themes.
        $ink    = $font;
        $inkDim = self::rgba($font, 0.35);
        $card   = self::rgba($font, $light ? 0.10 : 0.08);

        [$bgDefs, $bgFill] = self::background($theme, $accent);

        $svg = [];
        $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::W . '" height="' . self::H . '" viewBox="0 0 ' . self::W . ' ' . self::H . '" role="img">';
        $svg[] = '<defs>' . $bgDefs
            . '<clipPath id="rr"><rect x="0" y="0" width="' . self::W . '" height="' . self::H . '" rx="28"/></clipPath>'
            . '</defs>';
        $svg[] = '<g clip-path="url(#rr)">';
        $svg[] = '<rect x="0" y="0" width="' . self::W . '" height="' . self::H . '" fill="' . $bgFill . '"/>';
        // Soft accent glow so even flat solids feel designed. The glow
        // geometry is seeded from the slug so two templates that happen to
        // share the exact same theme still get visually distinct cards.
        $seed = $slug === '' ? 0 : crc32($slug);
        $gx = self::W - 40 - ($seed % 120);            // 520..640 → top-right band
        $gy = 20 + (intdiv($seed, 7) % 90);            // 20..110
        $gr = 190 + ($seed % 70);                      // 190..260
        $g2 = 150 + (intdiv($seed, 11) % 70);          // 150..220
        $g2x = 10 + (intdiv($seed, 13) % 90);          // 10..100 → bottom-left band
        $svg[] = '<circle cx="' . $gx . '" cy="' . $gy . '" r="' . $gr . '" fill="' . self::rgba($accent, 0.18) . '"/>';
        $svg[] = '<circle cx="' . $g2x . '" cy="' . (self::H - 40) . '" r="' . $g2 . '" fill="' . self::rgba($accent, 0.10) . '"/>';
        if ($seed % 3 === 0) {
            // Every third slug gets an extra mid-page ribbon for variety.
            $svg[] = '<circle cx="' . (($seed % 2 === 0) ? 40 : self::W - 40) . '" cy="' . (self::H / 2) . '" r="' . (90 + ($seed % 50)) . '" fill="' . self::rgba($accent, 0.07) . '"/>';
        }

        $y = 64;
        $count = 0;
        foreach ($blocks as $block) {
            if ($y > self::H - 80 || $count >= 9) {
                break;
            }
            $type = (string) ($block['type'] ?? '');
            $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
            $row = self::row($type, $settings, $y, $ink, $inkDim, $card, $accent, $btnBg, $btnFg, $shape);
            if ($row === null) {
                continue;
            }
            [$markup, $height] = $row;
            $svg[] = $markup;
            $y += $height;
            $count++;
        }

        $svg[] = '</g>';
        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /* ─────────────────────────── background ─────────────────────────── */

    /**
     * @return array{0:string,1:string} [defs markup, fill ref/value]
     */
    private static function background(array $theme, string $accent): array
    {
        $type = (string) ($theme['background_type'] ?? '');

        if ($type === 'color') {
            return ['', self::color($theme['background_color'] ?? null, '#0f172a')];
        }

        $stops = [];
        $angle = 135;

        if ($type === 'gradient') {
            $css = (string) ($theme['background_gradient'] ?? '');
            $stops = self::hexes($css);
            if (preg_match('/(\d+)\s*deg/', $css, $m)) {
                $angle = (int) $m[1];
            }
        } elseif ($type === 'template') {
            // Animated bg-template: approximate the look from the stored CSS
            // (first few color stops) or its preview color.
            $stops = self::bgTemplateStops((int) ($theme['bg_template_id'] ?? 0));
        } elseif ($type === 'image') {
            // Photo background: neutral dark gradient tinted by the accent.
            $stops = ['#111827', self::mix('#111827', $accent, 0.35)];
            $angle = 160;
        }

        if (count($stops) < 2) {
            $base = $stops[0] ?? self::mix('#0f172a', $accent, 0.25);
            $stops = [self::mix($base, '#000000', 0.25), $base, self::mix($base, $accent, 0.4)];
        }
        $stops = array_slice($stops, 0, 4);

        // Map the CSS angle onto an SVG gradient vector (good enough for a thumb).
        $rad = deg2rad(($angle - 90 + 360) % 360);
        $x2 = 0.5 + cos($rad) / 2;
        $y2 = 0.5 + sin($rad) / 2;
        $x1 = 1 - $x2;
        $y1 = 1 - $y2;

        $defs = '<linearGradient id="bg" x1="' . round($x1, 3) . '" y1="' . round($y1, 3)
            . '" x2="' . round($x2, 3) . '" y2="' . round($y2, 3) . '">';
        $n = count($stops) - 1;
        foreach ($stops as $i => $hex) {
            $defs .= '<stop offset="' . round($n > 0 ? $i / $n * 100 : 0) . '%" stop-color="' . $hex . '"/>';
        }
        $defs .= '</linearGradient>';

        return [$defs, 'url(#bg)'];
    }

    /** @return array<int,string> */
    private static function bgTemplateStops(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        try {
            $row = BgTemplate::query()->find($id, ['id', 'preview_color', 'css']);
        } catch (\Throwable) {
            return [];
        }
        if (!$row) {
            return [];
        }
        $stops = self::hexes((string) ($row->css ?? ''));
        if (count($stops) >= 2) {
            return array_slice($stops, 0, 3);
        }
        $preview = self::color($row->preview_color ?? null, '');
        return $preview !== '' ? [$preview] : [];
    }

    /* ─────────────────────────── block rows ─────────────────────────── */

    /**
     * Render one block's skeleton row starting at $y.
     *
     * @return array{0:string,1:int}|null [markup, consumed height] or null to skip.
     */
    private static function row(
        string $type,
        array $settings,
        int $y,
        string $ink,
        string $inkDim,
        string $card,
        string $accent,
        string $btnBg,
        string $btnFg,
        string $shape
    ): ?array {
        $x = self::PAD;
        $w = self::W - self::PAD * 2;
        $cx = self::W / 2;

        // Profile header: optional cover band + avatar + name/bio bars.
        if (str_starts_with($type, 'profile')) {
            $hasCover = trim((string) ($settings['cover'] ?? $settings['cover_url'] ?? '')) !== '';
            $out = '';
            $top = $y;
            if ($hasCover) {
                $out .= '<rect x="24" y="' . $top . '" width="' . (self::W - 48) . '" height="90" rx="16" fill="' . self::rgba($accent, 0.30) . '"/>';
                $top += 56;
            }
            $out .= '<circle cx="' . $cx . '" cy="' . ($top + 44) . '" r="44" fill="' . self::rgba($ink, 0.85) . '" stroke="' . $accent . '" stroke-width="5"/>';
            $out .= '<rect x="' . ($cx - 90) . '" y="' . ($top + 104) . '" width="180" height="18" rx="9" fill="' . $ink . '"/>';
            $out .= '<rect x="' . ($cx - 140) . '" y="' . ($top + 134) . '" width="280" height="10" rx="5" fill="' . $inkDim . '"/>';
            $out .= '<rect x="' . ($cx - 110) . '" y="' . ($top + 152) . '" width="220" height="10" rx="5" fill="' . $inkDim . '"/>';
            return [$out, ($top - $y) + 186];
        }

        switch (true) {
            // Social icon row.
            case str_starts_with($type, 'socials') || $type === 'social':
                $out = '';
                for ($i = 0; $i < 5; $i++) {
                    $out .= '<circle cx="' . ($cx - 80 + $i * 40) . '" cy="' . ($y + 16) . '" r="14" fill="' . self::rgba($ink, 0.25) . '"/>';
                }
                return [$out, 52];

            // Buttons.
            case in_array($type, ['link', 'link_big', 'cta_button', 'whatsapp_widget', 'donation', 'one_time_offer', 'product', 'price', 'service'], true):
                $h = in_array($type, ['link_big', 'cta_button', 'one_time_offer'], true) ? 58 : 48;
                $rx = self::radius($shape, $h);
                $fill = $btnBg;
                $extra = '';
                if ($shape === 'outline') {
                    $fill = 'none';
                    $extra = ' stroke="' . $btnBg . '" stroke-width="3"';
                }
                $label = $shape === 'outline' ? $btnBg : $btnFg;
                $out = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . $rx . '" fill="' . $fill . '"' . $extra . '/>';
                $out .= '<rect x="' . ($cx - 80) . '" y="' . ($y + $h / 2 - 6) . '" width="160" height="12" rx="6" fill="' . $label . '"/>';
                return [$out, $h + 16];

            // Section heading.
            case $type === 'heading':
                return ['<rect x="' . ($cx - 70) . '" y="' . $y . '" width="140" height="14" rx="7" fill="' . $ink . '"/>', 38];

            // Paragraph / text.
            case in_array($type, ['paragraph', 'text', 'list', 'alert', 'ticker', 'badge'], true):
                $out = '<rect x="' . ($cx - ($type === 'badge' ? 60 : 150)) . '" y="' . $y . '" width="' . ($type === 'badge' ? 120 : 300) . '" height="10" rx="5" fill="' . ($type === 'badge' ? self::rgba($accent, 0.8) : $inkDim) . '"/>';
                return [$out, 30];

            case $type === 'divider':
                return ['<rect x="' . ($x + 60) . '" y="' . ($y + 4) . '" width="' . ($w - 120) . '" height="3" rx="1.5" fill="' . self::rgba($ink, 0.2) . '"/>', 24];

            // Imagery: grid/slider = 3 tiles, single image/video = one wide tile.
            case in_array($type, ['image_grid', 'image_slider', 'gallery'], true):
                $tw = (int) (($w - 24) / 3);
                $out = '';
                for ($i = 0; $i < 3; $i++) {
                    $tx = $x + $i * ($tw + 12);
                    $out .= '<rect x="' . $tx . '" y="' . $y . '" width="' . $tw . '" height="' . $tw . '" rx="12" fill="' . self::rgba($accent, 0.28) . '"/>'
                        . '<circle cx="' . ($tx + $tw / 2) . '" cy="' . ($y + $tw / 2) . '" r="14" fill="' . self::rgba($ink, 0.35) . '"/>';
                }
                return [$out, $tw + 18];

            case in_array($type, ['image', 'video', 'audio', 'pdf', 'map'], true):
                $h = 110;
                $out = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="14" fill="' . self::rgba($accent, 0.28) . '"/>';
                $out .= $type === 'video'
                    ? '<path d="M ' . ($cx - 12) . ' ' . ($y + $h / 2 - 16) . ' l 28 16 l -28 16 z" fill="' . self::rgba($ink, 0.6) . '"/>'
                    : '<circle cx="' . $cx . '" cy="' . ($y + $h / 2) . '" r="16" fill="' . self::rgba($ink, 0.35) . '"/>';
                return [$out, $h + 16];

            // Countdown: 4 digit tiles.
            case $type === 'countdown':
                $out = '';
                for ($i = 0; $i < 4; $i++) {
                    $out .= '<rect x="' . ($cx - 132 + $i * 68) . '" y="' . $y . '" width="56" height="52" rx="10" fill="' . $card . '"/>'
                        . '<rect x="' . ($cx - 132 + $i * 68 + 14) . '" y="' . ($y + 16) . '" width="28" height="16" rx="4" fill="' . $ink . '"/>';
                }
                return [$out, 70];

            // Email capture / forms: input + button.
            case in_array($type, ['email_collector', 'contact_form', 'form', 'subscribe'], true):
                $bw = 130;
                $rx = self::radius($shape, 44);
                $out = '<rect x="' . $x . '" y="' . $y . '" width="' . ($w - $bw - 12) . '" height="44" rx="' . $rx . '" fill="' . $card . '" stroke="' . self::rgba($ink, 0.25) . '" stroke-width="2"/>';
                $out .= '<rect x="' . ($x + 18) . '" y="' . ($y + 17) . '" width="120" height="10" rx="5" fill="' . $inkDim . '"/>';
                $out .= '<rect x="' . ($x + $w - $bw) . '" y="' . $y . '" width="' . $bw . '" height="44" rx="' . $rx . '" fill="' . $btnBg . '"/>';
                return [$out, 62];

            // Card-style content blocks.
            case in_array($type, ['testimonials', 'testimonial_carousel', 'review', 'faq', 'faq_v2', 'timeline', 'tabs', 'stats', 'list_pricing', 'progress', 'poll', 'quiz', 'vcard', 'card', 'coupon', 'reviews_wall'], true):
                $h = 84;
                $out = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="14" fill="' . $card . '"/>';
                $out .= '<circle cx="' . ($x + 34) . '" cy="' . ($y + 30) . '" r="14" fill="' . self::rgba($accent, 0.6) . '"/>';
                $out .= '<rect x="' . ($x + 60) . '" y="' . ($y + 20) . '" width="' . ($w - 120) . '" height="10" rx="5" fill="' . $ink . '"/>';
                $out .= '<rect x="' . ($x + 60) . '" y="' . ($y + 40) . '" width="' . ($w - 180) . '" height="8" rx="4" fill="' . $inkDim . '"/>';
                $out .= '<rect x="' . ($x + 60) . '" y="' . ($y + 56) . '" width="' . ($w - 240) . '" height="8" rx="4" fill="' . $inkDim . '"/>';
                return [$out, $h + 16];

            // QR block.
            case $type === 'qr_code':
                $s = 84;
                $qx = $cx - $s / 2;
                $out = '<rect x="' . $qx . '" y="' . $y . '" width="' . $s . '" height="' . $s . '" rx="10" fill="' . self::rgba($ink, 0.9) . '"/>';
                foreach ([[10, 10], [56, 10], [10, 56]] as [$dx, $dy]) {
                    $out .= '<rect x="' . ($qx + $dx) . '" y="' . ($y + $dy) . '" width="18" height="18" rx="3" fill="' . ($ink === '#ffffff' ? '#111827' : '#ffffff') . '"/>';
                }
                return [$out, $s + 16];

            default:
                // Unknown/rare block: subtle generic bar so the layout rhythm holds.
                return ['<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="34" rx="10" fill="' . $card . '"/>', 50];
        }
    }

    /* ─────────────────────────── color utils ─────────────────────────── */

    private static function radius(string $shape, int $h): int
    {
        return match ($shape) {
            'pill' => (int) ($h / 2),
            'square' => 4,
            default => 12,
        };
    }

    /** Validate a hex color, else fall back. */
    private static function color(mixed $value, string $fallback): string
    {
        $v = is_string($value) ? trim($value) : '';
        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $v) ? strtolower($v) : $fallback;
    }

    /** @return array<int,string> All 6/3-digit hex colors found in a CSS string. */
    private static function hexes(string $css): array
    {
        preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $css, $m);
        return array_values(array_unique(array_map('strtolower', $m[0])));
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function rgba(string $hex, float $a): string
    {
        [$r, $g, $b] = self::rgb($hex);
        return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $a . ')';
    }

    private static function mix(string $hexA, string $hexB, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($hexA);
        [$r2, $g2, $b2] = self::rgb($hexB);
        return sprintf(
            '#%02x%02x%02x',
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t)
        );
    }

    private static function isLight(string $hex): bool
    {
        [$r, $g, $b] = self::rgb($hex);
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150;
    }
}
