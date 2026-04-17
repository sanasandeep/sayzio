<?php

namespace App\Modules\Admin\Services;

use App\Modules\User\Controllers\BiolinkBlockController;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Support\Facades\DB;

/**
 * Capture and apply page / card templates.
 *
 * Snapshot shapes:
 *   Page: { biolink: {...}, blocks: [ {type, settings, is_active, children:[{...}]} ] }
 *   Card: { type:'card', settings, is_active, children: [ {type, settings, is_active} ] }
 *
 * On apply, every block's settings are re-sanitized through
 * BiolinkBlockController::sanitizeSettings — even though admin-curated
 * snapshots come from already-sanitized live blocks, we never trust the
 * stored payload (it may have been hand-edited via the snapshot JSON
 * editor in the admin form). All IDs / link_id / parent_id / timestamps
 * are dropped. _tab_id is preserved on page apply (menu_bar items travel
 * with the page snapshot, so refs remain valid) and stripped on card-only
 * apply (the target link's menu_bar may not have a matching tab).
 */
class TemplateService
{
    private ?BiolinkBlockController $sanitizer = null;

    private function sanitize(string $type, array $settings): array
    {
        if (!$this->sanitizer) {
            $this->sanitizer = app(BiolinkBlockController::class);
        }
        return $this->sanitizer->sanitizeSettings($type, $settings);
    }

    public function captureFromLink(Link $link): array
    {
        $biolink = (array) ($link->settings['biolink'] ?? []);

        $blocks = $link->biolinkBlocks()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => function ($q) { $q->orderBy('sort_order'); }])
            ->get();

        $serialized = $blocks->map(function (BiolinkBlock $b) {
            return $this->serializeBlock($b, true);
        })->all();

        return [
            'biolink' => $biolink,
            'blocks' => $serialized,
        ];
    }

    public function captureFromCardBlock(BiolinkBlock $card): array
    {
        if ($card->type !== 'card') {
            throw new \InvalidArgumentException('Block must be of type "card".');
        }
        $card->loadMissing(['children' => function ($q) { $q->orderBy('sort_order'); }]);
        return $this->serializeBlock($card, true);
    }

    private function serializeBlock(BiolinkBlock $b, bool $includeChildren): array
    {
        $data = [
            'type' => $b->type,
            'settings' => $b->settings ?? [],
            'is_active' => (bool) $b->is_active,
        ];
        if ($includeChildren && $b->type === 'card') {
            $data['children'] = $b->children->map(function ($c) {
                return $this->serializeBlock($c, false);
            })->all();
        }
        return $data;
    }

    public function applyPageToLink(Link $link, array $snapshot, bool $replace = true): void
    {
        DB::transaction(function () use ($link, $snapshot, $replace) {
            if ($replace) {
                $link->biolinkBlocks()->delete();
            }

            $settings = $link->settings ?? [];
            $existingBiolink = (array) ($settings['biolink'] ?? []);
            $tplBiolink = (array) ($snapshot['biolink'] ?? []);
            // Preserve link-specific identity (verified avatar/heading text & favicon)
            // by keeping existing keys for those fields if present.
            $preserveKeys = ['favicon_url', 'custom_branding_text', 'custom_branding_url', 'custom_branding_logo'];
            foreach ($preserveKeys as $k) {
                if (array_key_exists($k, $existingBiolink) && !array_key_exists($k, $tplBiolink)) {
                    $tplBiolink[$k] = $existingBiolink[$k];
                }
            }
            $settings['biolink'] = array_merge($existingBiolink, $tplBiolink);
            $link->settings = $settings;
            $link->save();

            $blocks = $snapshot['blocks'] ?? [];
            $baseSort = $replace ? 0 : ((int) ($link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1) + 1);
            foreach ($blocks as $i => $b) {
                $this->insertBlockTree($link, $b, null, $baseSort + $i, /*stripTabId*/ false);
            }
        });
    }

    public function applyCardToLink(Link $link, array $snapshot, ?int $insertAfterId = null): BiolinkBlock
    {
        return DB::transaction(function () use ($link, $snapshot, $insertAfterId) {
            if (($snapshot['type'] ?? null) !== 'card') {
                throw new \InvalidArgumentException('Card snapshot missing or invalid.');
            }

            if ($insertAfterId) {
                $after = BiolinkBlock::where('id', $insertAfterId)
                    ->where('link_id', $link->id)
                    ->whereNull('parent_id')
                    ->firstOrFail();
                $newSort = $after->sort_order + 1;
                $link->biolinkBlocks()
                    ->whereNull('parent_id')
                    ->where('sort_order', '>=', $newSort)
                    ->increment('sort_order');
            } else {
                $newSort = ((int) ($link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1)) + 1;
            }

            return $this->insertBlockTree($link, $snapshot, null, $newSort, /*stripTabId*/ true);
        });
    }

    private function insertBlockTree(Link $link, array $b, ?int $parentId, int $sortOrder, bool $stripTabId): BiolinkBlock
    {
        $settings = is_array($b['settings'] ?? null) ? $b['settings'] : [];
        if ($stripTabId) {
            unset($settings['_tab_id']);
        }
        $type = (string) ($b['type'] ?? '');
        if (!array_key_exists($type, BiolinkBlock::TYPES)) {
            throw new \InvalidArgumentException("Unknown block type in snapshot: {$type}");
        }

        // Re-sanitize template payload through the same pipeline used for
        // user-submitted block settings — never trust stored snapshot JSON.
        $settings = $this->sanitize($type, $settings);

        $block = $link->biolinkBlocks()->create([
            'type' => $type,
            'settings' => $settings,
            'is_active' => array_key_exists('is_active', $b) ? (bool) $b['is_active'] : true,
            'sort_order' => $sortOrder,
            'parent_id' => $parentId,
        ]);

        if ($type === 'card' && !empty($b['children']) && is_array($b['children'])) {
            foreach (array_values($b['children']) as $i => $child) {
                $this->insertBlockTree($link, $child, $block->id, $i, $stripTabId);
            }
        }
        return $block;
    }
}
