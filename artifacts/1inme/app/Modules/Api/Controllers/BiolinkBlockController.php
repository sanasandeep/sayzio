<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Services\OgMetadataService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

/**
 * CRUD for the blocks (sections) that make up an authenticated user's
 * biolink page. Public viewing of blocks is via BiolinkController@show.
 */
class BiolinkBlockController extends Controller
{
    use ApiResponses;

    public function index(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Link in Bio not found');

        $items = BiolinkBlock::where('link_id', $link->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => $this->transform($b))
            ->all();
        return $this->ok(['items' => $items]);
    }

    /**
     * Block-type palette catalog for the mobile editor. Mirrors the web
     * biolink-editor palette: the same category labels and the same
     * picker-visible block types (aliases collapsed via pickerTypes(),
     * system/verified slugs hidden). Each type carries a per-user
     * `locked` flag derived from the same plan-gating check the web
     * gallery uses (User::userCanUseBlockType), so the app can render
     * locked tiles as upgrade prompts. User-scoped, not link-scoped —
     * the catalog is identical across a user's biolinks.
     */
    public function catalog(Request $request)
    {
        $user = $request->user();

        $types = [];
        $usedCategories = [];
        foreach (BiolinkBlock::pickerTypes() as $slug => $meta) {
            if (!empty($meta['system']) || ($meta['category'] ?? '') === 'verified') {
                continue;
            }
            $category = $meta['category'] ?? 'basic';
            $usedCategories[$category] = true;
            $types[] = [
                'type'     => $slug,
                'label'    => $meta['label'],
                'icon'     => $meta['icon'],
                'category' => $category,
                'locked'   => !$user->userCanUseBlockType($slug),
            ];
        }

        $categories = [];
        foreach (BiolinkBlock::CATEGORIES as $key => $label) {
            if (!empty($usedCategories[$key])) {
                $categories[] = ['key' => $key, 'label' => $label];
            }
        }

        return $this->ok([
            'categories' => $categories,
            'types'      => $types,
        ]);
    }

    /**
     * Background preset catalog (mobile parity for the web Appearance
     * "Presets" gallery). Static catalog — same 157 presets the web picker
     * shows — with per-preset color stops parsed server-side so React
     * Native can approximate each swatch with a LinearGradient.
     */
    public function bgPresets(Request $request)
    {
        return $this->ok(\App\Modules\User\Support\BgPresetCatalog::forApi());
    }

    /**
     * Background template catalog (mobile parity for the web Appearance
     * "Templates" gallery). Admin-managed `bg_templates` rows with color
     * stops parsed server-side plus md5-gated pre-rendered PNG swatches so
     * mobile shows each template's REAL texture, not a gradient tint.
     */
    public function bgTemplates(Request $request)
    {
        return $this->ok(\App\Modules\User\Support\BgTemplateCatalog::forApi());
    }

    public function store(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Link in Bio not found');

        $data = $request->validate([
            'type'       => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer'],
            'parent_id'  => ['nullable', 'integer'],
            'is_active'  => ['nullable', 'boolean'],
            'settings'   => ['nullable', 'array'],
        ]);
        $sort = $data['sort_order'] ?? ((int) BiolinkBlock::where('link_id', $link->id)->max('sort_order') + 1);
        $settings = $data['settings'] ?? [];

        // Design lock: fixed template blocks form a contiguous prefix at the
        // top of the page — a new root block can never be slotted into (or
        // ahead of) it, so clamp the requested sort below the prefix.
        if ($link->isDesignLocked() && ($data['parent_id'] ?? null) === null) {
            $fixedCount = BiolinkBlock::where('link_id', $link->id)->whereNull('parent_id')
                ->get(['id', 'settings'])
                ->filter(fn ($fb) => !empty($fb->settings['_fixed']))
                ->count();
            if ($fixedCount > 0 && $sort < $fixedCount) {
                $sort = $fixedCount;
            }
        }

        // Design lock parity with the web editor: on a locked page a new
        // block's `_style` is always seeded server-side from the template's
        // styling for its type — any client-sent style is ignored.
        if ($link->isDesignLocked()) {
            $settings['_style'] = array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                \App\Modules\User\Support\BlockDefaults::styleForType($data['type']),
                $link->designLockStyleFor($data['type']) ?? []
            );
            unset($settings['_style_custom_snapshot']);
        }

        // Photo sticker overlays: same server-side sanitation as update()
        // (and the web editor) so a create can't persist unclamped or
        // foreign-file sticker entries either.
        $settings = $this->sanitizeSettingsStickers($settings);

        $b = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => $data['type'],
            'sort_order' => $sort,
            'parent_id'  => $data['parent_id'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
            'settings'   => $settings,
        ]);
        return $this->created(['block' => $this->transform($b)]);
    }

    public function update(Request $request, int $linkId, int $id)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Link in Bio not found');

        $b = BiolinkBlock::where('link_id', $link->id)->find($id);
        if (!$b) return $this->notFound('Block not found');

        $data = $request->validate([
            'type'       => ['sometimes', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer'],
            'parent_id'  => ['sometimes', 'nullable', 'integer'],
            'is_active'  => ['sometimes', 'boolean'],
            'settings'   => ['sometimes', 'array'],
            // Task #1094 — per-block scarcity. `start_date` is exposed
            // for symmetry with the web editor's "goes live" field; the
            // mobile UI mostly cares about `end_date` + `max_clicks`.
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date'   => ['sometimes', 'nullable', 'date'],
            'max_clicks' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
        ]);
        if (array_key_exists('max_clicks', $data)) {
            // 0 collapses to null (unlimited) so the cap field can be cleared
            // from the mobile editor without juggling explicit `null` JSON.
            $data['max_clicks'] = ($data['max_clicks'] === null || (int) $data['max_clicks'] <= 0)
                ? null : (int) $data['max_clicks'];
        }

        // Design lock parity: while locked, `_style` (and the pre-variant
        // snapshot) are server-owned — force the stored values back over
        // whatever the client sent so content edits save but styling can't drift.
        if ($link->isDesignLocked() && array_key_exists('settings', $data) && is_array($data['settings'])) {
            $existing = $b->settings ?? [];
            if (array_key_exists('_style', $existing)) {
                $data['settings']['_style'] = $existing['_style'];
            } else {
                unset($data['settings']['_style']);
            }
            if (array_key_exists('_style_custom_snapshot', $existing)) {
                $data['settings']['_style_custom_snapshot'] = $existing['_style_custom_snapshot'];
            } else {
                unset($data['settings']['_style_custom_snapshot']);
            }
        }

        // `_fixed` (template-pinned position) is admin-owned: always carry the
        // stored value over whatever the client sent, and while the page is
        // design-locked a fixed block's position fields are read-only too.
        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            if (!empty(($b->settings ?? [])['_fixed'])) {
                $data['settings']['_fixed'] = true;
            } else {
                unset($data['settings']['_fixed']);
            }
        }
        if ($link->isDesignLocked()) {
            if (!empty(($b->settings ?? [])['_fixed'])) {
                // Fixed block: position fields are read-only.
                unset($data['sort_order'], $data['parent_id']);
            } elseif (array_key_exists('sort_order', $data) && $b->parent_id === null && ($data['parent_id'] ?? $b->parent_id) === null) {
                // Non-fixed root block: never allow it to slot into (or
                // ahead of) the fixed prefix at the top of the page.
                $fixedCount = BiolinkBlock::where('link_id', $link->id)->whereNull('parent_id')
                    ->get(['id', 'settings'])
                    ->filter(fn ($fb) => !empty($fb->settings['_fixed']))
                    ->count();
                if ($fixedCount > 0 && (int) $data['sort_order'] < $fixedCount) {
                    $data['sort_order'] = $fixedCount;
                }
            }
        }

        // Photo sticker overlays (Task #5957): the mobile editor merges
        // `_style._photo_stickers` back into settings on save because this
        // PATCH replaces settings wholesale. Run the SAME sanitizer the web
        // editor uses so ownership checks + clamps (pos presets, size,
        // rotate, dx/dy ±80, entry cap) can never be bypassed via the API.
        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            $data['settings'] = $this->sanitizeSettingsStickers($data['settings']);
        }

        $b->fill($data)->save();
        return $this->ok(['block' => $this->transform($b->fresh())]);
    }

    /**
     * Returns the live "limits" state for every block on a biolink so
     * the mobile editor preview can mirror what the public page shows
     * without inventing its own countdown math. Public — no auth needed
     * (the data already shows on the live page); rate-limited at the
     * route level.
     */
    public function publicLimits(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, Link::BIOLINK_FAMILY, true)) {
            $link = null;
        }
        // Mirror BiolinkController@show: missing or disabled links are
        // 404 (don't leak existence), and accessibility (paywall, expiry,
        // password-protected, etc.) is enforced before we touch blocks.
        if (!$link || !$link->is_active) return $this->notFound('Link in Bio not found');
        if (!$link->isAccessible())     return $this->notFound('Link in Bio not available');

        // Visibility gate — public/registered/followers/subscribers. We
        // re-implement the same logic BiolinkController::checkVisibility
        // uses so we don't expose limits metadata for biolinks the caller
        // wouldn't be allowed to see in the first place.
        $owner  = $link->user;
        $viewer = $request->user();
        $vis    = $link->visibility ?? 'public';
        $allowed = false;
        if ($vis === 'public') {
            $allowed = true;
        } elseif ($viewer && $owner && (int) $viewer->id === (int) $owner->id) {
            $allowed = true;
        } elseif (!$viewer) {
            $allowed = false;
        } elseif ($vis === 'registered') {
            $allowed = true;
        } elseif ($vis === 'followers') {
            $allowed = Follow::where('follower_id', $viewer->id)
                ->where('creator_id', $owner->id)->exists();
        } elseif ($vis === 'subscribers') {
            $allowed = Subscriber::where('user_id', $owner->id)
                ->where('email', $viewer->email)
                ->where('status', 'active')->exists();
        }
        if (!$allowed) return $this->notFound('Link in Bio not found');

        // Only surface blocks that are themselves eligible to be shown:
        // active, schedule-window has begun. We deliberately KEEP expired
        // ones in the response so the UI can flip them to the sold-out
        // state on stale tabs without a hard reload — but we stay within
        // the same is_active + start_date envelope as the public renderer.
        $now = now();
        $items = BiolinkBlock::where('link_id', $link->id)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) {
                $q->whereNotNull('end_date')->orWhereNotNull('max_clicks');
            })
            ->get()
            ->map(fn (BiolinkBlock $b) => $b->limitsState())
            ->values()
            ->all();

        return $this->ok(['items' => $items, 'now' => $now->toIso8601String()]);
    }

    /**
     * Fetch Open Graph metadata (title, description, og:image / favicon
     * fallback) for a URL entered in the mobile block editor. Mirrors the
     * web editor's "Fetch details" endpoint
     * (User\BiolinkBlockController::ogMeta): same per-user rate limit (the
     * key is shared with the web limiter so the combined budget stays
     * 10/min) and the same SSRF-guarded OgMetadataService extractor.
     */
    public function ogMeta(Request $request)
    {
        $url = trim((string) $request->input('url', ''));
        if ($url === '') {
            return $this->fail('Please enter a URL first.', 422);
        }

        $rateKey = 'og-meta:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return $this->fail("Too many requests. Try again in {$seconds}s.", 429);
        }
        RateLimiter::hit($rateKey, 60);

        try {
            $meta = app(OgMetadataService::class)->extractFromUrl($url);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok(['meta' => $meta]);
    }

    public function destroy(Request $request, int $linkId, int $id)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Link in Bio not found');

        $b = BiolinkBlock::where('link_id', $link->id)->find($id);
        if (!$b) return $this->notFound('Block not found');
        if ($link->isDesignLocked() && !empty(($b->settings ?? [])['_fixed'])) {
            return $this->fail('This block is fixed by the template and cannot be removed. Detach from the template to unlock it.', 403, 'design_locked_fixed_block');
        }
        $b->delete();
        return $this->noContent();
    }

    public function reorder(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Link in Bio not found');

        $data = $request->validate([
            'order'        => ['required', 'array', 'min:1'],
            'order.*'      => ['integer'],
        ]);

        // Design-lock parity with the web editor: fixed template blocks form
        // a contiguous prefix in their original relative order, so reject any
        // order that moves them or slots user blocks between them.
        if ($link->isDesignLocked()) {
            $fixedIds = BiolinkBlock::where('link_id', $link->id)->whereNull('parent_id')
                ->orderBy('sort_order')->get(['id', 'settings'])
                ->filter(fn ($b) => !empty($b->settings['_fixed']))
                ->pluck('id')->values()->all();
            if ($fixedIds) {
                // Require the fixed blocks as an exact prefix of the
                // submitted order. Partial payloads that omit them would
                // still renumber user blocks from 0 and slide them above
                // the pinned blocks, so they're rejected too.
                $submitted = array_map('intval', $data['order']);
                $prefix = array_slice($submitted, 0, count($fixedIds));
                if ($prefix != $fixedIds) {
                    return $this->fail('Some blocks are fixed by the template and cannot be moved.', 422, 'design_locked_fixed_block');
                }
            }
        }

        foreach ($data['order'] as $i => $blockId) {
            BiolinkBlock::where('link_id', $link->id)->where('id', $blockId)->update(['sort_order' => $i]);
        }
        return $this->ok(['reordered' => true]);
    }

    /**
     * If the payload carries `_style._photo_stickers`, run it through the
     * shared PhotoStickerSanitizer (ownership check, server-derived url,
     * pos preset allowlist, size/rotate/dx/dy clamps, entry cap). All other
     * `_style` keys are left untouched. An empty sanitized result removes
     * the key entirely, matching the web editor's behaviour.
     */
    protected function sanitizeSettingsStickers(array $settings): array
    {
        if (!isset($settings['_style']) || !is_array($settings['_style'])) {
            return $settings;
        }
        if (!array_key_exists('_photo_stickers', $settings['_style'])) {
            return $settings;
        }
        $clean = \App\Modules\User\Support\PhotoStickerSanitizer::sanitize($settings['_style']['_photo_stickers']);
        if ($clean === []) {
            unset($settings['_style']['_photo_stickers']);
        } else {
            $settings['_style']['_photo_stickers'] = $clean;
        }
        return $settings;
    }

    protected function ownedLink(Request $request, int $id): ?Link
    {
        return Link::where('user_id', $request->user()->id)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->find($id);
    }

    protected function transform(BiolinkBlock $b): array
    {
        return [
            'id'         => $b->id,
            'link_id'    => $b->link_id,
            'type'       => $b->type,
            'sort_order' => $b->sort_order,
            'parent_id'  => $b->parent_id,
            'is_active'  => (bool) $b->is_active,
            'settings'   => $b->settings,
            // Task #1094 — scarcity fields. The mobile editor hydrates
            // its limits card from these and PATCHes them back; if they
            // were missing from the response the next save would clear
            // any cap or expiry the creator had previously set.
            'start_date' => optional($b->start_date)->toIso8601String(),
            'end_date'   => optional($b->end_date)->toIso8601String(),
            'max_clicks' => $b->max_clicks,
            'click_count'=> (int) ($b->click_count ?? 0),
            'created_at' => optional($b->created_at)->toIso8601String(),
            'updated_at' => optional($b->updated_at)->toIso8601String(),
        ];
    }
}
