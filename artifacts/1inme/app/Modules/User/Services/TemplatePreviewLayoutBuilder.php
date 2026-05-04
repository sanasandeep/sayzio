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
     * @return array<int, array<int, array{span:int,bg:string,h:int,icon:string}>>
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
     * Visual hints (background, height in px, icon) for a single block
     * type in the mini preview. Unknown types fall back to a neutral
     * pill so the preview never crashes on a future block type.
     *
     * @return array{bg:string,h:int,icon:string}
     */
    public function cellFor(string $type): array
    {
        static $palette = [
            'heading'         => ['bg' => 'rgba(167,139,250,0.55)', 'h' => 14, 'icon' => 'fa-heading'],
            'paragraph'       => ['bg' => 'rgba(255,255,255,0.10)', 'h' => 18, 'icon' => ''],
            'link'            => ['bg' => 'rgba(139,92,246,0.55)',  'h' => 10, 'icon' => 'fa-link'],
            'link_big'        => ['bg' => 'rgba(139,92,246,0.75)',  'h' => 16, 'icon' => 'fa-arrow-right'],
            'image'           => ['bg' => 'linear-gradient(135deg, rgba(56,189,248,0.40), rgba(139,92,246,0.40))', 'h' => 24, 'icon' => 'fa-image'],
            'video'           => ['bg' => 'linear-gradient(135deg, rgba(244,63,94,0.35), rgba(139,92,246,0.35))',  'h' => 24, 'icon' => 'fa-play'],
            'divider'         => ['bg' => 'rgba(255,255,255,0.18)', 'h' => 2,  'icon' => ''],
            'spacer'          => ['bg' => 'transparent',            'h' => 6,  'icon' => ''],
            'socials_multi'   => ['bg' => 'rgba(255,255,255,0.08)', 'h' => 10, 'icon' => 'fa-share-nodes'],
            'badge'           => ['bg' => 'rgba(245,158,11,0.45)',  'h' => 8,  'icon' => ''],
            'alert'           => ['bg' => 'rgba(245,158,11,0.30)',  'h' => 12, 'icon' => 'fa-circle-info'],
            'email_subscribe' => ['bg' => 'rgba(139,92,246,0.45)',  'h' => 16, 'icon' => 'fa-envelope'],
            'email_collector' => ['bg' => 'rgba(139,92,246,0.45)',  'h' => 16, 'icon' => 'fa-envelope'],
            'contact_form'    => ['bg' => 'rgba(255,255,255,0.06)', 'h' => 28, 'icon' => 'fa-id-card'],
            'form'            => ['bg' => 'rgba(255,255,255,0.06)', 'h' => 28, 'icon' => 'fa-list-check'],
            'profile_card_v1' => ['bg' => 'linear-gradient(135deg, rgba(167,139,250,0.45), rgba(56,189,248,0.30))', 'h' => 26, 'icon' => 'fa-user'],
            'whatsapp_widget' => ['bg' => 'rgba(34,197,94,0.45)',   'h' => 14, 'icon' => 'fa-comment'],
            'list'            => ['bg' => 'rgba(255,255,255,0.07)', 'h' => 20, 'icon' => 'fa-list'],
            'social_proof'    => ['bg' => 'rgba(255,255,255,0.06)', 'h' => 20, 'icon' => 'fa-quote-left'],
            'ai_companion'    => ['bg' => 'rgba(139,92,246,0.30)',  'h' => 22, 'icon' => 'fa-robot'],
            // Page-template-only: a "card" container at the page level
            // collapses to a single padded cell in the blueprint (we
            // intentionally don't recurse into its children to keep the
            // page-level overview readable at thumbnail size).
            'card'            => ['bg' => 'rgba(255,255,255,0.06)', 'h' => 32, 'icon' => 'fa-square'],
        ];
        return $palette[$type] ?? ['bg' => 'rgba(255,255,255,0.08)', 'h' => 12, 'icon' => 'fa-cube'];
    }
}
