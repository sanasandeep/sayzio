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
 * drives all four steps (industry → profile type [+ optional niche] → basic
 * profile & branding → additional content) in memory and POSTs every answer at
 * once to /generate. All three endpoints reuse the exact same services as the
 * web flow — BiolinkWizardQuestions for the taxonomy/questions/split and
 * BiolinkWizardGenerator (BiolinkPageRecipes + TemplateService) for the page
 * generation — so the two surfaces never drift.
 */
class BiolinkWizardController extends Controller
{
    use ApiResponses;

    public function __construct(private BiolinkWizardGenerator $generator) {}

    /**
     * Steps 1–2 in one shot: every category (industry step), the page types
     * (profile type step) under each, and the optional niche-refinement list
     * for every combo. The client caches this and drives the first two steps
     * without round-trips.
     *
     * The niche refinement is folded into the profile-type step and is purely
     * optional: only combos with a *specific* industries() list get one, so the
     * map only contains those combos (no generic fallback). Each page type and
     * industry carries a FontAwesome icon name (resolved to a native glyph
     * client-side) so the steps render the same icon-led look as the web flow.
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
                // Specific-only — combos without a niche list are omitted so the
                // client knows not to show the inline refinement for them.
                $specific = BiolinkWizardQuestions::industries($slug, $pt['slug']);
                if (empty($specific)) {
                    continue;
                }
                $industries["{$slug}.{$pt['slug']}"] = array_map(function (array $ind) {
                    $ind['icon'] = BiolinkWizardQuestions::industryIcon($ind['slug']);
                    return $ind;
                }, $specific);
            }
        }

        return $this->ok([
            'categories' => BiolinkWizardQuestions::categories(),
            'page_types' => $pageTypes,
            'industries' => $industries,
        ]);
    }

    /**
     * Steps 3–4: the detailed question set for a chosen (category, page_type,
     * industry), pre-split into the basic-profile step and the additional-content
     * step so the mobile client renders the same two content surfaces as web.
     */
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

        $questions = array_values(BiolinkWizardQuestions::questions($category, $pageType, $industry));
        $split     = BiolinkWizardQuestions::splitQuestions($questions);

        return $this->ok([
            'questions'  => $questions,
            'basics'     => array_values($split['basics']),
            'additional' => array_values($split['additional']),
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

        // The niche refinement is optional and folded into the profile-type
        // step — validate against the combo's *specific* list only (matching the
        // web wizard). Combos without a specific list force null; an empty/absent
        // value is always allowed (the user skipped the refinement).
        $industrySlugs = array_column(BiolinkWizardQuestions::industries($category, $pageType), 'slug');
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

    private function isValidCategory(string $category): bool
    {
        return in_array($category, array_column(BiolinkWizardQuestions::categories(), 'slug'), true);
    }

    private function isValidPageType(string $category, string $pageType): bool
    {
        return in_array($pageType, array_column(BiolinkWizardQuestions::pageTypes($category), 'slug'), true);
    }
}
