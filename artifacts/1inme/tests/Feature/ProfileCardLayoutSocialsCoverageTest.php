<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Template-coverage guard for the profile-card socials row (Task #5679).
 *
 * common/biolink-profile-card.blade.php dispatches on the design-carried
 * `_profile_layout` token, and only SOME layouts include the shared
 * common/biolink-profile-socials row. Nothing else asserts which layouts
 * surface socials, so a refactor could silently drop the include from a
 * layout that should have it (or a new catalog design could ship without
 * anyone deciding whether it shows socials).
 *
 * This test:
 *  1. enumerates every `_profile_layout` the BlockVariantCatalog can apply
 *     (plus the historical per-type fallback layouts) and fails if a new
 *     layout appears that isn't classified below, and
 *  2. renders the public page for each layout with a socials list present,
 *     asserting the expected set (and ONLY that set) renders the row.
 */
class ProfileCardLayoutSocialsCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Layouts whose renderer branch includes the socials row. Keep in
     * lockstep with common/biolink-profile-card.blade.php — if you add or
     * remove an @include of common/biolink-profile-socials, update this
     * list deliberately.
     */
    private const LAYOUTS_WITH_SOCIALS = [
        'glass',
        'gradient',
        'minimal_dark',
        'social_profile',
        'business_card',
        'sidebar_accent',
    ];

    /**
     * Layouts that intentionally render NO socials row.
     */
    private const LAYOUTS_WITHOUT_SOCIALS = [
        'classic_creator',
        'cover_hero',
        'split',
        'floating',
        'founder',
        'magazine',
        'id_badge',
        'ticket_stub',
        'polaroid',
        'terminal',
        // Historical per-type fallback layouts (profile_card_v3 / v4).
        'stats',
        'badges',
    ];

    /** Historical fallbacks used when no design has been applied. */
    private const FALLBACK_LAYOUTS = ['classic_creator', 'cover_hero', 'stats', 'badges'];

    private function owner(): User
    {
        $u = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($u);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function biolink(User $owner): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => 'zb' . Str::lower(Str::random(10)),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    private function visitPublic(string $alias)
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        return $this->get('/' . $alias);
    }

    /**
     * All `_profile_layout` tokens the catalog can stamp onto a
     * profile-card block, across the whole profile_card_v1..v4 family.
     *
     * @return string[]
     */
    private function catalogLayouts(): array
    {
        $layouts = [];
        foreach (['profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4'] as $type) {
            foreach (BlockVariantCatalog::forType($type) as $variant) {
                $layout = $variant['style']['_profile_layout'] ?? '';
                if ($layout !== '') {
                    $layouts[$layout] = true;
                }
            }
        }

        return array_keys($layouts);
    }

    public function test_every_catalog_layout_is_classified(): void
    {
        $known = array_merge(self::LAYOUTS_WITH_SOCIALS, self::LAYOUTS_WITHOUT_SOCIALS);

        $all = array_unique(array_merge($this->catalogLayouts(), self::FALLBACK_LAYOUTS));

        $unclassified = array_values(array_diff($all, $known));
        $this->assertSame([], $unclassified,
            'New profile-card layout(s) ' . implode(', ', $unclassified)
            . ' are not classified in ProfileCardLayoutSocialsCoverageTest — decide '
            . 'whether each renders the socials row and add it to the matching list.');

        // And the classification lists must not reference layouts that no
        // longer exist anywhere (catalog or fallback) — stale entries would
        // silently stop guarding anything.
        $stale = array_values(array_diff($known, $all));
        $this->assertSame([], $stale,
            'Layout(s) ' . implode(', ', $stale)
            . ' are classified here but no longer produced by the catalog or '
            . 'the per-type fallbacks — remove or update them.');
    }

    public function test_expected_layouts_render_the_socials_row_and_only_those(): void
    {
        $owner = $this->owner();

        $all = array_unique(array_merge($this->catalogLayouts(), self::FALLBACK_LAYOUTS));
        sort($all);

        foreach ($all as $layout) {
            $link   = $this->biolink($owner);
            $socUrl = 'https://socguard.example/' . $layout . '-' . Str::lower(Str::random(6));

            BiolinkBlock::create([
                'link_id'   => $link->id,
                'type'      => 'profile_card_v1',
                'is_active' => true,
                'settings'  => [
                    'name'    => 'Layout ' . $layout,
                    'socials' => [
                        ['name' => 'twitter', 'url' => $socUrl],
                    ],
                    '_style'  => ['_profile_layout' => $layout],
                ],
            ]);

            $resp = $this->visitPublic($link->alias);
            $resp->assertOk();

            if (in_array($layout, self::LAYOUTS_WITH_SOCIALS, true)) {
                $this->assertStringContainsString($socUrl, $resp->getContent(),
                    "Layout '{$layout}' should render the socials row but the social URL is missing.");
            } else {
                $this->assertStringNotContainsString($socUrl, $resp->getContent(),
                    "Layout '{$layout}' unexpectedly renders the socials row — if intentional, move it to LAYOUTS_WITH_SOCIALS.");
            }
        }
    }
}
