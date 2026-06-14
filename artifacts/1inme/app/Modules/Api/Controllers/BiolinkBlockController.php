<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
        if (!$link) return $this->notFound('Biolink not found');

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

    public function store(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $data = $request->validate([
            'type'       => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer'],
            'parent_id'  => ['nullable', 'integer'],
            'is_active'  => ['nullable', 'boolean'],
            'settings'   => ['nullable', 'array'],
        ]);
        $sort = $data['sort_order'] ?? ((int) BiolinkBlock::where('link_id', $link->id)->max('sort_order') + 1);
        $b = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => $data['type'],
            'sort_order' => $sort,
            'parent_id'  => $data['parent_id'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
            'settings'   => $data['settings'] ?? [],
        ]);
        return $this->created(['block' => $this->transform($b)]);
    }

    public function update(Request $request, int $linkId, int $id)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

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
        $link = Link::where('alias', $alias)->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->first();
        // Mirror BiolinkController@show: missing or disabled links are
        // 404 (don't leak existence), and accessibility (paywall, expiry,
        // password-protected, etc.) is enforced before we touch blocks.
        if (!$link || !$link->is_active) return $this->notFound('Biolink not found');
        if (!$link->isAccessible())     return $this->notFound('Biolink not available');

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
        if (!$allowed) return $this->notFound('Biolink not found');

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

    public function destroy(Request $request, int $linkId, int $id)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $b = BiolinkBlock::where('link_id', $link->id)->find($id);
        if (!$b) return $this->notFound('Block not found');
        $b->delete();
        return $this->noContent();
    }

    public function reorder(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $data = $request->validate([
            'order'        => ['required', 'array', 'min:1'],
            'order.*'      => ['integer'],
        ]);
        foreach ($data['order'] as $i => $blockId) {
            BiolinkBlock::where('link_id', $link->id)->where('id', $blockId)->update(['sort_order' => $i]);
        }
        return $this->ok(['reordered' => true]);
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
