<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\PaidPageTemplates;
use App\Services\Monetization\PostAccessPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the standalone Paid Page link type (see
 * .agents/memory/paid-page-link-type.md). Asserts that:
 *   (a) creating a paid_page link seeds a valid default template into
 *       settings['paid_page'] and defaults the page to public;
 *   (b) the public render returns 200 for a public paid page and enforces
 *       sign-in (gated 401) for a registered-visibility one;
 *   (c) the dedicated editor persists the chosen template and flips the
 *       page-level visibility (public <-> registered);
 *   (d) per-post paywall/visibility still gates content on the paid page,
 *       reusing the shared PostAccessPolicy.
 *
 * Aliases are prefixed with a non-reserved letter ("zp"): the GET catch-all
 * /{alias} matcher rejects aliases that start with reserved single-letter
 * tokens like p/u/c/m/f (see the memory note).
 */
class PaidPageTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = [], ?string $slug = null): Plan
    {
        $slug = $slug ?: ('p' . Str::random(6));
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'h' . Str::lower(Str::random(10)),
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function alias(): string
    {
        return 'zp' . substr(Str::random(8), 0, 8);
    }

    /**
     * Hit a public page the way a real visitor does. The catch-all /{alias}
     * route has no SetActiveWorkspace middleware, so a genuine guest/visitor
     * request carries NO bound workspace and Link's workspace global scope is
     * skipped (see BelongsToWorkspace). Our setup helpers leave the last-built
     * user's workspace bound in the container, which would otherwise wrongly
     * scope resolveByAlias to that workspace and 404 the owner's link. Clear
     * the bindings so the lookup mirrors production.
     */
    private function visitPublic(string $alias, ?User $as = null)
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        $req = $as ? $this->actingAs($as) : $this;
        return $req->get('/' . $alias);
    }

    /** Create a paid_page link directly (mirrors the seeding done by store()). */
    private function paidPage(User $u, string $visibility = 'public', ?string $template = null): Link
    {
        return $u->links()->create([
            'user_id'    => $u->id,
            'type'       => Link::TYPE_PAID_PAGE,
            'alias'      => $this->alias(),
            'is_active'  => true,
            'visibility' => $visibility,
            'settings'   => ['paid_page' => ['template' => $template ?: PaidPageTemplates::DEFAULT_ID]],
        ]);
    }

    private function publishedPost(User $creator, array $attrs = []): CreatorPost
    {
        $post = (new CreatorPost)->forceFill(array_merge([
            'user_id'      => $creator->id,
            'workspace_id' => $creator->ownedWorkspaces()->first()->id,
            'post_type'    => CreatorPost::TYPE_TEXT,
            'visibility'   => CreatorPost::VISIBILITY_FREE,
            'published_at' => now()->subMinute(),
        ], $attrs));
        $post->saveQuietly();
        return $post;
    }

    // ===== (a) creation seeds a valid default template =====

    public function test_creating_paid_page_seeds_default_template_and_public_visibility(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));

        $this->actingAs($u)->post('/user/links', [
            'type'  => 'paid_page',
            'alias' => $this->alias(),
            'title' => 'My Paid Page',
        ]);

        $link = $u->links()->where('type', 'paid_page')->latest('id')->first();
        $this->assertNotNull($link);
        // Default template is seeded into settings['paid_page'].
        $this->assertSame(PaidPageTemplates::DEFAULT_ID, $link->settings['paid_page']['template'] ?? null);
        $this->assertContains($link->settings['paid_page']['template'], PaidPageTemplates::ids());
        // New paid pages default to public.
        $this->assertSame('public', $link->visibility);
    }

    public function test_creating_paid_page_honors_explicit_valid_template(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        // Pick a non-default template id from the catalog.
        $explicit = collect(PaidPageTemplates::ids())
            ->first(fn ($id) => $id !== PaidPageTemplates::DEFAULT_ID);
        $this->assertNotNull($explicit);

        $this->actingAs($u)->post('/user/links', [
            'type'               => 'paid_page',
            'alias'              => $this->alias(),
            'paid_page_template' => $explicit,
        ]);

        $link = $u->links()->where('type', 'paid_page')->latest('id')->first();
        $this->assertNotNull($link);
        $this->assertSame($explicit, $link->settings['paid_page']['template'] ?? null);
    }

    public function test_creating_paid_page_falls_back_to_default_for_invalid_template(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));

        // An unknown template is rejected by validation; the form-side default
        // template ('') still seeds the catalog default rather than 500ing.
        $this->actingAs($u)->post('/user/links', [
            'type'               => 'paid_page',
            'alias'              => $this->alias(),
            'paid_page_template' => '',
        ]);

        $link = $u->links()->where('type', 'paid_page')->latest('id')->first();
        $this->assertNotNull($link);
        $this->assertSame(PaidPageTemplates::DEFAULT_ID, $link->settings['paid_page']['template'] ?? null);
    }

    // ===== (b) public render: 200 public, gated for registered =====

    public function test_public_paid_page_renders_for_guest(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($u, 'public');
        $this->publishedPost($u, ['body' => 'FREE-POST-BODY-VISIBLE']);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertSee('FREE-POST-BODY-VISIBLE', false);
    }

    public function test_registered_paid_page_blocks_guest_with_gated_view(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($u, 'registered');

        // Guest hitting a registered-visibility paid page gets the gated view.
        $resp = $this->get('/' . $link->alias);
        $resp->assertStatus(401);
    }

    public function test_registered_paid_page_renders_for_signed_in_visitor(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($u, 'registered');
        $this->publishedPost($u, ['body' => 'GATED-PAGE-BODY']);

        // A signed-in (registered) non-owner visitor clears the page gate.
        $visitor = $this->user($this->plan(['max_links' => 100]));
        $resp = $this->visitPublic($link->alias, $visitor);
        $resp->assertOk();
    }

    public function test_owner_can_view_their_own_registered_paid_page(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($u, 'registered');

        // Owners always see their own page regardless of the visibility tier.
        $resp = $this->visitPublic($link->alias, $u);
        $resp->assertOk();
    }

    // ===== (c) editor persists template + flips visibility =====

    public function test_editor_update_persists_template_and_flips_visibility(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($u, 'public');

        $explicit = collect(PaidPageTemplates::ids())
            ->first(fn ($id) => $id !== PaidPageTemplates::DEFAULT_ID);

        // Flip public -> registered and switch template.
        $this->actingAs($u)->post('/user/links/' . $link->id . '/paid-page', [
            'template'  => $explicit,
            'is_public' => 0,
        ])->assertRedirect();

        $link->refresh();
        $this->assertSame($explicit, $link->settings['paid_page']['template'] ?? null);
        $this->assertSame('registered', $link->visibility);

        // Flip back registered -> public.
        $this->actingAs($u)->post('/user/links/' . $link->id . '/paid-page', [
            'template'  => PaidPageTemplates::DEFAULT_ID,
            'is_public' => 1,
        ])->assertRedirect();

        $link->refresh();
        $this->assertSame(PaidPageTemplates::DEFAULT_ID, $link->settings['paid_page']['template'] ?? null);
        $this->assertSame('public', $link->visibility);
    }

    public function test_editor_update_rejects_unknown_template(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($u, 'public', PaidPageTemplates::DEFAULT_ID);

        $this->actingAs($u)->post('/user/links/' . $link->id . '/paid-page', [
            'template'  => 'not-a-real-template',
            'is_public' => 1,
        ])->assertSessionHasErrors('template');

        // The original template is untouched after a rejected update.
        $link->refresh();
        $this->assertSame(PaidPageTemplates::DEFAULT_ID, $link->settings['paid_page']['template'] ?? null);
    }

    public function test_editor_rejects_non_owner(): void
    {
        $owner = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($owner, 'public');

        $other = $this->user($this->plan(['max_links' => 100]));
        $resp = $this->actingAs($other)->post('/user/links/' . $link->id . '/paid-page', [
            'template'  => PaidPageTemplates::DEFAULT_ID,
            'is_public' => 1,
        ]);
        // A non-owner is rejected: the link belongs to another workspace, so the
        // route-model binding's workspace scope hides it (404); even when the
        // binding resolves, the owner check aborts (403). Either is a rejection.
        $this->assertContains($resp->status(), [403, 404]);
        // The owner's link is untouched by the rejected request.
        $link->refresh();
        $this->assertSame('public', $link->visibility);
    }

    // ===== (d) per-post paywall still gates content on the paid page =====

    public function test_paid_page_gates_tier_locked_posts_per_viewer(): void
    {
        $creator = $this->user($this->plan(['max_links' => 100]));
        $link = $this->paidPage($creator, 'public');

        $tier = SubscriptionTier::create([
            'user_id'             => $creator->id,
            'name'                => 'Gold',
            'slug'                => 'gold' . Str::random(4),
            'is_free'             => false,
            'is_active'           => true,
            'sort_order'          => 1,
            'price_monthly_cents' => 500,
            'currency'            => 'USD',
        ]);

        $freePost = $this->publishedPost($creator, [
            'body'       => 'FREE-POST-BODY',
            'visibility' => CreatorPost::VISIBILITY_FREE,
        ]);
        $lockedPost = $this->publishedPost($creator, [
            'body'             => 'LOCKED-POST-BODY',
            'visibility'       => CreatorPost::VISIBILITY_TIER,
            'visible_tier_ids' => [$tier->id],
        ]);

        // A subscriber on that tier.
        $subscriber = $this->user($this->plan(['max_links' => 100]));
        CreatorSubscription::create([
            'fan_user_id'          => $subscriber->id,
            'creator_user_id'      => $creator->id,
            'tier_id'              => $tier->id,
            'status'               => CreatorSubscription::STATUS_ACTIVE,
            'billing_cycle'        => 'monthly',
            'price_cents'          => 500,
            'currency'             => 'USD',
            'started_at'           => now()->subDay(),
            'current_period_start' => now()->subDay(),
            'current_period_end'   => now()->addMonth(),
        ]);

        // PostAccessPolicy (the single source of truth the paid page reuses).
        $this->assertFalse(PostAccessPolicy::evaluate(null, $lockedPost)['can']);
        $this->assertTrue(PostAccessPolicy::evaluate($creator, $lockedPost)['can']);
        $this->assertTrue(PostAccessPolicy::evaluate($subscriber, $lockedPost)['can']);
        $this->assertTrue(PostAccessPolicy::evaluate(null, $freePost)['can']);

        // The paid page render carries that same per-post access map.
        $guestResp = $this->visitPublic($link->alias);
        $guestResp->assertOk();
        $guestAccess = $guestResp->viewData('accessByPost');
        $this->assertFalse($guestAccess[$lockedPost->id]['can']);
        $this->assertTrue($guestAccess[$freePost->id]['can']);

        $subResp = $this->visitPublic($link->alias, $subscriber);
        $subResp->assertOk();
        $subAccess = $subResp->viewData('accessByPost');
        $this->assertTrue($subAccess[$lockedPost->id]['can']);
    }
}
