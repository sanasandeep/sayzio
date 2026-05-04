<?php

namespace App\Modules\User\Controllers;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LinkTemplateController extends Controller
{
    public function __construct(private TemplateService $templates) {}

    public function picker(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $user = auth()->user();
        $userPlanSlug = $user->plan?->slug;
        // Show all active templates so users can see what they could unlock,
        // but lock the ones above their tier (badge + upgrade CTA).
        $pageTemplates = PageTemplate::active()->get();
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
            $children = $this->summarizeChildren($t->snapshot['children'] ?? []);
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
     * Build a UI-friendly summary of a card snapshot's children. Each entry
     * carries the friendly type label, an icon, and a short preview of the
     * block's main field (heading text, button label, etc.) when one is
     * available — used by the Card Templates gallery to show what a
     * template will actually insert before the user applies it.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array{type:string,label:string,icon:string,preview:string}>
     */
    private function summarizeChildren(array $children): array
    {
        $types = BiolinkBlock::TYPES;
        $out = [];
        foreach ($children as $child) {
            if (!is_array($child)) continue;
            $type = (string) ($child['type'] ?? '');
            if ($type === '') continue;
            $info = $types[$type] ?? null;
            $label = is_array($info) && isset($info['label'])
                ? (string) $info['label']
                : ucwords(str_replace('_', ' ', $type));
            $icon = is_array($info) && isset($info['icon'])
                ? (string) $info['icon']
                : 'fa-cube';
            $settings = is_array($child['settings'] ?? null) ? $child['settings'] : [];
            $out[] = [
                'type'    => $type,
                'label'   => $label,
                'icon'    => $icon,
                'preview' => $this->previewFromSettings($type, $settings),
            ];
        }
        return $out;
    }

    /**
     * Pick the most "headline-ish" string out of a block's settings so the
     * gallery can render something like 'Heading — "Hello there"' on hover.
     * Returns '' when nothing useful is found; the UI falls back to the
     * type label alone in that case.
     *
     * @param  array<string, mixed>  $settings
     */
    private function previewFromSettings(string $type, array $settings): string
    {
        // Order matters: try the most identifying field first.
        $candidates = ['text', 'heading', 'title', 'name', 'label', 'button_text',
            'placeholder', 'message', 'phone', 'url'];
        foreach ($candidates as $key) {
            $v = $settings[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $clean = trim(preg_replace('/\s+/', ' ', strip_tags($v)) ?? '');
                if ($clean === '') continue;
                return mb_strimwidth($clean, 0, 60, '…');
            }
        }
        // Fallback: list-style blocks expose an items array.
        if (isset($settings['items']) && is_array($settings['items'])) {
            $first = $settings['items'][0] ?? null;
            if (is_string($first) && trim($first) !== '') {
                return mb_strimwidth(trim($first), 0, 60, '…');
            }
            if (is_array($first)) {
                foreach (['text', 'title', 'label', 'name'] as $k) {
                    if (isset($first[$k]) && is_string($first[$k]) && trim($first[$k]) !== '') {
                        return mb_strimwidth(trim($first[$k]), 0, 60, '…');
                    }
                }
            }
        }
        return '';
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
