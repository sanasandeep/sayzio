<?php

namespace App\Modules\User\Controllers;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LinkTemplateController extends Controller
{
    public function __construct(private TemplateService $templates) {}

    public function picker(Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $user = auth()->user();
        $userPlanSlug = $user->plan?->slug;
        // Show all active templates so users can see what they could unlock,
        // but lock the ones above their tier (badge + upgrade CTA).
        $pageTemplates = PageTemplate::active()->get();
        // Float persona-recommended templates to the top of the grid so the
        // user sees the "best for you" set first without filtering anything out.
        $persona = $user->persona;
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
        $personaLabel = \App\Modules\User\Services\PersonaCatalog::labelFor($persona);
        $lockedFn = fn(?string $required) => $this->isLocked($required, $userPlanSlug);
        return view('user.links.templates.picker', compact('link', 'pageTemplates', 'userPlanSlug', 'lockedFn', 'persona', 'personaLabel', 'hasRecommended'));
    }

    public function cardGallery(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $userPlanSlug = auth()->user()->plan?->slug;
        $cards = CardTemplate::active()->get()->map(function ($t) use ($userPlanSlug) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category,
                'description' => $t->description,
                'thumbnail_url' => $t->thumbnail_url,
                'plan_tier' => $t->plan_tier,
                'locked' => $this->isLocked($t->plan_tier, $userPlanSlug),
                'children_count' => count($t->snapshot['children'] ?? []),
            ];
        });
        return response()->json(['items' => $cards]);
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
