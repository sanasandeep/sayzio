<?php

namespace App\Modules\User\Services;

/**
 * Builds the tiny visual blueprint shown as a thumbnail in the template
 * gallery / page-picker when a template has no static thumbnail_url.
 *
 * The blueprint is a list of rows of cells laid out on the same 12-col
 * grid the real renderer uses, so column counts, image position and
 * button style are recognisable at thumbnail size without invoking the
 * full block renderer. Used by both the Card Templates gallery
 * (children of a single card) and the Page Templates picker (top-level
 * blocks of a whole page) so they share one source of truth.
 *
 * Each cell carries a `shape` hint the renderer uses to draw a
 * recognisable mock of that block type (avatar circle, pill button,
 * stacked input lines, etc.) instead of a single flat coloured bar.
 * Renderers exist in three places that all read these same hints:
 *   - Card Templates gallery   (Alpine, biolink-editor.blade.php)
 *   - Page Templates picker    (Blade,  templates/picker.blade.php)
 *   - Mobile picker            (React Native, app/links/[id]/blocks/index.tsx)
 */
class TemplatePreviewLayoutBuilder
{
    /**
     * Group an ordered list of block-shaped items into rows of cells
     * that fit on a 12-col grid. Each item must have a `type` and may
     * carry a `settings._style.grid_span` (1..12, default 12). Items
     * without a `type` are skipped. Capped at 6 rows so the preview
     * never overflows the gallery card.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function build(array $items): array
    {
        $rows = [];
        $current = [];
        $used = 0;
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $type = (string) ($item['type'] ?? '');
            if ($type === '') continue;
            $settings = is_array($item['settings'] ?? null) ? $item['settings'] : [];
            $span = (int) ($settings['_style']['grid_span'] ?? 12);
            $span = max(1, min(12, $span));
            $cell = $this->cellFor($type) + ['span' => $span];
            // Wrap to a new row when the current row can't fit this cell.
            if ($used + $span > 12 && $current) {
                $rows[] = $current;
                $current = [];
                $used = 0;
                if (count($rows) >= 6) break;
            }
            $current[] = $cell;
            $used += $span;
            if ($used >= 12) {
                $rows[] = $current;
                $current = [];
                $used = 0;
                if (count($rows) >= 6) break;
            }
        }
        if ($current && count($rows) < 6) {
            $rows[] = $current;
        }
        return $rows;
    }

    /**
     * Visual hints for a single block type in the mini preview.
     * Each cell carries:
     *   - shape: one of 'tile' (default coloured rect with icon),
     *            'heading' (solid bar, optionally with shorter sub line),
     *            'pill' (rounded full-radius button shape),
     *            'avatar' (circle on left + two stacked lines on right),
     *            'media' (gradient block with media glyph, taller),
     *            'dot_row' (row of small circular icon dots),
     *            'text_lines' (stack of thin paragraph lines),
     *            'form' (stacked input lines + a button at the bottom),
     *            'list_rows' (stack of dot + line rows),
     *            'hairline' (1-2px line),
     *            'spacer' (transparent gap),
     *            'badge' (short narrow pill).
     *   - bg / h / icon: shared visual hints (background, height in px,
     *     optional FA icon glyph).
     *   - lines / sub / dots: shape-specific extras (count of lines/dots,
     *     whether the heading has a shorter second line).
     * Unknown types fall back to a neutral 'tile' so the preview never
     * crashes on a future block type.
     *
     * @return array<string, mixed>
     */
    public function cellFor(string $type): array
    {
        static $palette = [
            // Headings — solid bar; h2/h1 templates use the same hint, the
            // gallery isn't precise enough to differentiate sizes.
            'heading'         => ['shape' => 'heading',    'bg' => 'rgba(167,139,250,0.75)', 'h' => 12, 'icon' => '', 'sub' => true],
            'heading_logo'    => ['shape' => 'heading',    'bg' => 'rgba(167,139,250,0.75)', 'h' => 12, 'icon' => '', 'sub' => false],
            'verified_heading'=> ['shape' => 'heading',    'bg' => 'rgba(167,139,250,0.75)', 'h' => 12, 'icon' => '', 'sub' => false],

            // Body text — N stacked thin lines.
            'paragraph'       => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 16, 'icon' => '', 'lines' => 2],
            'paragraph_rich'  => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 22, 'icon' => '', 'lines' => 3],
            'markdown'        => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 22, 'icon' => '', 'lines' => 3],
            'ticker'          => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 10, 'icon' => '', 'lines' => 1],

            // Buttons / links — rounded pill with arrow glyph at the right.
            'link'            => ['shape' => 'pill',       'bg' => 'rgba(139,92,246,0.55)',  'h' => 12, 'icon' => 'fa-arrow-right'],
            'link_big'        => ['shape' => 'pill',       'bg' => 'rgba(139,92,246,0.85)',  'h' => 16, 'icon' => 'fa-arrow-right'],
            'cta_button'      => ['shape' => 'pill',       'bg' => 'rgba(139,92,246,0.85)',  'h' => 16, 'icon' => 'fa-arrow-right'],
            'whatsapp_widget' => ['shape' => 'pill',       'bg' => 'rgba(34,197,94,0.85)',   'h' => 14, 'icon' => 'fa-comment'],
            'whatsapp_item'   => ['shape' => 'pill',       'bg' => 'rgba(34,197,94,0.85)',   'h' => 14, 'icon' => 'fa-comment'],
            'buy_me_coffee'   => ['shape' => 'pill',       'bg' => 'rgba(245,158,11,0.85)',  'h' => 14, 'icon' => 'fa-coffee'],
            'patreon'         => ['shape' => 'pill',       'bg' => 'rgba(245,72,55,0.85)',   'h' => 14, 'icon' => 'fa-hand-holding-usd'],
            'ko_fi'           => ['shape' => 'pill',       'bg' => 'rgba(244,114,182,0.85)', 'h' => 14, 'icon' => 'fa-mug-hot'],
            'paypal'          => ['shape' => 'pill',       'bg' => 'rgba(59,130,246,0.85)',  'h' => 14, 'icon' => 'fa-credit-card'],
            'donation'        => ['shape' => 'pill',       'bg' => 'rgba(244,63,94,0.85)',   'h' => 14, 'icon' => 'fa-heart'],

            // Media — gradient block with a centred media glyph, taller
            // than other cells so the "image area" is unmistakable.
            'image'           => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-image'],
            'image_grid'      => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-th'],
            'image_slider'    => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-images'],
            'image_slider_v2' => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-images'],
            'video'           => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,63,94,0.40), rgba(139,92,246,0.40))',  'h' => 28, 'icon' => 'fa-play'],
            'header_video'    => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,63,94,0.40), rgba(139,92,246,0.40))',  'h' => 28, 'icon' => 'fa-play'],
            'audio'           => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,114,182,0.40), rgba(139,92,246,0.40))','h' => 22, 'icon' => 'fa-music'],
            'pdf_document'    => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,63,94,0.30), rgba(244,114,182,0.30))', 'h' => 26, 'icon' => 'fa-file-pdf'],

            // Hairlines / gaps.
            'divider'         => ['shape' => 'hairline',   'bg' => 'rgba(255,255,255,0.45)', 'h' => 2,  'icon' => ''],
            'spacer'          => ['shape' => 'spacer',     'bg' => 'transparent',            'h' => 6,  'icon' => ''],

            // Socials — row of tiny circular icon dots.
            'socials'         => ['shape' => 'dot_row',    'bg' => 'rgba(255,255,255,0.85)', 'h' => 14, 'icon' => '', 'dots' => 5],
            'socials_multi'   => ['shape' => 'dot_row',    'bg' => 'rgba(255,255,255,0.85)', 'h' => 14, 'icon' => '', 'dots' => 5],
            'socials_custom'  => ['shape' => 'dot_row',    'bg' => 'rgba(255,255,255,0.85)', 'h' => 14, 'icon' => '', 'dots' => 5],

            // Small chips.
            'badge'           => ['shape' => 'badge',      'bg' => 'rgba(245,158,11,0.85)',  'h' => 8,  'icon' => ''],
            'alert'           => ['shape' => 'tile',       'bg' => 'rgba(245,158,11,0.30)',  'h' => 12, 'icon' => 'fa-circle-info'],

            // Forms — stacked input-line shapes with a button below.
            'email_subscribe' => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)'],
            'email_collector' => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)'],
            'phone_collector' => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)'],
            'contact_form'    => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 38, 'icon' => '', 'lines' => 3, 'btn_bg' => 'rgba(139,92,246,0.85)'],
            'form'            => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 38, 'icon' => '', 'lines' => 3, 'btn_bg' => 'rgba(139,92,246,0.85)'],
            'typeform'        => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 38, 'icon' => '', 'lines' => 3, 'btn_bg' => 'rgba(139,92,246,0.85)'],
            'direct_message'  => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)'],

            // Profiles — circle on the left, name + sub on the right.
            'profile_card_v1' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user'],
            'profile_card_v2' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user'],
            'profile_card_v3' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user'],
            'profile_card_v4' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user'],
            'avatar'          => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 22, 'icon' => 'fa-user'],
            'verified_avatar' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 22, 'icon' => 'fa-user-check'],

            // Lists — stack of dot + line rows.
            'list'            => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.30)', 'h' => 24, 'icon' => '', 'lines' => 3],
            'list_numbered'   => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.30)', 'h' => 24, 'icon' => '', 'lines' => 3],
            'list_pricing'    => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.30)', 'h' => 28, 'icon' => '', 'lines' => 3],

            // Misc tile-shaped blocks.
            'social_proof'    => ['shape' => 'tile',       'bg' => 'rgba(255,255,255,0.10)', 'h' => 18, 'icon' => 'fa-quote-left'],
            'ai_companion'    => ['shape' => 'tile',       'bg' => 'rgba(139,92,246,0.30)',  'h' => 22, 'icon' => 'fa-robot'],
            'countdown'       => ['shape' => 'tile',       'bg' => 'rgba(245,158,11,0.30)',  'h' => 18, 'icon' => 'fa-clock'],
            'progress'        => ['shape' => 'tile',       'bg' => 'rgba(139,92,246,0.30)',  'h' => 8,  'icon' => ''],
            'qr_code'         => ['shape' => 'tile',       'bg' => 'rgba(255,255,255,0.20)', 'h' => 28, 'icon' => 'fa-qrcode'],
            'map'             => ['shape' => 'tile',       'bg' => 'linear-gradient(135deg, rgba(34,197,94,0.30), rgba(56,189,248,0.30))', 'h' => 26, 'icon' => 'fa-map-marker-alt'],
            'map_location'    => ['shape' => 'tile',       'bg' => 'linear-gradient(135deg, rgba(34,197,94,0.30), rgba(56,189,248,0.30))', 'h' => 26, 'icon' => 'fa-map-pin'],

            // Page-template-only: a "card" container at the page level
            // collapses to a single padded cell in the blueprint (we
            // intentionally don't recurse into its children to keep the
            // page-level overview readable at thumbnail size).
            'card'            => ['shape' => 'tile',       'bg' => 'rgba(255,255,255,0.06)', 'h' => 32, 'icon' => 'fa-square'],
        ];
        return $palette[$type] ?? ['shape' => 'tile', 'bg' => 'rgba(255,255,255,0.10)', 'h' => 12, 'icon' => 'fa-cube'];
    }
}
