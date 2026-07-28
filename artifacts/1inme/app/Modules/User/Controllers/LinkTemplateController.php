<?php

namespace App\Modules\User\Controllers;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\TemplateContentSummarizer;
use App\Modules\User\Services\TemplatePreviewLayoutBuilder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LinkTemplateController extends Controller
{
    /**
     * Cards rendered server-side on the picker's first paint; the rest of
     * the library streams in via pickerChunk() so the initial HTML stays
     * small (~400 templates at once was ~5.3MB and a 20-30s render).
     */
    public const PICKER_CHUNK = 24;

    /** Memoized Plan sort-order ranks (isLocked runs once per card). */
    private $planRanks = null;

    public function __construct(
        private TemplateService $templates,
        private TemplateContentSummarizer $summarizer,
        private TemplatePreviewLayoutBuilder $previewLayout,
    ) {}

    /**
     * Lightweight ordered index of every active template — no snapshot
     * column (snapshots dominate the payload) — sorted by the persona
     * recommendation flag so recommended templates come first. Ordering is
     * deterministic (recommended flag, then sort_order, then id) so the
     * initial page and later chunks always agree on positions.
     */
    private function templateIndex(?string $persona)
    {
        $index = PageTemplate::active()
            ->select(['id', 'name', 'slug', 'description', 'category', 'plan_tier', 'thumbnail_url', 'recommended_personas', 'sort_order'])
            ->orderBy('sort_order')->orderBy('id')
            ->get();
        if ($persona) {
            $index = $index->sortByDesc(function ($t) use ($persona) {
                $tags = $t->recommended_personas ?? [];
                return is_array($tags) && in_array($persona, $tags, true) ? 1 : 0;
            })->values();
        }
        return $index;
    }

    /** Resolve the persona used for ordering (?persona= override wins). */
    private function resolvePersona(Request $request): ?string
    {
        $personaParam = $request->query('persona');
        $allowed = \App\Modules\User\Services\PersonaCatalog::slugs();
        return (is_string($personaParam) && in_array($personaParam, $allowed, true))
            ? $personaParam
            : auth()->user()->persona;
    }

    /**
     * Load the full rows (with snapshots) for a slice of the index and
     * decorate each with the "what's inside" summary + mini blueprint.
     */
    private function decorateSlice($index, int $offset, int $limit)
    {
        $ids = $index->slice($offset, $limit)->pluck('id')->all();
        if (empty($ids)) return collect();
        $rows = PageTemplate::whereIn('id', $ids)->get()->keyBy('id');
        return collect($ids)->map(fn($id) => $rows->get($id))->filter()->values()
            ->each(function ($t) {
                $blocks = is_array($t->snapshot['blocks'] ?? null) ? $t->snapshot['blocks'] : [];
                $t->setAttribute('content_summary', $this->summarizer->summarizePageBlocks($blocks));
                $t->setAttribute('preview_layout', $this->previewLayout->build($blocks));
            });
    }

    public function picker(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || !$link->isBiolinkFamily(), 403);
        $user = auth()->user();
        $userPlanSlug = $user->plan?->slug;
        // Persona for ordering: explicit ?persona= override (validated) wins
        // over the user's saved persona so admins/links/share-flows can preview
        // the "best for X" set without changing the user's stored preference.
        $persona = $this->resolvePersona($request);
        // Show all active templates so users can see what they could unlock,
        // but lock the ones above their tier (badge + upgrade CTA). Only the
        // first chunk is rendered server-side; the rest streams in.
        $pageTemplates = $this->templateIndex($persona);
        $initialTemplates = $this->decorateSlice($pageTemplates, 0, self::PICKER_CHUNK);
        $hasRecommended = $persona && $pageTemplates->contains(function ($t) use ($persona) {
            $tags = $t->recommended_personas ?? [];
            return is_array($tags) && in_array($persona, $tags, true);
        });
        $personaLabel = \App\Modules\User\Services\PersonaCatalog::pluralLabelFor($persona);
        $lockedFn = fn(?string $required) => $this->isLocked($required, $userPlanSlug);
        $chunkSize = self::PICKER_CHUNK;
        return view('user.links.templates.picker', compact('link', 'pageTemplates', 'initialTemplates', 'chunkSize', 'userPlanSlug', 'lockedFn', 'persona', 'personaLabel', 'hasRecommended'));
    }

    /**
     * JSON endpoint streaming the next chunk of picker cards as rendered
     * HTML. The client appends the HTML into the (Alpine-scoped) grid, so
     * the cards keep the same x-show filter wiring as server-rendered ones.
     */
    public function pickerChunk(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || !$link->isBiolinkFamily(), 403);
        $userPlanSlug = auth()->user()->plan?->slug;
        $persona = $this->resolvePersona($request);
        $offset = max(0, (int) $request->query('offset', 0));

        $index = $this->templateIndex($persona);
        $slice = $this->decorateSlice($index, $offset, self::PICKER_CHUNK);
        $hasBlocks = $link->biolinkBlocks()->exists();

        $html = '';
        foreach ($slice as $tpl) {
            $html .= view('user.links.templates._card', [
                'tpl' => $tpl,
                'link' => $link,
                'locked' => $this->isLocked($tpl->plan_tier, $userPlanSlug),
                'hasBlocks' => $hasBlocks,
            ])->render();
        }

        $next = $offset + $slice->count();
        return response()->json([
            'html' => $html,
            'next_offset' => $next < $index->count() ? $next : null,
            'total' => $index->count(),
        ]);
    }

    public function cardGallery(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || !$link->isBiolinkFamily(), 403);
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
                'preview_layout' => $this->previewLayout->build($rawChildren, 10),
            ];
        });

        return response()->json([
            'items' => $cards,
            'categories' => $categories,
        ]);
    }

    public function applyPage(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || !$link->isBiolinkFamily(), 403);
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

        $this->templates->applyPageToLink($link, $tpl->snapshot, /*replace*/ true, $tpl);

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', 'Template "' . $tpl->name . '" applied.');
    }

    /**
     * "Detach from template" — clears the design-lock stamp so the page
     * keeps its current look but the creator regains every styling surface
     * (appearance, per-block styles, variants, block theme, custom CSS/JS).
     */
    public function detachDesign(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || !$link->isBiolinkFamily(), 403);

        $settings = $link->settings ?? [];
        unset($settings['biolink']['design_locked']);
        $link->settings = $settings;
        $link->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Detached from template — full design controls are unlocked.');
    }

    public function applyCard(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || !$link->isBiolinkFamily(), 403);
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
            $block->load('children');
            $html = view('user.links.partials.block-card', [
                'block'       => $block,
                'link'        => $link,
                'blockTypes'  => BiolinkBlock::TYPES,
                'catColors'   => BiolinkBlock::CATEGORY_COLORS,
                'pollTallies' => [],
            ])->render();

            return response()->json([
                'success'      => true,
                'block_id'     => $block->id,
                'html'         => $html,
                'insert_after' => $validated['insert_after'] ?? null,
            ]);
        }
        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Card template added.');
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
        // Memoized: this runs once per rendered card, and each pluck() was a
        // round-trip to the (distant) database.
        $ranks = $this->planRanks ??= \App\Modules\Admin\Models\Plan::pluck('sort_order', 'slug');
        $req = $ranks[$required] ?? PHP_INT_MAX;
        $cur = $userPlan ? ($ranks[$userPlan] ?? -1) : -1;
        return $cur < $req;
    }
}
