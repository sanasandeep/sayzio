<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature coverage for the REST slide-deck editor endpoints
 * (GET/PUT /api/v1/links/{id}/slides — Api\SlideDeckApiController), which
 * delegate to the shared static helpers on the web SlideDeckController
 * (saveRules / sanitizeSaveData / persistDeck / ensureDeckFor). Covers:
 *  - ownership gating (foreign links and non-biolink-family links 404),
 *  - validation limits (max 50 slides, 10 block_ids per slide,
 *    auto_advance 0–60000ms),
 *  - block-id sanitization to blocks owned by the link,
 *  - background media URL sanitization (javascript:/protocol-relative
 *    values are blanked),
 *  - publish behavior (version bump + published snapshot; stays published
 *    and keeps bumping on later saves).
 */
class ApiSlideDeckTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function makeBiolink(User $owner): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    private function makeBlock(Link $link, string $type = 'heading'): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => $type,
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['text' => 'Hello'],
        ]);
    }

    private function authed(User $user): static
    {
        return $this->withHeader(
            'Authorization',
            'Bearer ' . $user->createToken('test')->plainTextToken
        );
    }

    private function slidePayload(array $overrides = []): array
    {
        return array_merge([
            'title'      => 'Slide',
            'block_ids'  => [],
            'background' => ['type' => 'color', 'color' => '#0f172a'],
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Ownership gating
    // ------------------------------------------------------------------

    public function test_show_and_save_404_on_other_users_link(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->authed($stranger)->getJson("/api/v1/links/{$link->id}/slides")
            ->assertNotFound();

        $this->authed($stranger)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [$this->slidePayload()],
        ])->assertNotFound();

        // No deck was created for the stranger's failed attempts.
        $this->assertNull(LinkSlideDeck::where('link_id', $link->id)->first());
    }

    public function test_non_biolink_family_link_404s(): void
    {
        $owner = $this->makeUser();
        $short = Link::create([
            'user_id'   => $owner->id,
            'type'      => 'short',
            'alias'     => Link::generateAlias(),
            'url'       => 'https://example.com',
            'is_active' => true,
        ]);

        $this->authed($owner)->getJson("/api/v1/links/{$short->id}/slides")
            ->assertNotFound();

        $this->authed($owner)->putJson("/api/v1/links/{$short->id}/slides", [
            'slides' => [$this->slidePayload()],
        ])->assertNotFound();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->getJson("/api/v1/links/{$link->id}/slides")->assertUnauthorized();
        $this->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [$this->slidePayload()],
        ])->assertUnauthorized();
    }

    // ------------------------------------------------------------------
    // Show — deck bootstrap
    // ------------------------------------------------------------------

    public function test_show_seeds_a_default_deck_with_welcome_slide(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);
        $this->makeBlock($link);

        $resp = $this->authed($owner)->getJson("/api/v1/links/{$link->id}/slides")
            ->assertOk();

        $deck = $resp->json('data.deck');
        $this->assertFalse($deck['is_published']);
        $this->assertSame(1, $deck['version']);
        $this->assertCount(1, $deck['slides']);
        $this->assertSame('Welcome', $deck['slides'][0]['title']);
        $this->assertSame((int) $link->id, $resp->json('data.meta.link_id'));
        $this->assertCount(1, $resp->json('data.meta.blocks'));
    }

    // ------------------------------------------------------------------
    // Validation limits
    // ------------------------------------------------------------------

    public function test_save_rejects_more_than_50_slides(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $slides = array_fill(0, 51, $this->slidePayload());
        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => $slides,
        ])->assertStatus(422);

        // 50 is accepted.
        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => array_fill(0, 50, $this->slidePayload()),
        ])->assertOk();
        $this->assertCount(50, LinkSlideDeck::where('link_id', $link->id)->first()->slides);
    }

    public function test_save_rejects_empty_slides_list(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [],
        ])->assertStatus(422);
    }

    public function test_save_rejects_more_than_10_block_ids_per_slide(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [$this->slidePayload(['block_ids' => range(1, 11)])],
        ])->assertStatus(422);
    }

    public function test_save_rejects_out_of_range_auto_advance(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'settings' => ['auto_advance' => 60001],
            'slides'   => [$this->slidePayload()],
        ])->assertStatus(422);

        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'settings' => ['auto_advance' => -1],
            'slides'   => [$this->slidePayload()],
        ])->assertStatus(422);

        // Boundary values are accepted and persisted.
        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'settings' => ['auto_advance' => 60000],
            'slides'   => [$this->slidePayload()],
        ])->assertOk();
        $deck = LinkSlideDeck::where('link_id', $link->id)->first();
        $this->assertSame(60000, $deck->settings['auto_advance']);
    }

    public function test_save_rejects_unknown_transition(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'settings' => ['transition' => 'teleport'],
            'slides'   => [$this->slidePayload()],
        ])->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Sanitization
    // ------------------------------------------------------------------

    public function test_block_ids_are_restricted_to_blocks_owned_by_the_link(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $link        = $this->makeBiolink($owner);
        $otherLink   = $this->makeBiolink($stranger);
        $ownBlock    = $this->makeBlock($link);
        $foreignBlock = $this->makeBlock($otherLink);

        $resp = $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [$this->slidePayload([
                'block_ids' => [$ownBlock->id, $foreignBlock->id, 999999],
            ])],
        ])->assertOk();

        $this->assertSame([$ownBlock->id], $resp->json('data.deck.slides.0.block_ids'));

        $deck = LinkSlideDeck::where('link_id', $link->id)->first();
        $this->assertSame([$ownBlock->id], array_values($deck->slides()->first()->block_ids));
    }

    public function test_unsafe_background_media_urls_are_blanked(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [
                $this->slidePayload(['background' => [
                    'type'      => 'image',
                    'image_url' => 'javascript:alert(1)',
                ]]),
                $this->slidePayload(['background' => [
                    'type'      => 'video',
                    'video_url' => '//evil.example/x.mp4',
                ]]),
                $this->slidePayload(['background' => [
                    'type'   => 'slideshow',
                    'images' => ['javascript:alert(1)', 'https://example.com/ok.jpg'],
                ]]),
            ],
        ])->assertOk();

        $slides = LinkSlideDeck::where('link_id', $link->id)->first()
            ->slides()->orderBy('sort_order')->get();

        $this->assertSame('', $slides[0]->background['image_url']);
        $this->assertSame('', $slides[1]->background['video_url']);
        // Unsafe slideshow entries are dropped entirely; safe ones survive.
        $this->assertSame(['https://example.com/ok.jpg'], array_values($slides[2]->background['images']));
    }

    public function test_safe_background_urls_survive(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [$this->slidePayload(['background' => [
                'type'      => 'image',
                'image_url' => 'https://example.com/bg.jpg',
            ]])],
        ])->assertOk();

        $slide = LinkSlideDeck::where('link_id', $link->id)->first()->slides()->first();
        $this->assertSame('https://example.com/bg.jpg', $slide->background['image_url']);
    }

    // ------------------------------------------------------------------
    // Publish / version behavior
    // ------------------------------------------------------------------

    public function test_publishing_bumps_version_and_builds_snapshot(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        // Draft save: no publish, version stays at 1, no snapshot.
        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [$this->slidePayload(['title' => 'Draft'])],
        ])->assertOk();

        $deck = LinkSlideDeck::where('link_id', $link->id)->first();
        $this->assertFalse((bool) $deck->is_published);
        $this->assertSame(1, (int) $deck->version);
        $this->assertEmpty($deck->published_snapshot);

        // Publish: version bumps, snapshot is built from the saved slides.
        $resp = $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'is_published' => true,
            'slides'       => [$this->slidePayload(['title' => 'Live'])],
        ])->assertOk();

        $deck->refresh();
        $this->assertTrue((bool) $deck->is_published);
        $this->assertSame(2, (int) $deck->version);
        $this->assertNotEmpty($deck->published_snapshot);
        $this->assertTrue($resp->json('data.deck.is_published'));
        $this->assertSame(2, $resp->json('data.deck.version'));

        // Once published, subsequent saves without is_published stay
        // published and keep bumping the version + rebuilding the snapshot.
        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [$this->slidePayload(['title' => 'Live v2'])],
        ])->assertOk();

        $deck->refresh();
        $this->assertTrue((bool) $deck->is_published);
        $this->assertSame(3, (int) $deck->version);
    }

    public function test_save_replaces_slides_wholesale_and_orders_them(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeBiolink($owner);

        // First save (replaces the seeded Welcome slide).
        $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [
                $this->slidePayload(['title' => 'One']),
                $this->slidePayload(['title' => 'Two']),
                $this->slidePayload(['title' => 'Three']),
            ],
        ])->assertOk();

        // Second save shrinks the deck; old rows must be gone.
        $resp = $this->authed($owner)->putJson("/api/v1/links/{$link->id}/slides", [
            'slides' => [
                $this->slidePayload(['title' => 'B']),
                $this->slidePayload(['title' => 'A']),
            ],
        ])->assertOk();

        $titles = collect($resp->json('data.deck.slides'))->pluck('title')->all();
        $this->assertSame(['B', 'A'], $titles);

        $deck = LinkSlideDeck::where('link_id', $link->id)->first();
        $rows = $deck->slides()->orderBy('sort_order')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([0, 1], $rows->pluck('sort_order')->map(fn ($v) => (int) $v)->all());
    }

    // ------------------------------------------------------------------
    // Plan gating of in-slide block creation
    // ------------------------------------------------------------------

    private function makePlan(array $features): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    public function test_creatable_types_shrink_to_the_plan_block_allowlist(): void
    {
        // A restrictive plan that allows only a strict subset of the
        // slides editor's CREATABLE_TYPES catalog.
        $allowed = ['heading', 'paragraph', 'link'];
        $plan = $this->makePlan(['block_types_allowed' => $allowed]);
        $owner = $this->makeUser();
        $owner->update(['plan_id' => $plan->id]);
        $link = $this->makeBiolink($owner);

        $resp = $this->authed($owner)->getJson("/api/v1/links/{$link->id}/slides")
            ->assertOk();

        $types = collect($resp->json('data.meta.creatable_types'))->pluck('type');
        $this->assertEqualsCanonicalizing($allowed, $types->all());
        // Premium-only types from the catalog are NOT advertised.
        $this->assertNotContains('video', $types->all());
        $this->assertNotContains('image_grid', $types->all());

        // An unrestricted ('*') plan advertises the full curated catalog.
        $full = $this->makeUser();
        $full->update(['plan_id' => $this->makePlan(['block_types_allowed' => '*'])->id]);
        $fullLink = $this->makeBiolink($full);
        $fullTypes = collect(
            $this->authed($full)->getJson("/api/v1/links/{$fullLink->id}/slides")
                ->assertOk()->json('data.meta.creatable_types')
        )->pluck('type');
        $this->assertEqualsCanonicalizing(
            \App\Modules\Api\Controllers\SlideDeckApiController::CREATABLE_TYPES,
            $fullTypes->all()
        );
    }

    public function test_block_create_rejects_a_type_the_plan_disallows(): void
    {
        $plan = $this->makePlan(['block_types_allowed' => ['heading', 'paragraph', 'link']]);
        $owner = $this->makeUser();
        $owner->update(['plan_id' => $plan->id]);
        $link = $this->makeBiolink($owner);

        // Disallowed type (advertised in CREATABLE_TYPES but excluded by
        // this plan's allowlist) is rejected with a 403 and no row.
        $this->authed($owner)->postJson("/api/v1/links/{$link->id}/blocks", [
            'type'     => 'video',
            'settings' => ['url' => 'https://example.com/v.mp4'],
        ])->assertForbidden()
          ->assertJsonPath('error.code', 'plan_block_type_locked');

        $this->assertSame(0, BiolinkBlock::where('link_id', $link->id)->count());

        // An allowed type on the same plan still creates fine.
        $this->authed($owner)->postJson("/api/v1/links/{$link->id}/blocks", [
            'type'     => 'heading',
            'settings' => ['text' => 'Hi'],
        ])->assertCreated();

        $this->assertSame(1, BiolinkBlock::where('link_id', $link->id)->where('type', 'heading')->count());
    }
}
