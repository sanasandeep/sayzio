<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserFile;
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
     * the industry options for every combo. The client caches this and drives
     * the first three steps without round-trips.
     *
     * The industry step is always a real step (matching the web wizard): every
     * combo gets an industry list — the combo's specific one when it has one,
     * otherwise a small generic set — so the mobile wizard never silently skips
     * it. Each page type and industry carries a FontAwesome icon name (resolved
     * to a native glyph client-side) so the steps render the same icon-led look
     * as the web flow.
     */
    public function taxonomy()
    {
        $pageTypes  = [];
        $industries = [];

        foreach (BiolinkWizardQuestions::categories() as $cat) {
            $slug = $cat['slug'];

            $pageTypes[$slug] = array_map(function (array $pt) use ($slug) {
                $pt['icon'] = BiolinkWizardQuestions::pageTypeIcon($slug, $pt['slug']);
                return $pt;
            }, BiolinkWizardQuestions::pageTypes($slug));

            foreach ($pageTypes[$slug] as $pt) {
                $industries["{$slug}.{$pt['slug']}"] = array_map(function (array $ind) {
                    $ind['icon'] = BiolinkWizardQuestions::industryIcon($ind['slug']);
                    return $ind;
                }, $this->effectiveIndustries($slug, $pt['slug']));
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
            // The industry step is always shown on mobile now (generic fallback
            // when the combo has no specific list), so this is always true.
            'has_industry_step' => !empty($this->effectiveIndustries($category, $pageType)),
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

        // The industry step is always shown (specific list when the combo has
        // one, otherwise a generic set), so validate against the effective list
        // — matching the web wizard. An empty/absent value is allowed (the user
        // skipped the step); generic slugs are harmless to the recipe pipeline,
        // which falls back to the category placeholder for unknown slugs.
        $industrySlugs = array_column($this->effectiveIndustries($category, $pageType), 'slug');
        $industry = $data['industry'] ?? null;
        if ($industry === '') {
            $industry = null;
        }
        if (empty($industrySlugs)) {
            $industry = null;
        } elseif ($industry !== null && !in_array($industry, $industrySlugs, true)) {
            return $this->fail('Invalid industry.', 422, 'invalid_industry');
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
        if ($maxLinks !== -1 && ($usedLinks = $owner->links()->count()) >= $maxLinks) {
            return $this->planGate("You've reached your plan's link limit ({$maxLinks}). Upgrade your plan for more links.", 'max_links', $owner, 403, 'link_limit', $usedLinks);
        }
        $maxBiolinks = $features['max_biolinks'] ?? 1;
        if ($maxBiolinks !== -1) {
            $usedBiolinks = $owner->links()->whereIn('type', Link::BIOLINK_FAMILY)->count();
            if ($usedBiolinks >= $maxBiolinks) {
                return $this->planGate("You've reached your plan's Link in Bio limit ({$maxBiolinks}). Upgrade your plan for more.", 'max_biolinks', $owner, 403, 'biolink_limit', $usedBiolinks);
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

    /**
     * Upload an image answer (e.g. avatar/cover) from the device during the
     * wizard; returns the public URL to stamp into the answer. Mirrors the
     * restaurant/resume photo-upload flow: the file lands in the user's vault
     * as a UserFile and the resulting URL matches what the web editor sees, so
     * pasting a URL stays a valid fallback on the client.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $user = $request->user();

        try {
            $userFile = UserFile::createFromUpload($request->file('photo'), $user, [
                'max_size_mb'    => 5,
                'compress_image' => true,
                'max_width'      => 1600,
                'max_height'     => 1600,
                'quality'        => 85,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'upload_failed');
        }

        // The sanctum API path doesn't bind the active workspace, so the shared
        // createFromUpload() lands the vault file with workspace_id = null.
        // workspace_id isn't mass-assignable, so set it directly.
        if ($userFile->workspace_id === null) {
            $userFile->workspace_id = $this->activeWorkspaceId($user);
            $userFile->save();
        }

        return $this->ok(['photo_url' => $userFile->url]);
    }

    /**
     * The industry options for the (always-present) industry step: the combo's
     * specific list when it has one, otherwise a generic set so the step is
     * never blank. Mirrors the web wizard's effectiveIndustries().
     */
    private function effectiveIndustries(string $category, string $pageType): array
    {
        $specific = BiolinkWizardQuestions::industries($category, $pageType);
        return !empty($specific) ? $specific : BiolinkWizardQuestions::genericIndustries();
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
