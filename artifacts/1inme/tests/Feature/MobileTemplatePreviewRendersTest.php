<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Database\Seeders\CardTemplateSeeder;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Database\Seeders\StarterPageTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile-side sibling of {@see TemplatePreviewRendersTest}.
 *
 * Admins/creators can browse and apply the same template library from the
 * Expo app over /api/v1, which renders and serializes the stored snapshots
 * through a DIFFERENT path than the web admin preview route:
 *
 *   - Page: GET /api/v1/links/{id}/page-templates/{template}
 *       Builds an unsaved preview link via TemplateService::buildPreviewLink()
 *       (the same sanitizer applyPage uses) and flattens the active block tree
 *       into the public {id,type,sort_order,parent_id,settings} payload.
 *   - Card: POST /api/v1/links/{id}/card-templates/apply
 *       Inserts the card sub-tree via TemplateService::applyCardToLink()
 *       (insertBlockTree + sanitizer) and returns the freshly-created tree.
 *
 * Both paths throw InvalidArgumentException on an unknown/unsupported block
 * type (buildPreviewBlock / insertBlockTree) — which, unlike the web preview
 * route, is NOT caught here, so a renderer-incompatible snapshot surfaces as
 * an HTTP 500 rather than a readable fallback. Either way, a template whose
 * stored snapshot blanks out its blocks on this surface would otherwise go
 * uncaught: the mobile gallery would silently show an empty page/card.
 *
 * This test drives the real HTTP endpoints against the actual seeded
 * template library so it catches regressions in the live mobile serialize
 * path (buildPreviewLink, applyCardToLink, the shared sanitizer) that a
 * pure snapshot-shape check would miss.
 *
 * Auth note: authenticated with a REAL Sanctum bearer token, not
 * Sanctum::actingAs() — the latter injects a mock current-access-token that
 * the TouchSessionToken middleware then tries to ->save(), 500-ing every
 * request.
 */
class MobileTemplatePreviewRendersTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the full live template library (page + card). */
    private function seedTemplateLibrary(): void
    {
        $this->seed(StarterPageTemplatesSeeder::class);
        $this->seed(ExpandedPageTemplateLibrarySeeder::class);
        $this->seed(CardTemplateSeeder::class);
    }

    /**
     * Build a user holding a plan that ranks above every plan tier any active
     * template requires, so plan-locked templates ({@see CardTemplateController})
     * are still appliable and the test covers the FULL active library — not just
     * the free tier. Returns the user with a default workspace.
     */
    private function makeUnlockedUser(): User
    {
        // Create a Plan row for every distinct plan_tier referenced by an
        // active template, ranked low; the user's own plan ranks far above
        // them all so isLocked() never trips for any template.
        $tiers = collect()
            ->merge(PageTemplate::active()->pluck('plan_tier'))
            ->merge(CardTemplate::active()->pluck('plan_tier'))
            ->filter(fn ($t) => is_string($t) && $t !== '')
            ->unique();

        foreach ($tiers as $tier) {
            Plan::firstOrCreate(
                ['slug' => $tier],
                [
                    'name'          => ucfirst((string) $tier),
                    'monthly_price' => 0,
                    'annual_price'  => 0,
                    'trial_days'    => 0,
                    'status'        => 'active',
                    'sort_order'    => 1,
                    'features'      => ['max_links' => 1000, 'max_biolinks' => 1000],
                ],
            );
        }

        $topPlan = Plan::firstOrCreate(
            ['slug' => 'tpl-top-' . Str::random(6)],
            [
                'name'          => 'Top',
                'monthly_price' => 0,
                'annual_price'  => 0,
                'trial_days'    => 0,
                'status'        => 'active',
                'sort_order'    => 99999,
                'features'      => ['max_links' => 1000, 'max_biolinks' => 1000],
            ],
        );

        $user = User::create([
            'name'              => 'Mobile Tpl ' . Str::random(4),
            'email'             => 'mt-' . Str::random(8) . '@example.com',
            'password'          => Hash::make('x'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'plan_id'           => $topPlan->id,
        ]);
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    private function makeBiolink(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => Link::generateAlias(),
            'title'   => 'Preview Bio',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Recursively count the active blocks a page snapshot should flatten into
     * via buildPreviewLink: every block (default-active unless is_active is
     * explicitly false), recursing into a card's children — exactly the set
     * the show endpoint emits after filtering on is_active.
     */
    private function countActivePreviewBlocks(array $blocks): int
    {
        $n = 0;
        foreach ($blocks as $b) {
            if (!is_array($b)) {
                continue;
            }
            $active = !array_key_exists('is_active', $b) || (bool) $b['is_active'];
            if ($active) {
                $n++;
            }
            if (($b['type'] ?? null) === 'card' && !empty($b['children']) && is_array($b['children'])) {
                $n += $this->countActivePreviewBlocks($b['children']);
            }
        }
        return $n;
    }

    public function test_every_active_page_template_serializes_without_blank_blocks(): void
    {
        $this->seedTemplateLibrary();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $templates = PageTemplate::where('is_active', true)->get(['id', 'slug', 'snapshot']);
        $this->assertNotEmpty($templates, 'no active page templates were seeded to preview');

        foreach ($templates as $tpl) {
            $label = "page template '{$tpl->slug}'";

            $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates/{$tpl->id}");
            $resp->assertOk(
                // A 500 here means buildPreviewLink threw on a renderer-
                // incompatible block type in the snapshot.
            );

            $blocks = $resp->json('data.blocks');
            $this->assertIsArray($blocks, "{$label} returned no blocks array");
            $this->assertNotEmpty(
                $blocks,
                "{$label} serialized to an empty block payload — its snapshot "
                . 'has no active blocks the mobile renderer can show. Open the '
                . 'admin design-fix tools to repair it.'
            );

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $expected = $this->countActivePreviewBlocks($snapshot['blocks'] ?? []);
            $this->assertSame(
                $expected,
                count($blocks),
                "{$label} silently dropped blocks during mobile serialization: "
                . "expected {$expected} active block(s) from the snapshot, got "
                . count($blocks) . '. A renderer-incompatible block type is being '
                . 'stripped instead of rendered.'
            );
        }
    }

    public function test_every_active_card_template_applies_without_blank_blocks(): void
    {
        $this->seedTemplateLibrary();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $templates = CardTemplate::where('is_active', true)->get(['id', 'slug', 'snapshot']);
        $this->assertNotEmpty($templates, 'no active card templates were seeded to preview');

        foreach ($templates as $tpl) {
            $label = "card template '{$tpl->slug}'";

            $resp = $this->postJson("/api/v1/links/{$link->id}/card-templates/apply", [
                'template_id' => $tpl->id,
            ]);
            $resp->assertOk(
                // A 500 here means applyCardToLink/insertBlockTree threw on a
                // renderer-incompatible block type in the snapshot.
            );

            $blocks = $resp->json('data.blocks');
            $this->assertIsArray($blocks, "{$label} returned no blocks array");
            $this->assertNotEmpty(
                $blocks,
                "{$label} applied to an empty block payload — its snapshot "
                . 'produced no blocks the mobile renderer can show.'
            );

            // applyCardToLink returns the root card plus its direct children,
            // so the serialized tree must include the card and one entry per
            // snapshot child. A short payload means children were dropped.
            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $children = is_array($snapshot['children'] ?? null) ? $snapshot['children'] : [];
            $expected = 1 + count($children);
            $this->assertSame(
                $expected,
                count($blocks),
                "{$label} silently dropped blocks during mobile apply: expected "
                . "{$expected} block(s) (card + " . count($children) . ' child blocks) '
                . 'from the snapshot, got ' . count($blocks) . '. A renderer-'
                . 'incompatible child block type is being stripped instead of '
                . 'inserted.'
            );

            // The root must be the card container; remaining entries are its
            // children, confirming the sub-tree serialized intact.
            $this->assertSame('card', $blocks[0]['type'], "{$label} root block is not a card");
        }
    }

    /**
     * Sanity check on the guard itself: a page template whose snapshot holds
     * a deliberately broken block type must blow up the mobile show endpoint
     * (buildPreviewLink throws, uncaught -> HTTP 500). This proves the
     * positive tests above would actually catch a renderer-incompatible
     * template rather than passing vacuously.
     */
    public function test_broken_page_template_fails_the_mobile_preview(): void
    {
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $broken = PageTemplate::create([
            'slug'                 => 'broken-' . Str::random(8),
            'name'                 => 'Broken Preview',
            'category'             => 'general',
            'description'          => 'Deliberately broken for the guard test.',
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [],
            'snapshot'             => [
                'biolink' => [],
                'blocks'  => [
                    ['type' => 'definitely_not_a_real_block_type', 'settings' => [], 'is_active' => true],
                ],
            ],
        ]);

        $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates/{$broken->id}");
        $resp->assertStatus(500);
    }
}
