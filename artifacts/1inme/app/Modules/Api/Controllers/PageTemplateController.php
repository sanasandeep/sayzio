<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\PersonaCatalog;
use App\Modules\User\Services\TemplateContentSummarizer;
use App\Modules\User\Services\TemplatePreviewLayoutBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for the web's `LinkTemplateController@picker` / `applyPage`.
 * Surfaces the full-page starter/persona biolink templates (categories,
 * thumbnails, a shape-aware mini-blueprint, a "what's inside" page summary,
 * plan-tier lock, and persona-aware ordering) over /api/v1 so the Expo
 * editor can browse and apply a complete redesigned page without the web's
 * session cookie.
 *
 * Unlike card templates (which insert a single grouped sub-tree), applying a
 * page template REPLACES the link's blocks, so `apply` honours the same
 * overwrite confirmation guard the web flow uses.
 */
class PageTemplateController extends Controller
{
    public function __construct(
        private TemplateService $templates,
        private TemplateContentSummarizer $summarizer,
        private TemplatePreviewLayoutBuilder $previewLayout,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $link = Link::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        abort_if(!$link->isBiolinkFamily(), 403);

        $user = auth()->user();
        $userPlanSlug = $user?->plan?->slug;
        $categories = PageTemplate::categories();
        $catFilter = (string) $request->query('category', '');
        $q = trim((string) $request->query('q', ''));

        // Persona for ordering: an explicit (validated) ?persona= override
        // wins over the user's saved persona so admins/share-flows can preview
        // the "best for X" set without changing the user's stored preference.
        $personaParam = $request->query('persona');
        $allowed = PersonaCatalog::slugs();
        $persona = (is_string($personaParam) && in_array($personaParam, $allowed, true))
            ? $personaParam
            : $user?->persona;

        $query = PageTemplate::active();
        if ($catFilter !== '' && $catFilter !== 'all' && array_key_exists($catFilter, $categories)) {
            $query->where('category', $catFilter);
        }
        if ($q !== '') {
            $needle = '%' . $q . '%';
            $query->where(function ($w) use ($needle) {
                $w->where('name', 'ilike', $needle)
                  ->orWhere('description', 'ilike', $needle);
            });
        }

        $templates = $query->get();

        // Show all active templates so users can see what they could unlock,
        // but order persona-recommended ones first (mirrors the web picker).
        if ($persona) {
            $templates = $templates->sortByDesc(function ($t) use ($persona) {
                $tags = $t->recommended_personas ?? [];
                return is_array($tags) && in_array($persona, $tags, true) ? 1 : 0;
            })->values();
        }

        $items = $templates->map(function (PageTemplate $t) use ($userPlanSlug, $categories, $persona) {
            $blocks = is_array($t->snapshot['blocks'] ?? null) ? $t->snapshot['blocks'] : [];
            $summary = $this->summarizer->summarizePageBlocks($blocks);
            $tags = $t->recommended_personas ?? [];
            return [
                'id'             => $t->id,
                'name'           => $t->name,
                'category'       => $t->category,
                'category_label' => $categories[$t->category] ?? ucfirst((string) $t->category),
                'description'    => $t->description,
                'thumbnail_url'  => $t->thumbnail_url,
                'plan_tier'      => $t->plan_tier,
                'locked'         => $this->isLocked($t->plan_tier, $userPlanSlug),
                'recommended'    => $persona && is_array($tags) && in_array($persona, $tags, true),
                'blocks_count'   => count($summary),
                'content'        => $summary,
                // Same shape-aware blueprint the web picker renders so the
                // mobile gallery can draw a recognisable mock of the page
                // (avatar, profile card, buttons, faq rows, …) at thumbnail
                // size when no static thumbnail_url is set. Built from the
                // raw snapshot so web and mobile share one source of truth.
                'preview_layout' => $this->previewLayout->build($blocks),
            ];
        })->values();

        return response()->json([
            'data' => [
                'items'      => $items,
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Return the full, sanitized block tree for a single page template so the
     * mobile editor can render a true visual preview with its native block
     * renderer *before* the user commits to replacing their page.
     *
     * No DB writes: blocks are built off the stored snapshot via
     * TemplateService::buildPreviewLink (the same sanitizer applyPage uses),
     * then flattened to the same {id,type,sort_order,parent_id,settings} shape
     * the public biolink payload returns, so the renderer needs no special
     * casing. Only active blocks are emitted — inactive ones won't appear on
     * the live page, so they shouldn't appear in the preview either.
     */
    public function show(Request $request, int $id, int $template): JsonResponse
    {
        $link = Link::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        abort_if(!$link->isBiolinkFamily(), 403);

        $tpl = PageTemplate::active()->where('id', $template)->firstOrFail();

        $user = auth()->user();
        $userPlanSlug = $user?->plan?->slug;
        $categories = PageTemplate::categories();

        $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
        $previewLink = $this->templates->buildPreviewLink($snapshot, $user, (string) $tpl->name);

        $blocks = $previewLink->previewBlocks
            ->filter(fn ($b) => (bool) $b->is_active)
            ->map(fn ($b) => [
                'id'         => $b->id,
                'type'       => $b->type,
                'sort_order' => $b->sort_order,
                'parent_id'  => $b->parent_id,
                'settings'   => $b->settings,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'id'             => $tpl->id,
                'name'           => $tpl->name,
                'category'       => $tpl->category,
                'category_label' => $categories[$tpl->category] ?? ucfirst((string) $tpl->category),
                'description'    => $tpl->description,
                'plan_tier'      => $tpl->plan_tier,
                'locked'         => $this->isLocked($tpl->plan_tier, $userPlanSlug),
                'biolink'        => (array) ($snapshot['biolink'] ?? []),
                'blocks'         => $blocks,
            ],
        ]);
    }

    public function apply(Request $request, int $id): JsonResponse
    {
        $link = Link::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        abort_if(!$link->isBiolinkFamily(), 403);

        $validated = $request->validate([
            'template_id'       => 'required|integer|exists:page_templates,id',
            'confirm_overwrite' => 'nullable|boolean',
        ]);

        $tpl = PageTemplate::active()->where('id', $validated['template_id'])->firstOrFail();
        $userPlanSlug = auth()->user()?->plan?->slug;
        if ($this->isLocked($tpl->plan_tier, $userPlanSlug)) {
            return response()->json([
                'error' => [
                    'code'    => 'plan_required',
                    'message' => 'This template requires a higher plan.',
                ],
            ], 403);
        }

        // Applying a page template replaces the link's existing blocks. Guard
        // against accidental data loss: if the link already has blocks, the
        // client must opt in by passing confirm_overwrite (mirrors the web
        // JS confirm), otherwise we surface a 409 the editor can prompt on.
        $hasBlocks = $link->biolinkBlocks()->exists();
        if ($hasBlocks && empty($validated['confirm_overwrite'])) {
            return response()->json([
                'error' => [
                    'code'    => 'confirm_overwrite',
                    'message' => 'Applying this template will replace your existing blocks.',
                ],
            ], 409);
        }

        $this->templates->applyPageToLink($link, $tpl->snapshot, /*replace*/ true);

        // Return the full freshly-created block tree (parents first, then
        // their children by sort_order) so the mobile editor can swap its
        // list in place instead of refetching everything.
        $tree = BiolinkBlock::where('link_id', $link->id)
            ->orderByRaw('(parent_id is not null) asc, sort_order asc')
            ->get();

        return response()->json([
            'data' => [
                'blocks' => $tree->map(fn ($b) => $this->serializeBlock($b))->all(),
            ],
        ]);
    }

    /**
     * Serialize a block into the same shape the blocks index endpoint
     * (BiolinkBlockController@transform) returns, so the mobile editor can
     * fold the applied tree straight into its `blocks` cache.
     */
    private function serializeBlock(BiolinkBlock $b): array
    {
        return [
            'id'          => $b->id,
            'link_id'     => $b->link_id,
            'type'        => $b->type,
            'sort_order'  => $b->sort_order,
            'parent_id'   => $b->parent_id,
            'is_active'   => (bool) $b->is_active,
            'settings'    => $b->settings,
            'start_date'  => optional($b->start_date)->toIso8601String(),
            'end_date'    => optional($b->end_date)->toIso8601String(),
            'max_clicks'  => $b->max_clicks,
            'click_count' => (int) ($b->click_count ?? 0),
            'created_at'  => optional($b->created_at)->toIso8601String(),
            'updated_at'  => optional($b->updated_at)->toIso8601String(),
        ];
    }

    private function isLocked(?string $required, ?string $userPlan): bool
    {
        if (empty($required)) return false;
        $ranks = Plan::pluck('sort_order', 'slug');
        $req = $ranks[$required] ?? PHP_INT_MAX;
        $cur = $userPlan ? ($ranks[$userPlan] ?? -1) : -1;
        return $cur < $req;
    }
}
