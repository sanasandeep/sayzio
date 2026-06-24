<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;

/**
 * Shared "pick a starting design" list for the guided biolink wizard.
 *
 * Surfaces persona-tagged page templates for the wizard's starting-design step
 * so the web wizard (User\Controllers\BiolinkWizardController) and the mobile
 * API wizard (Api\Controllers\BiolinkWizardController) show the exact same set,
 * ordering and lock state. Reuses TemplateContentSummarizer +
 * TemplatePreviewLayoutBuilder (the same helpers the in-editor template picker
 * uses) so the cards render identically.
 *
 * Filtering: templates whose `recommended_personas` contains the chosen persona
 * are shown. If none match (or no persona is known) it falls back to all active
 * templates so the step is never empty — "Start from scratch" is always offered
 * separately by the callers.
 */
class WizardStartingDesignService
{
    public function __construct(
        private TemplateContentSummarizer $summarizer,
        private TemplatePreviewLayoutBuilder $previewLayout,
    ) {}

    /**
     * Build the starting-design list for a persona.
     *
     * @return array<int, array{
     *   id:int,name:string,category:string,category_label:string,description:string,
     *   thumbnail_url:?string,plan_tier:?string,locked:bool,recommended:bool,
     *   blocks_count:int,content_summary:array,preview_layout:array
     * }>
     */
    public function forPersona(?string $persona, ?User $user, ?string $q = null): array
    {
        $userPlanSlug = $user?->plan?->slug;
        $templates = PageTemplate::active()->get();

        // Optional free-text filter (name/description), case-insensitive.
        $needle = is_string($q) ? trim($q) : '';
        if ($needle !== '') {
            $lc = mb_strtolower($needle);
            $templates = $templates->filter(function ($t) use ($lc) {
                return str_contains(mb_strtolower((string) $t->name), $lc)
                    || str_contains(mb_strtolower((string) ($t->description ?? '')), $lc);
            })->values();
        }

        $isRecommended = function ($t) use ($persona): bool {
            if (!$persona) {
                return false;
            }
            $tags = $t->recommended_personas ?? [];
            return is_array($tags) && in_array($persona, $tags, true);
        };

        // Filter to persona-tagged templates; fall back to all active when the
        // persona has no recommendations so the step always has options.
        if ($persona) {
            $recommended = $templates->filter($isRecommended)->values();
            if ($recommended->isNotEmpty()) {
                $templates = $recommended;
            }
        }

        $ranks = Plan::pluck('sort_order', 'slug');
        $labels = method_exists(PageTemplate::class, 'categories') ? PageTemplate::categories() : [];

        return $templates->map(function ($t) use ($isRecommended, $userPlanSlug, $ranks, $labels) {
            $blocks = is_array($t->snapshot['blocks'] ?? null) ? $t->snapshot['blocks'] : [];
            $category = (string) ($t->category ?? '');
            return [
                'id'              => (int) $t->id,
                'name'            => (string) $t->name,
                'category'        => $category,
                'category_label'  => $labels[$category] ?? ($category !== '' ? ucfirst($category) : 'General'),
                'description'     => (string) ($t->description ?? ''),
                'thumbnail_url'   => $t->thumbnail_url,
                'plan_tier'       => $t->plan_tier,
                'locked'          => $this->isLocked($t->plan_tier, $userPlanSlug, $ranks),
                'recommended'     => $isRecommended($t),
                'blocks_count'    => count($blocks),
                'content_summary' => $this->summarizer->summarizePageBlocks($blocks),
                'preview_layout'  => $this->previewLayout->build($blocks),
            ];
        })->all();
    }

    /**
     * Locked = template requires a plan tier above the user's current plan.
     * Mirrors LinkTemplateController::isLocked (Plan::sort_order comparison).
     *
     * @param \Illuminate\Support\Collection<string,int>|null $ranks
     */
    public function isLocked(?string $required, ?string $userPlan, $ranks = null): bool
    {
        if (empty($required)) {
            return false;
        }
        $ranks ??= Plan::pluck('sort_order', 'slug');
        $req = $ranks[$required] ?? PHP_INT_MAX;
        $cur = $userPlan ? ($ranks[$userPlan] ?? -1) : -1;
        return $cur < $req;
    }
}
