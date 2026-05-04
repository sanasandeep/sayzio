<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\BiolinkBlock;

/**
 * Builds UI-friendly "what's inside" summaries from card / page template
 * snapshots. Centralizes the friendly-label + icon + headline-preview logic
 * so the Card Templates gallery and Page Templates picker stay consistent —
 * both pull labels/icons from the single source of truth `BiolinkBlock::TYPES`.
 */
class TemplateContentSummarizer
{
    /**
     * Summarize a flat list of children belonging to a single card snapshot.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array{type:string,label:string,icon:string,preview:string}>
     */
    public function summarizeChildren(array $children): array
    {
        $out = [];
        foreach ($children as $child) {
            $entry = $this->summarizeOne($child);
            if ($entry !== null) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /**
     * Summarize a page-template snapshot's top-level blocks. Each top-level
     * block carries the same {type,label,icon,preview} fields as a card
     * child, plus a `children` array (empty when not a card or when the
     * card has no children).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array{type:string,label:string,icon:string,preview:string,children:array<int, array{type:string,label:string,icon:string,preview:string}>}>
     */
    public function summarizePageBlocks(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            $entry = $this->summarizeOne($block);
            if ($entry === null) continue;
            $entry['children'] = ($entry['type'] === 'card' && is_array($block['children'] ?? null))
                ? $this->summarizeChildren($block['children'])
                : [];
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * @param  mixed  $node
     * @return array{type:string,label:string,icon:string,preview:string}|null
     */
    private function summarizeOne($node): ?array
    {
        if (!is_array($node)) return null;
        $type = (string) ($node['type'] ?? '');
        if ($type === '') return null;
        $info = BiolinkBlock::TYPES[$type] ?? null;
        $label = is_array($info) && isset($info['label'])
            ? (string) $info['label']
            : ucwords(str_replace('_', ' ', $type));
        $icon = is_array($info) && isset($info['icon'])
            ? (string) $info['icon']
            : 'fa-cube';
        $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
        return [
            'type'    => $type,
            'label'   => $label,
            'icon'    => $icon,
            'preview' => $this->previewFromSettings($type, $settings),
        ];
    }

    /**
     * Pick the most "headline-ish" string out of a block's settings so the
     * gallery can render something like 'Heading — "Hello there"' on hover.
     * Returns '' when nothing useful is found; callers fall back to the
     * type label alone in that case.
     *
     * @param  array<string, mixed>  $settings
     */
    private function previewFromSettings(string $type, array $settings): string
    {
        $candidates = ['text', 'heading', 'title', 'name', 'label', 'button_text',
            'placeholder', 'message', 'phone', 'url'];
        foreach ($candidates as $key) {
            $v = $settings[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $clean = trim(preg_replace('/\s+/', ' ', strip_tags($v)) ?? '');
                if ($clean === '') continue;
                return mb_strimwidth($clean, 0, 60, '…');
            }
        }
        if (isset($settings['items']) && is_array($settings['items'])) {
            $first = $settings['items'][0] ?? null;
            if (is_string($first) && trim($first) !== '') {
                return mb_strimwidth(trim($first), 0, 60, '…');
            }
            if (is_array($first)) {
                foreach (['text', 'title', 'label', 'name'] as $k) {
                    if (isset($first[$k]) && is_string($first[$k]) && trim($first[$k]) !== '') {
                        return mb_strimwidth(trim($first[$k]), 0, 60, '…');
                    }
                }
            }
        }
        return '';
    }
}
