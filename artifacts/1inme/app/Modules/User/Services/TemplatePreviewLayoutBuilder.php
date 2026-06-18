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
     * without a `type` are skipped. Capped at `$maxRows` rows (default 6)
     * so the preview never overflows; the web card gallery passes a higher
     * cap so taller cards read as taller, while the page picker and mobile
     * (which render into fixed-height strips) keep the tighter default.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  int  $maxRows  Maximum rows to emit (clamped to >= 1).
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function build(array $items, int $maxRows = 6): array
    {
        $maxRows = max(1, $maxRows);
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
                if (count($rows) >= $maxRows) break;
            }
            $current[] = $cell;
            $used += $span;
            if ($used >= 12) {
                $rows[] = $current;
                $current = [];
                $used = 0;
                if (count($rows) >= $maxRows) break;
            }
        }
        if ($current && count($rows) < $maxRows) {
            $rows[] = $current;
        }
        return $rows;
    }

    /**
     * Visual hints for a single block type in the mini preview.
     * Each cell carries:
     *   - shape: one of 'tile' (default coloured rect with icon),
     *            'heading' (sample heading text, optionally with a sub line),
     *            'pill' (rounded full-radius button with a label),
     *            'avatar' (circular placeholder image + name lines on right),
     *            'media' (placeholder image, taller, with optional play glyph),
     *            'dot_row' (row of small circular icon dots),
     *            'text_lines' (sample paragraph text, clamped to N lines),
     *            'form' (input lines + a labelled button),
     *            'list_rows' (stack of dot + sample-text rows),
     *            'hairline' (1-2px line),
     *            'spacer' (transparent gap),
     *            'badge' (short narrow pill).
     *   - bg / h / icon: shared visual hints (background, height in px,
     *     optional FA icon glyph used as a fallback when no text/image).
     *   - lines / sub / dots: shape-specific extras (count of lines/dots,
     *     whether the heading has a shorter second line).
     *   - text / sub_text: real placeholder copy for text-like shapes
     *     (heading title, paragraph body, pill/button label, form button).
     *   - items: short sample strings for list rows.
     *   - img: absolute URL of a real placeholder image (from
     *     public/block-placeholders/) for media/avatar shapes. The
     *     renderers draw an <img> when present and fall back to the
     *     coloured skeleton + icon otherwise.
     * Unknown types fall back to a neutral 'tile' so the preview never
     * crashes on a future block type.
     *
     * @return array<string, mixed>
     */
    public function cellFor(string $type): array
    {
        $img = static function (string $file): string {
            return asset('block-placeholders/' . $file);
        };

        $palette = [
            // Headings — sample title text; h2/h1 templates share the hint,
            // the gallery isn't precise enough to differentiate sizes.
            'heading'         => ['shape' => 'heading',    'bg' => 'rgba(167,139,250,0.75)', 'h' => 12, 'icon' => '', 'sub' => true,  'text' => 'Your Headline', 'sub_text' => 'A short supporting line'],
            'heading_logo'    => ['shape' => 'heading',    'bg' => 'rgba(167,139,250,0.75)', 'h' => 12, 'icon' => '', 'sub' => false, 'text' => 'Brand Name'],
            'verified_heading'=> ['shape' => 'heading',    'bg' => 'rgba(167,139,250,0.75)', 'h' => 12, 'icon' => '', 'sub' => false, 'text' => 'Verified Name'],

            // Body text — real sample copy clamped to N lines.
            'paragraph'       => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 16, 'icon' => '', 'lines' => 2, 'text' => 'A short intro about you and what you share here.'],
            'paragraph_rich'  => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 22, 'icon' => '', 'lines' => 3, 'text' => 'Tell your story with rich, formatted text and a few lines of detail.'],
            'markdown'        => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 22, 'icon' => '', 'lines' => 3, 'text' => 'Write anything in markdown — lists, links and more.'],
            'ticker'          => ['shape' => 'text_lines', 'bg' => 'rgba(255,255,255,0.30)', 'h' => 10, 'icon' => '', 'lines' => 1, 'text' => 'Latest news and updates'],

            // Buttons / links — rounded pill with a real label.
            'link'            => ['shape' => 'pill',       'bg' => 'rgba(139,92,246,0.55)',  'h' => 12, 'icon' => 'fa-arrow-right',        'text' => 'Visit my website'],
            'link_big'        => ['shape' => 'pill',       'bg' => 'rgba(139,92,246,0.85)',  'h' => 16, 'icon' => 'fa-arrow-right',        'text' => 'Check this out'],
            'cta_button'      => ['shape' => 'pill',       'bg' => 'rgba(139,92,246,0.85)',  'h' => 16, 'icon' => 'fa-arrow-right',        'text' => 'Get started'],
            'whatsapp_widget' => ['shape' => 'pill',       'bg' => 'rgba(34,197,94,0.85)',   'h' => 14, 'icon' => 'fa-comment',            'text' => 'Chat on WhatsApp'],
            'whatsapp_item'   => ['shape' => 'pill',       'bg' => 'rgba(34,197,94,0.85)',   'h' => 14, 'icon' => 'fa-comment',            'text' => 'Message me'],
            'buy_me_coffee'   => ['shape' => 'pill',       'bg' => 'rgba(245,158,11,0.85)',  'h' => 14, 'icon' => 'fa-coffee',             'text' => 'Buy me a coffee'],
            'patreon'         => ['shape' => 'pill',       'bg' => 'rgba(245,72,55,0.85)',   'h' => 14, 'icon' => 'fa-hand-holding-usd',   'text' => 'Become a patron'],
            'ko_fi'           => ['shape' => 'pill',       'bg' => 'rgba(244,114,182,0.85)', 'h' => 14, 'icon' => 'fa-mug-hot',            'text' => 'Support on Ko-fi'],
            'paypal'          => ['shape' => 'pill',       'bg' => 'rgba(59,130,246,0.85)',  'h' => 14, 'icon' => 'fa-credit-card',        'text' => 'Pay with PayPal'],
            'donation'        => ['shape' => 'pill',       'bg' => 'rgba(244,63,94,0.85)',   'h' => 14, 'icon' => 'fa-heart',              'text' => 'Donate'],

            // Media — real placeholder image, taller so the "image area"
            // is unmistakable. The 'play' flag overlays a play glyph.
            'image'           => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-image',    'img' => $img('image.svg')],
            'image_grid'      => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-th',       'img' => $img('image-square.svg')],
            'image_slider'    => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-images',   'img' => $img('image.svg')],
            'image_slider_v2' => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(56,189,248,0.45), rgba(139,92,246,0.45))', 'h' => 28, 'icon' => 'fa-images',   'img' => $img('image.svg')],
            'video'           => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,63,94,0.40), rgba(139,92,246,0.40))',  'h' => 28, 'icon' => 'fa-play',     'img' => $img('cover.svg'), 'play' => true],
            'header_video'    => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,63,94,0.40), rgba(139,92,246,0.40))',  'h' => 28, 'icon' => 'fa-play',     'img' => $img('cover.svg'), 'play' => true],
            'audio'           => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,114,182,0.40), rgba(139,92,246,0.40))','h' => 22, 'icon' => 'fa-music',    'img' => $img('cover.svg'), 'play' => true],
            'pdf_document'    => ['shape' => 'media',      'bg' => 'linear-gradient(135deg, rgba(244,63,94,0.30), rgba(244,114,182,0.30))', 'h' => 26, 'icon' => 'fa-file-pdf',  'img' => $img('document.svg')],

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

            // Forms — stacked input-line shapes with a labelled button below.
            'email_subscribe' => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)', 'text' => 'Subscribe'],
            'email_collector' => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)', 'text' => 'Join the list'],
            'phone_collector' => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)', 'text' => 'Send'],
            'contact_form'    => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 38, 'icon' => '', 'lines' => 3, 'btn_bg' => 'rgba(139,92,246,0.85)', 'text' => 'Submit'],
            'form'            => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 38, 'icon' => '', 'lines' => 3, 'btn_bg' => 'rgba(139,92,246,0.85)', 'text' => 'Submit'],
            'typeform'        => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 38, 'icon' => '', 'lines' => 3, 'btn_bg' => 'rgba(139,92,246,0.85)', 'text' => 'Submit'],
            'direct_message'  => ['shape' => 'form',       'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => '', 'lines' => 1, 'btn_bg' => 'rgba(139,92,246,0.85)', 'text' => 'Send message'],

            // Profiles — circular placeholder image, name + sub on the right.
            'profile_card_v1' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user',       'img' => $img('avatar.svg'), 'text' => 'Your Name', 'sub_text' => '@yourhandle'],
            'profile_card_v2' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user',       'img' => $img('avatar.svg'), 'text' => 'Your Name', 'sub_text' => '@yourhandle'],
            'profile_card_v3' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user',       'img' => $img('avatar.svg'), 'text' => 'Your Name', 'sub_text' => '@yourhandle'],
            'profile_card_v4' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 30, 'icon' => 'fa-user',       'img' => $img('avatar.svg'), 'text' => 'Your Name', 'sub_text' => '@yourhandle'],
            'avatar'          => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 22, 'icon' => 'fa-user',       'img' => $img('avatar.svg'), 'text' => 'Your Name', 'sub_text' => '@yourhandle'],
            'verified_avatar' => ['shape' => 'avatar',     'bg' => 'rgba(167,139,250,0.85)', 'h' => 22, 'icon' => 'fa-user-check', 'img' => $img('avatar.svg'), 'text' => 'Your Name', 'sub_text' => '@yourhandle'],

            // Lists — stack of dot + sample-text rows.
            'list'            => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.30)', 'h' => 24, 'icon' => '', 'lines' => 3, 'items' => ['First item', 'Second item', 'Third item']],
            'list_numbered'   => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.30)', 'h' => 24, 'icon' => '', 'lines' => 3, 'items' => ['First step', 'Second step', 'Third step']],
            'list_pricing'    => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.30)', 'h' => 28, 'icon' => '', 'lines' => 3, 'items' => ['Starter — $9', 'Pro — $19', 'Team — $49']],

            // Q&A and engagement — expandable rows / option lists.
            'faq'             => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.20)', 'h' => 26, 'icon' => 'fa-circle-question', 'lines' => 3, 'items' => ['What do you do?', 'How can I reach you?', 'Where are you based?']],
            'quiz'            => ['shape' => 'list_rows',  'bg' => 'rgba(139,92,246,0.22)',  'h' => 26, 'icon' => 'fa-clipboard-question', 'lines' => 3, 'items' => ['Pick the right answer', 'Option A', 'Option B']],
            'poll'            => ['shape' => 'list_rows',  'bg' => 'rgba(56,189,248,0.22)',  'h' => 26, 'icon' => 'fa-square-poll-vertical', 'lines' => 3, 'items' => ['What should I cover next?', 'Tutorials', 'Q&A']],
            'timeline'        => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.18)', 'h' => 28, 'icon' => 'fa-stream', 'lines' => 3, 'items' => ['Where it started', 'A big milestone', 'What I\'m building now']],

            // Social proof — quote-style review tiles.
            'testimonials'    => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.14)', 'h' => 28, 'icon' => 'fa-quote-left', 'lines' => 3, 'items' => ['“A real delight to work with.”', '“Made a difference for us.”', '“Sharp and on time.”']],
            'review'          => ['shape' => 'tile',       'bg' => 'rgba(255,255,255,0.12)', 'h' => 22, 'icon' => 'fa-star', 'text' => '★★★★★ — A glowing review'],

            // Commerce — products, services, pricing, promos.
            'product'         => ['shape' => 'tile',       'bg' => 'linear-gradient(135deg, rgba(245,158,11,0.30), rgba(244,114,182,0.30))', 'h' => 30, 'icon' => 'fa-bag-shopping', 'text' => 'Featured product'],
            'service'         => ['shape' => 'tile',       'bg' => 'rgba(139,92,246,0.24)',  'h' => 24, 'icon' => 'fa-briefcase', 'text' => 'What I offer'],
            'price'           => ['shape' => 'list_rows',  'bg' => 'rgba(255,255,255,0.16)', 'h' => 30, 'icon' => 'fa-tags', 'lines' => 3, 'items' => ['Discovery + audit', 'Hands-on work', 'Async support']],
            'coupon'          => ['shape' => 'tile',       'bg' => 'linear-gradient(135deg, rgba(244,63,94,0.30), rgba(245,158,11,0.30))', 'h' => 20, 'icon' => 'fa-ticket', 'text' => 'WELCOME15'],
            'vcard'           => ['shape' => 'tile',       'bg' => 'rgba(56,189,248,0.22)',  'h' => 24, 'icon' => 'fa-address-card', 'text' => 'Save my contact'],

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
