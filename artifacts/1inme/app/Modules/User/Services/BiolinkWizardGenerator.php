<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared "generate the biolink page" core for the guided wizard.
 *
 * Both the web wizard (User\Controllers\BiolinkWizardController::finish) and
 * the mobile API wizard (Api\Controllers\BiolinkWizardController::generate)
 * funnel through here so the recipe → Link → blocks generation lives in
 * exactly one place. Callers are responsible for validating the answers and
 * enforcing plan caps before invoking this; the service only builds the page.
 */
class BiolinkWizardGenerator
{
    public function __construct(private TemplateService $templates) {}

    /**
     * Create a biolink Link for $owner and paint the recipe's blocks onto it.
     *
     * Runs inside a transaction so a failed apply (e.g. an unknown block type)
     * rolls back and never leaves an empty link sitting in the dashboard. The
     * Link title is derived from the answers via BiolinkWizardQuestions so web
     * and mobile name the page identically.
     *
     * When a `$templateSnapshot` is supplied (the user picked a starting
     * design), it is seeded first — design + blocks — and the recipe answers
     * are then *layered* on top: the user's identity (name/title/bio/avatar) is
     * merged into the template's profile card and the recipe's remaining
     * content blocks are appended beneath the template's design, preserving the
     * template's page-level theme. With no template (the default "Start from
     * scratch" path) the recipe is applied verbatim, exactly as before.
     *
     * @param array<string,mixed>      $answers
     * @param array<string,mixed>|null $templateSnapshot PageTemplate->snapshot.
     */
    public function generate(
        User $owner,
        string $category,
        string $pageType,
        ?string $industry,
        array $answers,
        ?array $templateSnapshot = null,
        ?string $alias = null,
    ): Link {
        $title = BiolinkWizardQuestions::resolveTitle($answers);

        return DB::transaction(function () use ($owner, $category, $pageType, $industry, $answers, $title, $templateSnapshot, $alias) {
            $link = Link::create([
                'user_id'   => $owner->id,
                'type'      => 'biolink',
                'alias'     => $this->resolveAlias($alias),
                'title'     => mb_substr($title, 0, 255),
                'is_active' => true,
            ]);

            $snapshot = BiolinkPageRecipes::build($category, $pageType, $industry, $answers);

            if (is_array($templateSnapshot) && !empty($templateSnapshot['blocks'])) {
                // Seed the chosen starting design (theme + blocks) first...
                $this->templates->applyPageToLink($link, $templateSnapshot, /*replace*/ true);
                // ...then layer the user's wizard answers on top of it.
                $this->layerRecipeOntoTemplate($link, $snapshot, $answers);
            } else {
                $this->templates->applyPageToLink($link, $snapshot, /*replace*/ true);
            }

            return $link;
        });
    }

    /**
     * Resolve the alias for the new link. Uses the user's custom alias (carried
     * through from the Create Link page) when one was supplied and is still
     * available; otherwise — or if it was taken between wizard start and finish
     * — falls back to an auto-generated alias so generation never fails.
     */
    private function resolveAlias(?string $alias): string
    {
        $alias = trim((string) $alias);
        if ($alias !== '' && !Link::where('alias', $alias)->exists()) {
            return $alias;
        }
        return Link::generateAlias();
    }

    /**
     * Layer a recipe snapshot onto a link that has already been seeded with a
     * starting-design template. Personalises the template's profile card with
     * the user's identity (rather than stacking a duplicate profile) and
     * appends the recipe's remaining content blocks beneath the template's
     * design without disturbing its page-level theme.
     *
     * @param array<string,mixed> $recipeSnapshot
     * @param array<string,mixed> $answers
     */
    private function layerRecipeOntoTemplate(Link $link, array $recipeSnapshot, array $answers): void
    {
        $blocks = is_array($recipeSnapshot['blocks'] ?? null) ? $recipeSnapshot['blocks'] : [];

        // Split the recipe's leading profile card from the rest of its content.
        $recipeProfile = null;
        $rest = [];
        foreach ($blocks as $b) {
            if (!is_array($b)) {
                continue;
            }
            if ($recipeProfile === null && ($b['type'] ?? '') === 'profile_card_v1') {
                $recipeProfile = $b;
                continue;
            }
            $rest[] = $b;
        }

        // Merge the user's identity into the template's existing profile card,
        // so we personalise the chosen design instead of duplicating profiles.
        if ($recipeProfile !== null) {
            $tplProfile = $link->biolinkBlocks()
                ->where('type', 'profile_card_v1')
                ->orderBy('id')
                ->first();

            if ($tplProfile) {
                $settings = is_array($tplProfile->settings) ? $tplProfile->settings : [];
                $src = is_array($recipeProfile['settings'] ?? null) ? $recipeProfile['settings'] : [];
                foreach (['name', 'title', 'headline', 'bio', 'avatar', 'cover', 'location', 'website'] as $k) {
                    $v = $src[$k] ?? null;
                    if (is_string($v) && trim($v) !== '') {
                        $settings[$k] = $v;
                    }
                }
                // The template card is no longer a placeholder once the user's
                // own details have been merged in.
                unset($settings['_placeholder']);
                $tplProfile->settings = $settings;
                $tplProfile->save();
            } else {
                // Template had no profile card — keep the recipe's so the page
                // still leads with the user's identity.
                array_unshift($rest, $recipeProfile);
            }
        }

        // Append the recipe's remaining content blocks beneath the template's
        // design. Passing an empty `biolink` keeps the template's theme intact
        // (applyPageToLink only array_merges page settings when not replacing).
        if ($rest) {
            $this->templates->applyPageToLink(
                $link,
                ['biolink' => [], 'blocks' => array_values($rest)],
                /*replace*/ false,
            );
        }
    }
}
