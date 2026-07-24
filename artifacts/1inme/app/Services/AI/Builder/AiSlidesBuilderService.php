<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlide;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BlockDefaults;

/**
 * AI builder for the Slides link type.
 *
 * The model plans a deck as slides, each carrying a handful of simple
 * content blocks (heading / paragraph / list / image / cta_button). Blocks
 * are persisted as BiolinkBlocks on the link (the deck's content pool) and
 * each slide references its blocks via block_ids — exactly how the manual
 * editor wires things up.
 */
class AiSlidesBuilderService extends AbstractAiTypeBuilderService
{
    public const FEATURE = 'slides_builder';

    /** Deck-level guardrails (editor allows 50 slides / 10 blocks each). */
    public const MAX_SLIDES = 20;
    public const MAX_BLOCKS_PER_SLIDE = 6;

    /** Block types the slides builder may emit. */
    public const ALLOWED_BLOCK_TYPES = ['heading', 'paragraph', 'list', 'image', 'cta_button'];

    public function feature(): string  { return self::FEATURE; }
    public function linkType(): string { return Link::TYPE_SLIDES; }
    public function label(): string    { return 'AI Slides builder'; }

    protected function systemPrompt(User $user): string
    {
        return <<<'PROMPT'
You are a presentation designer for a swipeable web slide deck. Answer with ONE JSON object only — no prose, no markdown fences.

Schema:
{
  "slides": [
    {
      "title": "short slide label (max 160 chars)",
      "background": {"type": "color", "color": "#0f172a"} OR {"type": "gradient", "from_color": "#hex", "to_color": "#hex"},
      "blocks": [
        {"type": "heading",    "settings": {"text": "...", "size": "h1|h2|h3"}},
        {"type": "paragraph",  "settings": {"text": "..."}},
        {"type": "list",       "settings": {"items": ["plain string", "plain string"]}},
        {"type": "image",      "settings": {"url": "ONLY a supplied image URL", "alt": "..."}},
        {"type": "cta_button", "settings": {"url": "ONLY a supplied URL", "text": "..."}}
      ]
    }
  ]
}

Rules:
- 3 to 20 slides, 1 to 6 blocks per slide.
- Only use the block types listed above.
- "list" items are plain strings, never objects.
- Only reference image URLs and link URLs the user explicitly supplied; keep them EXACTLY as given. If none were supplied, use no image or cta_button blocks.
- Pick dark, high-contrast backgrounds unless the brief asks otherwise.
- Write real, useful copy from the brief — never lorem ipsum.
PROMPT;
    }

    protected function materialize(User $user, Link $link, array $parsed, array $links, array $images): array
    {
        $slidesIn = is_array($parsed['slides'] ?? null) ? $parsed['slides'] : [];
        $slidesIn = array_slice(array_values(array_filter($slidesIn, 'is_array')), 0, self::MAX_SLIDES);

        $deck = LinkSlideDeck::firstOrCreate(
            ['link_id' => $link->id],
            [
                'workspace_id' => $link->workspace_id,
                'version'      => 1,
                'is_published' => false,
                'settings'     => [
                    'theme'        => ['background' => '#0f172a', 'accent' => '#3d6bff', 'text' => '#f8fafc'],
                    'transition'   => 'slide',
                    'auto_advance' => 0,
                    'loop'         => false,
                ],
            ],
        );

        // Replace the previous generated deck: drop old slides and the
        // blocks they referenced (other link blocks are left untouched).
        $oldBlockIds = [];
        foreach ($deck->slides()->get() as $old) {
            foreach ((array) ($old->block_ids ?? []) as $bid) {
                $oldBlockIds[] = (int) $bid;
            }
        }
        $deck->slides()->delete();
        if ($oldBlockIds) {
            BiolinkBlock::where('link_id', $link->id)->whereIn('id', $oldBlockIds)->delete();
        }

        $sortBlock = (int) BiolinkBlock::where('link_id', $link->id)->max('sort_order');
        $slideCount = 0;
        $blockCount = 0;

        foreach ($slidesIn as $i => $slideIn) {
            $blockIds = [];
            $blocksIn = is_array($slideIn['blocks'] ?? null) ? $slideIn['blocks'] : [];

            foreach (array_slice($blocksIn, 0, self::MAX_BLOCKS_PER_SLIDE) as $blockIn) {
                $block = $this->buildSlideBlock($blockIn, $links, $images);
                if ($block === null) continue;

                $row = BiolinkBlock::create([
                    'link_id'    => $link->id,
                    'type'       => $block['type'],
                    'settings'   => $block['settings'],
                    'sort_order' => ++$sortBlock,
                    'is_active'  => true,
                ]);
                $blockIds[] = $row->id;
                $blockCount++;
            }

            if (!$blockIds) continue;

            LinkSlide::create([
                'deck_id'    => $deck->id,
                'sort_order' => $slideCount,
                'title'      => $this->str($slideIn['title'] ?? null, 160) ?? ('Slide ' . ($i + 1)),
                'block_ids'  => $blockIds,
                'background' => $this->cleanBackground($slideIn['background'] ?? null),
                'settings'   => [],
            ]);
            $slideCount++;
        }

        if ($slideCount === 0) {
            throw new \RuntimeException('The AI response contained no usable slides. Your coins were refunded — please try again.');
        }

        return ['slides' => $slideCount, 'blocks' => $blockCount];
    }

    /** @return array{type:string,settings:array}|null */
    private function buildSlideBlock(mixed $blockIn, array $links, array $images): ?array
    {
        if (!is_array($blockIn)) return null;
        $type = is_string($blockIn['type'] ?? null) ? $blockIn['type'] : '';
        if (!in_array($type, self::ALLOWED_BLOCK_TYPES, true)) return null;

        $in = is_array($blockIn['settings'] ?? null) ? $blockIn['settings'] : [];

        $settings = match ($type) {
            'heading' => [
                'text' => $this->str($in['text'] ?? null, 300),
                'size' => in_array($in['size'] ?? null, ['h1', 'h2', 'h3'], true) ? $in['size'] : 'h2',
            ],
            'paragraph' => [
                'text' => $this->str($in['text'] ?? null, 2000),
            ],
            'list' => [
                'items' => array_slice(array_values(array_filter(array_map(
                    fn ($item) => is_string($item) ? mb_substr(trim($item), 0, 300) : null,
                    is_array($in['items'] ?? null) ? $in['items'] : [],
                ), fn ($item) => $item !== null && $item !== '')), 0, 12),
            ],
            'image' => [
                'url' => $this->suppliedImage($in['url'] ?? null, $images),
                'alt' => $this->str($in['alt'] ?? null, 200) ?? '',
            ],
            'cta_button' => [
                'url'  => (is_string($in['url'] ?? null) && in_array(trim($in['url']), $links, true)) ? trim($in['url']) : null,
                'text' => $this->str($in['text'] ?? null, 120) ?? 'Learn more',
            ],
            default => [],
        };

        // Drop blocks whose essential content is missing.
        $essential = match ($type) {
            'heading', 'paragraph' => $settings['text'] ?? null,
            'list'                 => ($settings['items'] ?? []) ? 'ok' : null,
            'image'                => $settings['url'] ?? null,
            'cta_button'           => $settings['url'] ?? null,
            default                => null,
        };
        if ($essential === null) return null;

        // Seed first-paint content + structural styling like the editor does.
        $merged = array_replace(BlockDefaults::contentForType($type), array_filter($settings, fn ($v) => $v !== null));
        unset($merged['_placeholder'], $merged['_placeholder_seed']);
        $merged['_style'] = array_merge(BiolinkBlock::STYLE_DEFAULTS, BlockDefaults::styleForType($type));

        return ['type' => $type, 'settings' => $merged];
    }

    private function cleanBackground(mixed $bg): array
    {
        if (!is_array($bg)) return ['type' => 'color', 'color' => '#0f172a'];

        $hex = fn ($v, $fallback) => (is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($v))) ? trim($v) : $fallback;

        if (($bg['type'] ?? null) === 'gradient') {
            return [
                'type'       => 'gradient',
                'from_color' => $hex($bg['from_color'] ?? null, '#0f172a'),
                'to_color'   => $hex($bg['to_color'] ?? null, '#1e293b'),
            ];
        }

        return ['type' => 'color', 'color' => $hex($bg['color'] ?? null, '#0f172a')];
    }
}
