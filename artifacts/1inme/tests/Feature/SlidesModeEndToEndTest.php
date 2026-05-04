<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\LinkSlideViewEvent;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the new biolink slides mode. Mirrors what a
 * Playwright run would exercise but at the HTTP boundary so it lives
 * alongside the rest of the Feature suite (no Playwright is configured
 * for this artifact).
 *
 * Three scenarios are covered:
 *
 *  1. Web flow: enable slides mode on a biolink, save two slides each
 *     hosting a block, publish, hit the public alias and assert the
 *     deck markup renders both slides + the inline view-tracker, then
 *     confirm POST /sl/{alias}/view records a LinkSlideViewEvent.
 *
 *  2. Mobile API: GET /api/v1/biolinks/{alias} for a published slides
 *     biolink returns the SlidesPayload shape the mobile FlatList
 *     viewer consumes, and POST /api/v1/biolinks/{alias}/slides/view
 *     records a mobile-source view event.
 *
 *  3. Editor draft vs publish: saving a draft does NOT replace the
 *     public snapshot — the public alias keeps showing the previously
 *     published deck, while the signed `_preview=1` URL reflects the
 *     in-progress draft built from the live editor tables.
 */
class SlidesModeEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Slides Owner ' . Str::random(4),
            'email'    => 'sl' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function makeBiolink(User $user): Link
    {
        // Set workspace_id explicitly to match the workspace the
        // workspace.scope middleware will resolve at request time, so
        // the global scope on Link doesn't filter the row out when
        // route-model-binding looks it up under acting-as auth.
        $ws = app(WorkspaceContext::class)->resolve($user);
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $ws?->id,
            'type'         => 'biolink',
            'alias'        => Link::generateAlias(),
            'title'        => 'Deck Test',
            'is_active'    => true,
        ]);
    }

    /**
     * Add two simple text blocks we can host inside slides. `paragraph`
     * is the lightest block type and survives the snapshot renderer
     * without needing supporting fixtures.
     */
    private function makeTwoBlocks(Link $link): array
    {
        $b1 = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'paragraph',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['text' => 'First slide body copy'],
        ]);
        $b2 = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'paragraph',
            'sort_order' => 1,
            'is_active'  => true,
            'settings'   => ['text' => 'Second slide body copy'],
        ]);
        return [$b1, $b2];
    }

    /**
     * Build the save payload for the editor's POST endpoint. Defaults
     * to publishing so the public alias picks the snapshot up.
     */
    private function savePayload(array $slides, bool $publish = true): array
    {
        return [
            'is_published' => $publish,
            'settings'     => [
                'theme'        => ['background' => '#0f172a', 'accent' => '#8b5cf6', 'text' => '#f8fafc'],
                'transition'   => 'slide',
                'auto_advance' => 0,
                'loop'         => false,
            ],
            'slides' => $slides,
        ];
    }

    public function test_web_flow_enable_save_publish_view_and_track(): void
    {
        $owner = $this->makeUser();
        $bio   = $this->makeBiolink($owner);
        [$b1, $b2] = $this->makeTwoBlocks($bio);

        // 1. Enable slides mode via the toggle endpoint.
        $this->actingAs($owner)
            ->postJson("/user/links/{$bio->id}/slides/toggle", ['enabled' => true])
            ->assertOk()
            ->assertJson(['ok' => true, 'mode' => 'slides']);

        $bio->refresh();
        $this->assertSame('slides', data_get($bio->settings, 'biolink.mode'));

        // 2. Save + publish two slides, each hosting one of the blocks.
        $payload = $this->savePayload([
            ['title' => 'Slide One', 'block_ids' => [$b1->id]],
            ['title' => 'Slide Two', 'block_ids' => [$b2->id]],
        ]);
        $this->actingAs($owner)
            ->postJson("/user/links/{$bio->id}/slides", $payload)
            ->assertOk()
            ->assertJson(['ok' => true, 'is_published' => true]);

        $deck = LinkSlideDeck::withoutGlobalScope('workspace')
            ->where('link_id', $bio->id)->first();
        $this->assertNotNull($deck);
        $this->assertTrue((bool) $deck->is_published);
        $this->assertGreaterThan(0, (int) $deck->version);
        $this->assertIsArray($deck->published_snapshot);
        $this->assertCount(2, $deck->published_snapshot['slides']);

        // 3. Public alias renders the slides deck with both slides + the
        // inline view tracker (the script that POSTs to /sl/{alias}/view).
        $resp = $this->get('/' . $bio->alias);
        $resp->assertOk();
        $body = $resp->getContent();
        $this->assertStringContainsString('Slide One', $body);
        $this->assertStringContainsString('Slide Two', $body);
        $this->assertStringContainsString('First slide body copy', $body);
        $this->assertStringContainsString('Second slide body copy', $body);
        // Two .sl-slide sections + the tracker URL the inline JS pings.
        $this->assertSame(2, substr_count($body, 'class="sl-slide"'));
        $this->assertStringContainsString("/sl/' + encodeURIComponent(ALIAS) + '/view", $body);

        // 4. Hitting /sl/{alias}/view persists a LinkSlideViewEvent.
        $this->postJson("/sl/{$bio->alias}/view", [
            'slide_index'     => 1,
            'page_session_id' => 'sl_test_session_x',
            'completed'       => true,
        ])->assertOk()->assertJson(['ok' => true, 'tracked' => true]);

        $event = LinkSlideViewEvent::where('link_id', $bio->id)->first();
        $this->assertNotNull($event);
        $this->assertSame(1, (int) $event->slide_index);
        $this->assertTrue((bool) $event->completed);
        $this->assertSame('web', (string) $event->source);
        $this->assertSame((int) $deck->id, (int) $event->deck_id);
    }

    public function test_mobile_api_returns_slides_payload_and_records_view(): void
    {
        $owner = $this->makeUser();
        $bio   = $this->makeBiolink($owner);
        [$b1, $b2] = $this->makeTwoBlocks($bio);

        // Bring the deck up to a published state via the editor save
        // endpoint so we exercise the same publish path the web test
        // relies on.
        $this->actingAs($owner)
            ->postJson("/user/links/{$bio->id}/slides/toggle", ['enabled' => true])
            ->assertOk();
        $this->actingAs($owner)
            ->postJson("/user/links/{$bio->id}/slides", $this->savePayload([
                ['title' => 'Mobile Slide A', 'block_ids' => [$b1->id]],
                ['title' => 'Mobile Slide B', 'block_ids' => [$b2->id]],
            ]))
            ->assertOk();

        // 1. GET /api/v1/biolinks/{alias} — shape must match SlidesPayload
        // in artifacts/1inme-mobile/lib/api/biolinks.ts.
        $resp = $this->getJson("/api/v1/biolinks/{$bio->alias}");
        $resp->assertOk();
        $resp->assertJsonStructure([
            'data' => [
                'biolink' => ['id', 'alias', 'title', 'mode'],
                'slides'  => [
                    'deck_id', 'version', 'settings',
                    'slides' => [
                        '*' => [
                            'id', 'sort_order', 'title', 'background',
                            'animation', 'transition', 'blocks',
                        ],
                    ],
                ],
            ],
        ]);

        $data = $resp->json('data');
        $this->assertSame('slides', $data['biolink']['mode']);
        $this->assertCount(2, $data['slides']['slides']);
        $this->assertSame('Mobile Slide A', $data['slides']['slides'][0]['title']);
        $this->assertSame('Mobile Slide B', $data['slides']['slides'][1]['title']);

        // Mobile viewer renders blocks natively — server must NOT include
        // the prerendered HTML field that the web snapshot carries.
        $firstBlock = $data['slides']['slides'][0]['blocks'][0];
        $this->assertArrayHasKey('id', $firstBlock);
        $this->assertArrayHasKey('type', $firstBlock);
        $this->assertArrayHasKey('settings', $firstBlock);
        $this->assertArrayNotHasKey('html', $firstBlock);
        $this->assertSame((int) $b1->id, (int) $firstBlock['id']);
        $this->assertSame('paragraph', (string) $firstBlock['type']);

        // 2. POST /api/v1/biolinks/{alias}/slides/view records a mobile
        // source event. Round-trips the trackSlideView() call.
        $this->postJson("/api/v1/biolinks/{$bio->alias}/slides/view", [
            'slide_index'     => 0,
            'page_session_id' => 'mobile_session_y',
            'completed'       => false,
        ])->assertOk()->assertJsonPath('data.tracked', true);

        $event = LinkSlideViewEvent::where('link_id', $bio->id)->first();
        $this->assertNotNull($event);
        $this->assertSame(0, (int) $event->slide_index);
        $this->assertSame('mobile_app', (string) $event->source);
        $this->assertSame('mobile_session_y', (string) $event->page_session_id);
    }

    public function test_draft_save_keeps_public_snapshot_but_signed_preview_reflects_draft(): void
    {
        $owner = $this->makeUser();
        $bio   = $this->makeBiolink($owner);
        [$b1, $b2] = $this->makeTwoBlocks($bio);

        // 1. Publish v1 with one slide titled "Published Title".
        $this->actingAs($owner)
            ->postJson("/user/links/{$bio->id}/slides/toggle", ['enabled' => true])
            ->assertOk();
        $this->actingAs($owner)
            ->postJson("/user/links/{$bio->id}/slides", $this->savePayload([
                ['title' => 'Published Title', 'block_ids' => [$b1->id]],
            ], true))
            ->assertOk();

        $deck = LinkSlideDeck::withoutGlobalScope('workspace')
            ->where('link_id', $bio->id)->first();
        $publishedVersion = (int) $deck->version;

        // 2. Save a draft (is_published=false) that swaps the deck to a
        // brand-new slide titled "Draft Title". This rewrites the live
        // editor tables but must NOT bump the published_snapshot.
        $this->actingAs($owner)
            ->postJson("/user/links/{$bio->id}/slides", $this->savePayload([
                ['title' => 'Draft Title', 'block_ids' => [$b2->id]],
            ], false))
            ->assertOk()
            ->assertJson(['ok' => true, 'is_published' => true]); // still flagged published from v1

        $deck->refresh();
        $this->assertSame($publishedVersion, (int) $deck->version,
            'draft save must not bump the published version');
        $snapshot = $deck->published_snapshot;
        $this->assertIsArray($snapshot);
        $this->assertCount(1, $snapshot['slides']);
        $this->assertSame('Published Title', $snapshot['slides'][0]['title'],
            'public snapshot must still hold the previously-published slide');

        // 3. Public alias still shows the published title, never the draft.
        $publicResp = $this->get('/' . $bio->alias);
        $publicResp->assertOk();
        $publicBody = $publicResp->getContent();
        $this->assertStringContainsString('Published Title', $publicBody);
        $this->assertStringNotContainsString('Draft Title', $publicBody);

        // 4. Signed owner-preview URL builds the view from the live editor
        // tables (the draft) instead of the frozen snapshot, so the new
        // "Draft Title" must appear and "Published Title" must not.
        $previewUrl = URL::temporarySignedRoute(
            'redirect.handle',
            now()->addHours(1),
            ['alias' => $bio->alias, '_preview' => 1, '_slides_preview' => 1],
            false,
        );
        $previewResp = $this->get($previewUrl);
        $previewResp->assertOk();
        $previewBody = $previewResp->getContent();
        $this->assertStringContainsString('Draft Title', $previewBody);
        $this->assertStringNotContainsString('Published Title', $previewBody);
    }
}
