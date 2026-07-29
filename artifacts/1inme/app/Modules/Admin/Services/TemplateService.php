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
        if ($includeChildren && $b->isContainer()) {
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
                $stamp = [
                    'template_id'   => $template->id,
                    'template_name' => $template->name,
                    'locked_at'     => now()->toIso8601String(),
                    'block_styles'  => $this->snapshotBlockStyles($snapshot),
                ];
                // Admin-defined color palettes travel inside the stamp so
                // the editor's palette picker works without re-reading the
                // template row (and survives template edits/deletion). The
                // first palette is the template default and is applied to
                // the page immediately.
                $palettes = $template->palettes();
                if (!empty($palettes)) {
                    $stamp['palettes'] = $palettes;
                    $stamp['palette']  = $palettes[0]['key'];
                    $settings['biolink'] = array_merge($settings['biolink'], $palettes[0]['colors']);
                }
                $settings['biolink']['design_locked'] = $stamp;
            } else {
                unset($settings['biolink']['design_locked']);
            }
            // Any fresh (replace) apply supersedes a previous detach: the
            // release stamp only makes sense while the detached design is
            // still on the page, so clear it to keep the lifecycle
            // deterministic (re-attach goes through reattachPageToLink).
            unset($settings['biolink']['design_lock_released']);

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
     * Switch a design-locked page to one of the palettes stored in its
     * design-lock stamp. Applies the palette's color keys onto the biolink
     * settings and records the selection. Returns false when the key does
     * not exist in the stamp (no changes made).
     */
    public function applyPaletteToLink(Link $link, string $paletteKey): bool
    {
        $info = $link->designLockInfo();
        if (!$info) return false;
        $palette = collect($info['palettes'] ?? [])
            ->first(fn ($p) => is_array($p) && ($p['key'] ?? null) === $paletteKey);
        if (!$palette || !is_array($palette['colors'] ?? null)) return false;

        $settings = $link->settings ?? [];
        $settings['biolink'] = array_merge((array) ($settings['biolink'] ?? []), $palette['colors']);
        $settings['biolink']['design_locked']['palette'] = $paletteKey;
        $link->settings = $settings;
        $link->save();
        return true;
    }

    /**
     * Re-attach a previously detached page to the same design-locked
     * template WITHOUT replacing the creator's blocks. Recreates any fixed
     * template blocks that were deleted while detached, re-pins the fixed
     * blocks as a contiguous prefix in template order, and reflows the
     * user's own blocks after them (preserving their relative order). The
     * design-lock stamp is rebuilt from the template's current snapshot;
     * the palette selected before detaching is restored when it still
     * exists on the template.
     */
    public function reattachPageToLink(Link $link, \App\Modules\Admin\Models\PageTemplate $template): void
    {
        DB::transaction(function () use ($link, $template) {
            $snapshot = $template->snapshot;
            $settings = $link->settings ?? [];
            $released = (array) ($settings['biolink']['design_lock_released'] ?? []);

            // Merge the template's design settings back onto the page,
            // preserving the same link-specific identity keys as a fresh
            // apply.
            $existingBiolink = (array) ($settings['biolink'] ?? []);
            $tplBiolink = (array) ($snapshot['biolink'] ?? []);
            $preserveKeys = ['favicon_url', 'custom_branding_text', 'custom_branding_url', 'custom_branding_logo', 'menu_bar'];
            foreach ($preserveKeys as $k) {
                if (array_key_exists($k, $existingBiolink) && !array_key_exists($k, $tplBiolink)) {
                    $tplBiolink[$k] = $existingBiolink[$k];
                }
            }
            $settings['biolink'] = array_merge($existingBiolink, $tplBiolink);

            // Rebuild the design-lock stamp from the template's CURRENT
            // snapshot (it may have been edited since the original attach).
            $stamp = [
                'template_id'   => $template->id,
                'template_name' => $template->name,
                'locked_at'     => now()->toIso8601String(),
                'block_styles'  => $this->snapshotBlockStyles($snapshot),
            ];
            $palettes = $template->palettes();
            if (!empty($palettes)) {
                $stamp['palettes'] = $palettes;
                $wanted = (string) ($released['palette'] ?? '');
                $restore = collect($palettes)->first(fn ($p) => ($p['key'] ?? null) === $wanted) ?: $palettes[0];
                $stamp['palette'] = $restore['key'];
                $settings['biolink'] = array_merge($settings['biolink'], (array) ($restore['colors'] ?? []));
            }
            $settings['biolink']['design_locked'] = $stamp;
            unset($settings['biolink']['design_lock_released']);
            $link->settings = $settings;
            $link->save();

            // Fixed template blocks, in template order.
            $fixedSnapshot = array_values(array_filter(
                (array) ($snapshot['blocks'] ?? []),
                fn ($b) => is_array($b) && !empty(((array) ($b['settings'] ?? []))['_fixed'] ?? null)
            ));

            $existing = $link->biolinkBlocks()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get();

            // Match each fixed snapshot block to a surviving block of the
            // same type that still carries `_fixed`; consume matches in
            // order so duplicates pair one-to-one. Missing ones get
            // recreated from the snapshot.
            $pool = $existing->filter(fn ($b) => !empty(($b->settings ?? [])['_fixed']))->values()->all();
            $prefix = [];
            foreach ($fixedSnapshot as $snap) {
                $matchIdx = null;
                foreach ($pool as $idx => $candidate) {
                    if ($candidate !== null && $candidate->type === ($snap['type'] ?? null)) {
                        $matchIdx = $idx;
                        break;
                    }
                }
                if ($matchIdx !== null) {
                    $prefix[] = $pool[$matchIdx];
                    $pool[$matchIdx] = null;
                } else {
                    // Recreate the missing fixed block from the snapshot
                    // (sort order fixed up below).
                    $prefix[] = $this->insertBlockTree($link, $snap, null, 0, /*stripTabId*/ false);
                }
            }
            // Any leftover `_fixed` blocks no longer present in the template
            // snapshot lose their pin and reflow with the user's blocks.
            $leftoverFixed = array_values(array_filter($pool));
            foreach ($leftoverFixed as $b) {
                $s = $b->settings ?? [];
                unset($s['_fixed']);
                $b->settings = $s;
            }

            $prefixIds = array_map(fn ($b) => $b->id, $prefix);
            $rest = $existing->filter(fn ($b) => !in_array($b->id, $prefixIds, true))->values();

            $sort = 0;
            foreach ($prefix as $b) {
                $b->sort_order = $sort++;
                $b->save();
            }
            foreach ($rest as $b) {
                $b->sort_order = $sort++;
                $b->save();
            }

            // Re-attach re-applies the template's design to EVERY block
            // (root + children): styling is server-owned while locked, so
            // any styles the user customized while detached are replaced by
            // the template's per-type style (falling back to defaults when
            // the template never styled this type) — mirroring how new
            // blocks are seeded on a locked page.
            $styleMap = (array) $stamp['block_styles'];
            foreach ($link->biolinkBlocks()->get() as $b) {
                $s = $b->settings ?? [];
                $s['_style'] = array_merge(
                    BiolinkBlock::STYLE_DEFAULTS,
                    \App\Modules\User\Support\BlockDefaults::styleForType($b->type),
                    (array) ($styleMap[$b->type] ?? [])
                );
                unset($s['_style_custom_snapshot']);
                $b->settings = $s;
                $b->save();
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
        if (BiolinkBlock::isContainerType($type) && !empty($b['children']) && is_array($b['children'])) {
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

        if (BiolinkBlock::isContainerType($type) && !empty($b['children']) && is_array($b['children'])) {
            foreach (array_values($b['children']) as $i => $child) {
                $this->insertBlockTree($link, $child, $block->id, $i, $stripTabId);
            }
        }
        return $block;
    }
}
