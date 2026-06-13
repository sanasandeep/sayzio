<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\TemplateContentSummarizer;
use App\Modules\User\Services\TemplatePreviewLayoutBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for the web's `LinkTemplateController@cardGallery` /
 * `applyCard`. Surfaces the same card-template gallery (categories,
 * thumbnails, "what's inside" summary, plan-tier lock) over /api/v1
 * so the mobile editor can browse and insert card templates without
 * needing the web's session cookie.
 */
class CardTemplateController extends Controller
{
    public function __construct(
        private TemplateService $templates,
        private TemplateContentSummarizer $summarizer,
        private TemplatePreviewLayoutBuilder $previewLayout,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $link = Link::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        abort_if($link->type !== 'biolink', 403);

        $userPlanSlug = auth()->user()?->plan?->slug;
        $categories = CardTemplate::categories();
        $catFilter = (string) $request->query('category', '');
        $q = trim((string) $request->query('q', ''));

        $query = CardTemplate::active();
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

        $items = $query->get()->map(function (CardTemplate $t) use ($userPlanSlug, $categories) {
            $rawChildren = is_array($t->snapshot['children'] ?? null) ? $t->snapshot['children'] : [];
            $children = $this->summarizer->summarizeChildren($rawChildren);
            return [
                'id'             => $t->id,
                'name'           => $t->name,
                'category'       => $t->category,
                'category_label' => $categories[$t->category] ?? ucfirst($t->category),
                'description'    => $t->description,
                'thumbnail_url'  => $t->thumbnail_url,
                'plan_tier'      => $t->plan_tier,
                'locked'         => $this->isLocked($t->plan_tier, $userPlanSlug),
                'children_count' => count($children),
                'children'       => $children,
                // Same shape-aware blueprint the web gallery renders so the
                // mobile picker can draw a recognisable mock of the card
                // (avatar circle, pill buttons, social dots, stacked input
                // lines, etc.) at thumbnail size when no static
                // thumbnail_url is set. Built from the raw snapshot so web
                // and mobile share one source of truth.
                'preview_layout' => $this->previewLayout->build($rawChildren),
            ];
        });

        return response()->json([
            'data' => [
                'items'      => $items,
                'categories' => $categories,
            ],
        ]);
    }

    public function apply(Request $request, int $id): JsonResponse
    {
        $link = Link::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        abort_if($link->type !== 'biolink', 403);

        $validated = $request->validate([
            'template_id'  => 'required|integer|exists:card_templates,id',
            'insert_after' => 'nullable|integer|exists:biolink_blocks,id',
            'tab_id'       => 'nullable|string|max:64',
        ]);

        $tpl = CardTemplate::active()->where('id', $validated['template_id'])->firstOrFail();
        $userPlanSlug = auth()->user()?->plan?->slug;
        if ($this->isLocked($tpl->plan_tier, $userPlanSlug)) {
            return response()->json([
                'error' => [
                    'code'    => 'plan_required',
                    'message' => 'This template requires a higher plan.',
                ],
            ], 403);
        }

        $tabId = $request->has('tab_id') ? ($validated['tab_id'] ?? '') : null;
        $block = $this->templates->applyCardToLink(
            $link,
            $tpl->snapshot,
            $validated['insert_after'] ?? null,
            $tabId,
        );

        // Return the full freshly-created sub-tree (parent card first, then
        // its children) so the mobile editor can patch its list in place
        // instead of refetching everything. The parent has a null parent_id
        // so it sorts ahead of its children; children follow by sort_order.
        $tree = BiolinkBlock::where('link_id', $link->id)
            ->where(function ($w) use ($block) {
                $w->where('id', $block->id)->orWhere('parent_id', $block->id);
            })
            ->orderByRaw('(parent_id is not null) asc, sort_order asc')
            ->get();

        return response()->json([
            'data' => [
                'block_id' => $block->id,
                'blocks'   => $tree->map(fn ($b) => $this->serializeBlock($b))->all(),
            ],
        ]);
    }

    /**
     * Serialize a block into the same shape the blocks index endpoint
     * (BiolinkBlockController@transform) returns, so the mobile editor can
     * fold the applied sub-tree straight into its `blocks` cache.
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
