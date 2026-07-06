<?php

namespace Tests\Feature;

use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\BiolinkPageRecipes;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Heavier end-to-end coverage that needs a fresh DB per test (RefreshDatabase
 * rebuilds every migration so we keep this in a separate class to avoid
 * slowing down the lighter assertions above).
 */
class BiolinkWizardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    /**
     * Mirror what `SetActiveWorkspace` would resolve at request time so
     * test-seeded drafts live in the same workspace the controller will
     * search when serving an HTTP request as this user.
     */
    private function activeWorkspaceId(User $user): ?int
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        return $ws?->id;
    }

    /**
     * End-to-end recipe → TemplateService produces correctly-keyed blocks
     * that survive the platform sanitizer (i.e. block settings are NOT
     * replaced with placeholder defaults).
     */
    public function test_finish_applies_recipe_and_creates_populated_blocks(): void
    {
        $user = $this->makeUser();

        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);

        $snapshot = BiolinkPageRecipes::build('creator', 'influencer', null, [
            'display_name' => 'Demo Creator',
            'tagline'      => 'Stories, art, and good vibes',
            'bio'          => 'Sharing my creative journey.',
            'links'        => [['label' => 'My Shop', 'url' => 'https://shop.example.com']],
            'socials'      => [['platform' => 'instagram', 'handle' => 'demo']],
            'cta_label'    => 'Subscribe',
            'cta_url'      => 'https://example.com/sub',
            'theme'        => 'dark',
        ]);

        $this->actingAs($user);
        app(TemplateService::class)->applyPageToLink($link, $snapshot, true);

        $blocks = BiolinkBlock::where('link_id', $link->id)->get();
        $this->assertNotEmpty($blocks, 'TemplateService should have created blocks from the recipe');

        $byType = $blocks->keyBy('type');
        $this->assertTrue($byType->has('profile_card_v1'), 'expected profile_card_v1 in created blocks');
        $this->assertTrue($byType->has('cta_button'), 'expected cta_button in created blocks');

        // Settings should carry the user's actual answers, not placeholders.
        $profile = $byType['profile_card_v1']->settings ?? [];
        $this->assertSame('Demo Creator', $profile['name'] ?? null);
        $this->assertNotSame('Your Name', $profile['name'] ?? null,
            'profile name should not be the default placeholder');

        $cta = $byType['cta_button']->settings ?? [];
        $this->assertSame('Subscribe', $cta['text'] ?? null);
        $this->assertSame('https://example.com/sub', $cta['url'] ?? null);
        $this->assertNotSame('Click Here', $cta['text'] ?? null,
            'cta button text should not be the default placeholder');
    }

    /** Browser autosave PATCH persists answers without bumping the step. */
    public function test_autosave_patch_merges_into_draft(): void
    {
        $user = $this->makeUser();

        // Seed an in-progress draft so the autosave endpoint has somewhere
        // to write into (the controller's draft endpoint is a no-op when no
        // draft exists yet — that's intentional).
        BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'industry'      => null,
            'step'          => 3,
            'answers'       => ['display_name' => 'Old Name'],
        ]);

        $resp = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch('/user/links/wizard/draft', [
                'answers' => ['display_name' => 'New Name', 'tagline' => 'Updated'],
            ]);

        $resp->assertNoContent();

        $draft = BiolinkWizardDraft::where('actor_user_id', $user->id)->first();
        $this->assertNotNull($draft);
        $this->assertSame('New Name', $draft->answers['display_name']);
        $this->assertSame('Updated', $draft->answers['tagline']);
        $this->assertSame(3, $draft->step, 'autosave must not advance the step');
    }

    /** Resume returns the same draft state across requests. */
    public function test_resume_loads_existing_draft(): void
    {
        $user = $this->makeUser();

        BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            // business_name is a basic profile field, so it renders on the
            // basics step (index 2) in the redesigned 4-step flow.
            'step'          => 2,
            'answers'       => ['business_name' => 'Resumed Co'],
        ]);

        $resp = $this->actingAs($user)->get('/user/links/wizard');
        $resp->assertOk();
        // The view renders draft state into the form; we don't assert exact
        // markup (it could change with redesigns), but the saved business
        // name should appear somewhere in the response payload.
        $body = $resp->getContent();
        $this->assertStringContainsString('Resumed Co', $body,
            'wizard index should rehydrate the in-progress draft into the rendered form');
    }

    /**
     * Two team members in the same workspace should each get their *own*
     * draft — never share or overwrite each other.
     */
    public function test_drafts_are_per_actor_not_per_workspace_owner(): void
    {
        $alice = $this->makeUser();
        $bob   = $this->makeUser();

        BiolinkWizardDraft::create([
            'user_id'       => $alice->id, // workspace owner
            'actor_user_id' => $alice->id,
            'workspace_id'  => $this->activeWorkspaceId($alice),
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'step'          => 3,
            'answers'       => ['display_name' => 'Alice'],
        ]);
        BiolinkWizardDraft::create([
            // In a real shared workspace `user_id` would be Alice (the owner)
            // and `workspace_id` would be Alice's workspace; here each test
            // user has their own personal workspace (no team seed) so we
            // mirror what `WorkspaceContext::resolve($bob)` would return —
            // the point of the test is that the lookup keys on actor.
            'user_id'       => $bob->id,
            'actor_user_id' => $bob->id,
            'workspace_id'  => $this->activeWorkspaceId($bob),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'step'          => 3,
            'answers'       => ['business_name' => 'Bob Inc'],
        ]);

        // Bob's autosave must only update his own row.
        $this->actingAs($bob)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch('/user/links/wizard/draft', ['answers' => ['business_name' => 'Bob Inc v2']])
            ->assertNoContent();

        $aliceDraft = BiolinkWizardDraft::where('actor_user_id', $alice->id)->first();
        $bobDraft   = BiolinkWizardDraft::where('actor_user_id', $bob->id)->first();

        $this->assertSame('Alice', $aliceDraft->answers['display_name']);
        $this->assertSame('Bob Inc v2', $bobDraft->answers['business_name']);
    }

    /**
     * A team member without `links.create` (e.g. a viewer) must NOT be
     * able to launch destructive wizard actions in the workspace owner's
     * context. The route is gated by `workspace.can:links.create`, which
     * returns 403 (HTML) / JSON for missing permissions.
     */
    public function test_viewer_role_cannot_start_or_finish_wizard(): void
    {
        $alice = $this->makeUser();
        $bob   = $this->makeUser();

        // Resolve Alice's personal workspace and add Bob as a viewer
        // (viewers have view=true, create=false in WorkspacePermissions).
        $aliceWs = app(WorkspaceContext::class)->resolve($alice);
        $this->assertNotNull($aliceWs, 'Alice should have a default workspace');
        WorkspaceMember::create([
            'workspace_id' => $aliceWs->id,
            'user_id'      => $bob->id,
            'role'         => 'viewer',
        ]);

        // Force Bob's session to point at Alice's workspace so the
        // SetActiveWorkspace middleware resolves *that* workspace, not
        // Bob's personal one (where he'd be the owner and bypass perms).
        $resp = $this->actingAs($bob)
            ->withSession([WorkspaceContext::SESSION_KEY => $aliceWs->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/user/links/wizard/start');

        $this->assertSame(403, $resp->status(),
            'viewer must be denied destructive wizard start');
        $this->assertSame('forbidden', $resp->json('error'));

        // Finish must also be denied.
        $resp2 = $this->actingAs($bob)
            ->withSession([WorkspaceContext::SESSION_KEY => $aliceWs->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/user/links/wizard/finish');

        $this->assertSame(403, $resp2->status(),
            'viewer must be denied wizard finish');
    }
}
