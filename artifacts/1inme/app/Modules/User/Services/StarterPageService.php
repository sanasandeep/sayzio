<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BlockDefaults;

/**
 * What a brand-new Link in Bio starts with.
 *
 * Before this, a new page was a blank canvas: "No blocks yet" in the editor,
 * "This Link in Bio page is being set up" in the preview, and nothing at all
 * to tell a first-time user what a page is made of. Sana, 2026-09-23: "while
 * creating empty link, default it should load 5 blocks automatically, so
 * users will understand better."
 *
 * ---- Where the five blocks come from --------------------------------
 *
 * An admin can mark any page template as a starter by giving it a
 * `starter_weight` above zero (see the migration). One of those is drawn per
 * new page. When no template qualifies -- which is where every install
 * begins, since the seeded template library was purged -- the BUILT_IN kit
 * below is used, so the feature works on day one with no admin setup.
 *
 * ---- How one is chosen ----------------------------------------------
 *
 * Random, but not blindly random. In order:
 *
 *   1. active templates with starter_weight > 0;
 *   2. minus any the user's plan cannot use (a Free user must not open their
 *      first page on a design they are locked out of editing);
 *   3. preferring ones tagged for the persona the user chose at onboarding,
 *      and falling back to the whole eligible set when none match;
 *   4. a weighted draw among what is left.
 *
 * The weight is what makes this tunable without a deploy: give the starter
 * that converts best a 5 and the experiments a 1.
 */
class StarterPageService
{
    /**
     * The fallback page: five blocks that spell out what a Link in Bio is.
     *
     * Picture, name, a line about you, your socials, one link. Deliberately
     * ordinary -- the job is to be recognisable and immediately editable, not
     * to be a design. Content comes from BlockDefaults so it stays in step
     * with the picker previews and with whatever an admin has overridden
     * under Block Defaults.
     */
    public const BUILT_IN = ['avatar', 'heading', 'paragraph_rich', 'socials', 'link'];

    public function __construct(private TemplateService $templates)
    {
    }

    /**
     * Seed a freshly created page. A no-op on a page that already has blocks,
     * so this can be called from more than one creation path without ever
     * overwriting real work.
     */
    public function seed(Link $link, ?User $user = null): void
    {
        if (! $link->isBiolinkFamily() || $link->biolinkBlocks()->exists()) {
            return;
        }

        $template = $this->pickTemplate($user ?? $link->user);

        $snapshot = $template
            ? (array) $template->snapshot
            : ['blocks' => $this->builtInBlocks()];

        if (empty($snapshot['blocks'])) {
            return;
        }

        // replace: false -- the page is empty, and a false here means a
        // race that created a block first survives instead of being deleted.
        $this->templates->applyPageToLink($link, $snapshot, false, $template);

        $this->stampAsStarter($link);
    }

    /**
     * Mark every block this seeded, so the page can tell "what we handed
     * them" apart from "what they built".
     *
     * Stamped after the apply rather than inside the snapshot, because
     * TemplateService re-sanitizes snapshot settings through the same
     * pipeline as user input -- a provenance flag has no business going
     * through a content sanitizer, and this way it cannot be forged from a
     * hand-edited snapshot either.
     *
     * The flag is only half the test. It says a block came from the starter
     * set; `_placeholder` says it has not been edited since. The public page
     * hides a page only while BOTH are true of every block, so adding one
     * block of your own, or editing one of these, publishes the page.
     */
    private function stampAsStarter(Link $link): void
    {
        foreach ($link->biolinkBlocks()->get() as $block) {
            $settings = $block->settings ?? [];
            $settings['_starter_seed'] = true;
            $block->settings = $settings;
            $block->saveQuietly();
        }
    }

    /**
     * The admin starter this user should get, or null to use BUILT_IN.
     */
    public function pickTemplate(?User $user): ?PageTemplate
    {
        $eligible = PageTemplate::query()
            ->starter()
            ->availableForPlan($user?->plan?->slug)
            ->get();

        if ($eligible->isEmpty()) {
            return null;
        }

        // Persona is a preference, never a filter: a user whose persona has
        // no starter still gets one rather than an empty page.
        $persona = $user?->persona;
        if ($persona) {
            $matching = $eligible->filter(
                fn (PageTemplate $t) => in_array($persona, (array) ($t->recommended_personas ?? []), true)
            );
            if ($matching->isNotEmpty()) {
                $eligible = $matching;
            }
        }

        return $this->drawByWeight($eligible->values()->all());
    }

    /**
     * Weighted random pick. A template with weight 3 comes up three times as
     * often as one with weight 1.
     *
     * @param  array<int, PageTemplate>  $templates
     */
    private function drawByWeight(array $templates): ?PageTemplate
    {
        $total = 0;
        foreach ($templates as $t) {
            $total += max(1, (int) $t->starter_weight);
        }
        if ($total < 1) {
            return $templates[0] ?? null;
        }

        $roll = random_int(1, $total);
        foreach ($templates as $t) {
            $roll -= max(1, (int) $t->starter_weight);
            if ($roll <= 0) {
                return $t;
            }
        }

        return $templates[array_key_last($templates)] ?? null;
    }

    /**
     * BUILT_IN as a snapshot's `blocks` list.
     *
     * Every block is marked `_placeholder` by BlockDefaults, which -- with
     * the `_starter_seed` stamp added after the apply -- is what keeps the
     * public page on its "being set up" notice until the owner has actually
     * changed something. See Link::isUntouchedStarterPage().
     *
     * @return array<int, array{type: string, settings: array, is_active: bool}>
     */
    public function builtInBlocks(): array
    {
        $out = [];

        foreach (self::BUILT_IN as $type) {
            if (! isset(BiolinkBlock::TYPES[$type])) {
                continue;   // a type retired from the catalog is skipped, not fatal
            }

            $out[] = [
                'type'      => $type,
                'settings'  => BlockDefaults::seededSettings($type),
                'is_active' => true,
            ];
        }

        return $out;
    }
}
