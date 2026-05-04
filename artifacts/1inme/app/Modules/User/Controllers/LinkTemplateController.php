<?php

namespace App\Modules\User\Controllers;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\TemplateContentSummarizer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LinkTemplateController extends Controller
{
    public function __construct(
        private TemplateService $templates,
        private TemplateContentSummarizer $summarizer,
    ) {}

    public function picker(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $user = auth()->user();
        $userPlanSlug = $user->plan?->slug;
        // Show all active templates so users can see what they could unlock,
        // but lock the ones above their tier (badge + upgrade CTA).
        $pageTemplates = PageTemplate::active()->get();
        // Attach a UI-friendly "what's inside" summary to each template so
        // the picker can show top-level cards/blocks (and the children
        // inside each card) before the user applies and overwrites the link.
        $pageTemplates->each(function ($t) {
            $blocks = is_array($t->snapshot['blocks'] ?? null) ? $t->snapshot['blocks'] : [];
            $t->setAttribute('content_summary', $this->summarizer->summarizePageBlocks($blocks));
        });
        // Persona for ordering: explicit ?persona= override (validated) wins
        // over the user's saved persona so admins/links/share-flows can preview
        // the "best for X" set without changing the user's stored preference.
        $personaParam = $request->query('persona');
        $allowed = \App\Modules\User\Services\PersonaCatalog::slugs();
        $persona = (is_string($personaParam) && in_array($personaParam, $allowed, true))
            ? $personaParam
            : $user->persona;
        if ($persona) {
            $pageTemplates = $pageTemplates->sortByDesc(function ($t) use ($persona) {
                $tags = $t->recommended_personas ?? [];
                return is_array($tags) && in_array($persona, $tags, true) ? 1 : 0;
            })->values();
        }
        $hasRecommended = $persona && $pageTemplates->contains(function ($t) use ($persona) {
            $tags = $t->recommended_personas ?? [];
            return is_array($tags) && in_array($persona, $tags, true);
        });
        $personaLabel = \App\Modules\User\Services\PersonaCatalog::pluralLabelFor($persona);
        $lockedFn = fn(?string $required) => $this->isLocked($required, $userPlanSlug);
        return view('user.links.templates.picker', compact('link', 'pageTemplates', 'userPlanSlug', 'lockedFn', 'persona', 'personaLabel', 'hasRecommended'));
    }

    public function cardGallery(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $userPlanSlug = auth()->user()->plan?->slug;

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

        $cards = $query->get()->map(function ($t) use ($userPlanSlug, $categories) {
            $rawChildren = is_array($t->snapshot['children'] ?? null) ? $t->snapshot['children'] : [];
            $children = $this->summarizer->summarizeChildren($rawChildren);
            return [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category,
                'category_label' => $categories[$t->category] ?? ucfirst($t->category),
                'description' => $t->description,
                'thumbnail_url' => $t->thumbnail_url,
                'plan_tier' => $t->plan_tier,
                'locked' => $this->isLocked($t->plan_tier, $userPlanSlug),
                'children_count' => count($children),
                'children' => $children,
                // Tiny visual blueprint of the card layout: rows of cells laid
                // out on the same 12-col grid the real card uses, each cell
                // carrying a height/color/icon hint per block type. The
                // gallery renders this as a thumbnail when no static
                // thumbnail_url is set, falling back to a generic icon if
                // the snapshot has no usable children.
                'preview_layout' => $this->buildPreviewLayout($rawChildren),
            ];
        });

        return response()->json([
            'items' => $cards,
            'categories' => $categories,
        ]);
    }

    public function applyPage(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $validated = $request->validate([
            'template_id' => 'required|integer|exists:page_templates,id',
            'confirm_overwrite' => 'nullable|boolean',
        ]);
        $tpl = PageTemplate::active()->where('id', $validated['template_id'])->firstOrFail();
        $userPlanSlug = auth()->user()->plan?->slug;
        if ($this->isLocked($tpl->plan_tier, $userPlanSlug)) {
            return back()->with('error', 'This template requires a higher plan.');
        }

        // Server-side overwrite guard: if the link already has any blocks,
        // require explicit confirmation (UI sets the flag from a JS confirm).
        $hasBlocks = $link->biolinkBlocks()->exists();
        if ($hasBlocks && empty($validated['confirm_overwrite'])) {
            return back()->with('error', 'Applying this template will replace your existing blocks. Confirm to proceed.');
        }

        $this->templates->applyPageToLink($link, $tpl->snapshot, /*replace*/ true);

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', 'Template "' . $tpl->name . '" applied.');
    }

    public function applyCard(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $validated = $request->validate([
            'template_id' => 'required|integer|exists:card_templates,id',
            'insert_after' => 'nullable|integer|exists:biolink_blocks,id',
            'tab_id' => 'nullable|string|max:64',
        ]);
        $tpl = CardTemplate::active()->where('id', $validated['template_id'])->firstOrFail();
        $userPlanSlug = auth()->user()->plan?->slug;
        if ($this->isLocked($tpl->plan_tier, $userPlanSlug)) {
            return response()->json(['success' => false, 'error' => 'This template requires a higher plan.'], 403);
        }

        // tab_id present in request (even empty) opts into tab-aware insertion;
        // absent means honor any _tab_id baked into the admin snapshot.
        $tabId = $request->has('tab_id') ? ($validated['tab_id'] ?? '') : null;
        $block = $this->templates->applyCardToLink($link, $tpl->snapshot, $validated['insert_after'] ?? null, $tabId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'block_id' => $block->id]);
        }
        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Card template added.');
    }

    /**
     * Build a tiny visual blueprint of the card snapshot's children, laid
     * out on the same 12-col grid the real card renderer uses. Cells are
     * grouped into rows that respect grid_span, so a card with two 6-col
     * children renders as a single row of two cells. Each cell carries a
     * type-specific background, height hint and icon — enough to convey
     * column count, image position and button style at thumbnail size
     * without invoking the full block renderer. Capped at 6 rows so the
     * preview never overflows the gallery card.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<int, array{span:int,bg:string,h:int,icon:string}>>
     */
    private function buildPreviewLayout(array $children): array
    {
        $rows = [];
        $current = [];
        $used = 0;
        foreach ($children as $child) {
            if (!is_array($child)) continue;
            $type = (string) ($child['type'] ?? '');
            if ($type === '') continue;
            $settings = is_array($child['settings'] ?? null) ? $child['settings'] : [];
            $span = (int) ($settings['_style']['grid_span'] ?? 12);
            $span = max(1, min(12, $span));
            $cell = $this->previewCellFor($type) + ['span' => $span];
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
     * Visual hints (background, height in px, icon) for a single block type
     * in the mini preview. Unknown types fall back to a neutral pill so the
     * preview never crashes on a future block type — the gallery itself
     * still falls back to a generic icon if no rows are produced at all.
     *
     * @return array{bg:string,h:int,icon:string}
     */
    private function previewCellFor(string $type): array
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
        ];
        return $palette[$type] ?? ['bg' => 'rgba(255,255,255,0.08)', 'h' => 12, 'icon' => 'fa-cube'];
    }

    /**
     * Locked = template requires a plan tier above the user's current plan.
     * Empty plan_tier = available to all. Comparison is by Plan::sort_order
     * (lower sort_order = lower tier). Higher-tier users automatically get
     * access to lower-tier templates.
     */
    public function isLocked(?string $required, ?string $userPlan): bool
    {
        if (empty($required)) return false;
        $ranks = \App\Modules\Admin\Models\Plan::pluck('sort_order', 'slug');
        $req = $ranks[$required] ?? PHP_INT_MAX;
        $cur = $userPlan ? ($ranks[$userPlan] ?? -1) : -1;
        return $cur < $req;
    }
}
