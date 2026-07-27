<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Exceptions\UnknownBlockTypeException;
use App\Modules\User\Controllers\BiolinkBlockController;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PreviewBiolinkBlock;
use App\Modules\User\Models\PreviewLink;
use App\Modules\User\Models\User;
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

    /**
     * @param \App\Modules\Admin\Models\PageTemplate|null $template The page
     *        template the snapshot came from, when applying a stored
     *        template. Design-locked templates stamp the link with a
     *        `settings.biolink.design_locked` marker (template identity +
     *        a per-block-type `_style` map used to seed new blocks); an
     *        unlocked template — or a raw snapshot — clears any prior stamp.
     */
    public function applyPageToLink(Link $link, array $snapshot, bool $replace = true, ?\App\Modules\Admin\Models\PageTemplate $template = null): void
    {
        DB::transaction(function () use ($link, $snapshot, $replace, $template) {
            if ($replace) {
                $link->biolinkBlocks()->delete();
            }

            $settings = $link->settings ?? [];
            $existingBiolink = (array) ($settings['biolink'] ?? []);
            $tplBiolink = (array) ($snapshot['biolink'] ?? []);
            // Preserve link-specific identity (verified avatar/heading text &
            // favicon) and the existing nav-tab structure (menu_bar) when the
            // template doesn't explicitly bring its own.
            $preserveKeys = ['favicon_url', 'custom_branding_text', 'custom_branding_url', 'custom_branding_logo', 'menu_bar'];
            foreach ($preserveKeys as $k) {
                if (array_key_exists($k, $existingBiolink) && !array_key_exists($k, $tplBiolink)) {
                    $tplBiolink[$k] = $existingBiolink[$k];
                }
            }
            $settings['biolink'] = array_merge($existingBiolink, $tplBiolink);

            // Design lock: applying a locked template stamps the link so the
            // editor hides styling surfaces and the server strips design keys;
            // applying an unlocked template (or a raw snapshot) releases any
            // previous lock — the new design replaces the locked one.
            if ($template && $template->design_locked) {
                $settings['biolink']['design_locked'] = [
                    'template_id'   => $template->id,
                    'template_name' => $template->name,
                    'locked_at'     => now()->toIso8601String(),
                    'block_styles'  => $this->snapshotBlockStyles($snapshot),
                ];
            } else {
                unset($settings['biolink']['design_locked']);
            }

            $link->settings = $settings;
            $link->save();

            $blocks = $snapshot['blocks'] ?? [];
            $baseSort = $replace ? 0 : ((int) ($link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1) + 1);
            foreach ($blocks as $i => $b) {
                $this->insertBlockTree($link, $b, null, $baseSort + $i, /*stripTabId*/ false);
            }
        });
    }

    /**
     * Apply a card-template snapshot.
     *
     * Tab-awareness:
     * - If the caller supplies $tabId (id of a menu_bar tab in this link),
     *   we set `_tab_id` on the inserted card so it shows up on that tab.
     * - If $tabId is null AND the snapshot has its own _tab_id from the
     *   source link, we honor it (admin curated). If the supplied tab_id
     *   is the empty string ('' explicitly), we strip _tab_id (general/all
     *   tabs).
     */
    public function applyCardToLink(Link $link, array $snapshot, ?int $insertAfterId = null, $tabId = null): BiolinkBlock
    {
        return DB::transaction(function () use ($link, $snapshot, $insertAfterId, $tabId) {
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

            // Apply tab context: explicit tabId wins; '' means strip; null = honor snapshot.
            if ($tabId !== null) {
                $snapshot['settings'] = (array) ($snapshot['settings'] ?? []);
                if ($tabId === '' || $tabId === false) {
                    unset($snapshot['settings']['_tab_id']);
                } else {
                    $snapshot['settings']['_tab_id'] = (string) $tabId;
                }
            }

            return $this->insertBlockTree($link, $snapshot, null, $newSort, /*stripTabId*/ false);
        });
    }

    /**
     * Build an unsaved PreviewLink populated with PreviewBiolinkBlock instances
     * straight from a snapshot — no DB writes, no transaction. Used by the
     * template-preview endpoint so clicking a template card renders instantly
     * without churning the DB with INSERT/ROLLBACK pairs on every click.
     *
     * Block settings still pass through the same sanitizer as applyPageToLink
     * so the preview matches what the template will look like once applied.
     */
    public function buildPreviewLink(array $snapshot, User $user, string $title): PreviewLink
    {
        $link = new PreviewLink();
        $link->forceFill([
            'id'        => 0,
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => 'preview-' . Link::generateAlias(),
            'title'     => $title,
            'is_active' => true,
            'settings'  => ['biolink' => (array) ($snapshot['biolink'] ?? [])],
        ]);
        $link->exists = false;
        $link->setRelation('user', $user);
        // Pre-load `pixels` as an empty collection so the biolink view's
        // `$link->pixels->count()` / `@foreach($link->pixels)` resolves
        // without Eloquent trying to lazy-load the (non-existent) relation.
        $link->setRelation('pixels', collect());

        $all = collect();
        $topActive = collect();
        $nextId = 1;

        foreach (($snapshot['blocks'] ?? []) as $i => $b) {
            $block = $this->buildPreviewBlock($link, $b, null, $i, $nextId, $all, 'block #' . ($i + 1));
            if ($block->is_active) {
                $topActive->push($block);
            }
        }

        $link->previewBlocks = $all;
        $link->previewActiveBlocks = $topActive;
        return $link;
    }

    private function buildPreviewBlock(PreviewLink $link, array $b, ?int $parentId, int $sortOrder, int &$nextId, $allSink, string $position): PreviewBiolinkBlock
    {
        $type = (string) ($b['type'] ?? '');
        if (!array_key_exists($type, BiolinkBlock::TYPES)) {
            throw new UnknownBlockTypeException($type, $position);
        }
        $settings = is_array($b['settings'] ?? null) ? $b['settings'] : [];
        $settings = $this->sanitize($type, $settings);

        $block = new PreviewBiolinkBlock();
        $block->forceFill([
            'id'         => $nextId++,
            'link_id'    => $link->id,
            'type'       => $type,
            'settings'   => $settings,
            'is_active'  => array_key_exists('is_active', $b) ? (bool) $b['is_active'] : true,
            'sort_order' => $sortOrder,
            'parent_id'  => $parentId,
        ]);
        $block->exists = false;
        $block->setRelation('link', $link);

        $children = collect();
        $activeChildren = collect();
        if ($type === 'card' && !empty($b['children']) && is_array($b['children'])) {
            foreach (array_values($b['children']) as $i => $child) {
                $childBlock = $this->buildPreviewBlock($link, $child, $block->id, $i, $nextId, $allSink, $position . ' → child #' . ($i + 1));
                $children->push($childBlock);
                if ($childBlock->is_active) {
                    $activeChildren->push($childBlock);
                }
            }
        }
        $block->previewChildren = $children;
        $block->previewActiveChildren = $activeChildren;
        $block->setRelation('children', $children);

        $allSink->push($block);
        return $block;
    }

    /**
     * Per-block-type `_style` map derived from a page snapshot's blocks
     * (first occurrence per type wins, children included). Stored inside
     * the design-lock stamp so new blocks created on a locked page can be
     * seeded with the template's styling for their type.
     *
     * @return array<string, array>
     */
    public function snapshotBlockStyles(array $snapshot): array
    {
        $map = [];
        $walk = function (array $blocks) use (&$walk, &$map) {
            foreach ($blocks as $b) {
                if (!is_array($b)) continue;
                $type = (string) ($b['type'] ?? '');
                $style = $b['settings']['_style'] ?? null;
                if ($type !== '' && is_array($style) && !isset($map[$type])) {
                    $map[$type] = $style;
                }
                if (!empty($b['children']) && is_array($b['children'])) {
                    $walk($b['children']);
                }
            }
        };
        $walk((array) ($snapshot['blocks'] ?? []));
        return $map;
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
