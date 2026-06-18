<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\BiolinkWizardGenerator;
use App\Modules\User\Services\BiolinkWizardQuestions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Mobile (Sanctum) parity for the guided Link-in-Bio wizard.
 *
 * The web wizard (user.links.wizard.*) keeps per-(user, workspace) draft rows
 * so a browser tab can resume later. Mobile is stateless instead: the client
 * drives all four steps (category → page type → optional industry → Q&A) in
 * memory and POSTs every answer at once to /generate. All three endpoints
 * reuse the exact same services as the web flow — BiolinkWizardQuestions for
 * the taxonomy/questions and BiolinkWizardGenerator (BiolinkPageRecipes +
 * TemplateService) for the page generation — so the two surfaces never drift.
 */
class BiolinkWizardController extends Controller
{
    use ApiResponses;

    public function __construct(private BiolinkWizardGenerator $generator) {}

    /**
     * Steps 1–3 in one shot: every category, the page types under each, and
     * the industry options for the combos that have an industry sub-step. The
     * client caches this and drives the first three steps without round-trips.
     */
    public function taxonomy()
    {
        $pageTypes  = [];
        $industries = [];

        foreach (BiolinkWizardQuestions::categories() as $cat) {
            $slug = $cat['slug'];
            $pageTypes[$slug] = BiolinkWizardQuestions::pageTypes($slug);

            foreach ($pageTypes[$slug] as $pt) {
                $ind = BiolinkWizardQuestions::industries($slug, $pt['slug']);
                if (!empty($ind)) {
                    $industries["{$slug}.{$pt['slug']}"] = $ind;
                }
            }
        }

        return $this->ok([
            'categories' => BiolinkWizardQuestions::categories(),
            'page_types' => $pageTypes,
            'industries' => $industries,
        ]);
    }

    /** Step 4: the detailed question set for a chosen (category, page_type, industry). */
    public function questions(Request $request)
    {
        $category = (string) $request->query('category', '');
        $pageType = (string) $request->query('page_type', '');
        $industry = $request->query('industry');
        $industry = is_string($industry) && $industry !== '' ? $industry : null;

        if (!$this->isValidCategory($category)) {
            return $this->fail('Invalid category.', 422, 'invalid_category');
        }
        if (!$this->isValidPageType($category, $pageType)) {
            return $this->fail('Invalid page type.', 422, 'invalid_page_type');
        }

        return $this->ok([
            'questions'         => array_values(BiolinkWizardQuestions::questions($category, $pageType, $industry)),
            'has_industry_step' => BiolinkWizardQuestions::hasIndustryStep($category, $pageType),
        ]);
    }

    /** Generate the biolink page from the collected answers. */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'category'  => ['required', 'string'],
            'page_type' => ['required', 'string'],
            'industry'  => ['nullable', 'string'],
            'answers'   => ['required', 'array'],
        ]);

        $category = $data['category'];
        $pageType = $data['page_type'];

        if (!$this->isValidCategory($category)) {
            return $this->fail('Invalid category.', 422, 'invalid_category');
        }
        if (!$this->isValidPageType($category, $pageType)) {
            return $this->fail('Invalid page type.', 422, 'invalid_page_type');
        }

        // Industry is only meaningful when the combo has an industry sub-step;
        // otherwise it's coerced to null so the recipe ignores it.
        $industrySlugs = array_column(BiolinkWizardQuestions::industries($category, $pageType), 'slug');
        $industry = $data['industry'] ?? null;
        if (!empty($industrySlugs)) {
            if ($industry !== null && !in_array($industry, $industrySlugs, true)) {
                return $this->fail('Invalid industry.', 422, 'invalid_industry');
            }
        } else {
            $industry = null;
        }

        $answers = BiolinkWizardQuestions::sanitizeAnswers($category, $pageType, $industry, $data['answers']);

        if (!BiolinkWizardQuestions::hasName($answers)) {
            return $this->fail('Please fill in at least the name field before generating your page.', 422, 'name_required');
        }

        $owner = $request->user();

        // Plan caps — mirror the web wizard's finish() guard, surfaced as JSON
        // (the web CheckPlanLimit middleware redirects, which is no use here).
        $features = $owner->plan?->features ?? [];
        $maxLinks = $features['max_links'] ?? 5;
        if ($maxLinks !== -1 && $owner->links()->count() >= $maxLinks) {
            return $this->fail("You've reached your plan's link limit ({$maxLinks}). Upgrade your plan for more links.", 403, 'link_limit');
        }
        $maxBiolinks = $features['max_biolinks'] ?? 1;
        if ($maxBiolinks !== -1) {
            $usedBiolinks = $owner->links()->whereIn('type', Link::BIOLINK_FAMILY)->count();
            if ($usedBiolinks >= $maxBiolinks) {
                return $this->fail("You've reached your plan's Link in Bio limit ({$maxBiolinks}). Upgrade your plan for more.", 403, 'biolink_limit');
            }
        }

        try {
            $link = $this->generator->generate($owner, $category, $pageType, $industry, $answers);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('We hit a snag generating your page. Please try again.', 500, 'generation_failed');
        }

        return $this->created(['link' => LinkResource::toArray($link->fresh())]);
    }

    private function isValidCategory(string $category): bool
    {
        return in_array($category, array_column(BiolinkWizardQuestions::categories(), 'slug'), true);
    }

    private function isValidPageType(string $category, string $pageType): bool
    {
        return in_array($pageType, array_column(BiolinkWizardQuestions::pageTypes($category), 'slug'), true);
    }
}
