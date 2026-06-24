<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the web Google "Sign in with Google" OAuth flow
 * (SocialOAuthController::callback in login mode) and the inline
 * merge-offer accept/decline endpoints.
 *
 * The login-mode branch logic is:
 *   resolve by social identity -> resolve by email (link) -> create account
 *
 * Google is the only provider that returns a verified email from
 * fetchProfile() (scope "openid email profile"), so it is the only provider
 * that reaches the email-match / auto-create branches. Every HTTP call to
 * Google is faked, so the rest of the flow runs end-to-end without leaving
 * the test sandbox.
 */
class GoogleOAuthLoginMergeOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);

        // Pin the Google OAuth client creds so fetchProfile() has concrete
        // values for the token exchange. Both $_ENV and putenv() are set to
        // mirror the existing OAuth callback tests (env() reads either).
        $_ENV['GOOGLE_CLIENT_ID']     = 'gid.apps.googleusercontent.com';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'gsecret';
        putenv('GOOGLE_CLIENT_ID=gid.apps.googleusercontent.com');
        putenv('GOOGLE_CLIENT_SECRET=gsecret');

        // A default free plan so brand-new-account creation can stamp plan_id.
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    private function makeUser(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    /**
     * Fake Google's token-exchange + userinfo endpoints so fetchProfile()
     * returns the given identity without leaving the test.
     */
    private function fakeGoogle(string $sub, string $email, string $name = 'Google User'): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'gtok'], 200),
            'www.googleapis.com/oauth2/v2/userinfo*' => Http::response([
                'id'    => $sub,
                'email' => $email,
                'name'  => $name,
            ], 200),
        ]);
    }

    /** Drive the login-mode callback with a valid state already in session. */
    private function loginCallback()
    {
        return $this->withSession([
            'social_oauth_state_google' => 'st-abc',
            'social_oauth_mode_google'  => 'login',
        ])->get('/user/social-oauth/google/callback?state=st-abc&code=auth-code');
    }

    // ---------------------------------------------------------------- login mode

    public function test_login_mode_signs_in_user_resolved_by_existing_identity(): void
    {
        $user = $this->makeUser(['email' => 'linked@example.com']);
        LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'social',
            'value'       => LinkedIdentifier::normalize('social', '', 'google', 'g-existing'),
            'provider'    => 'google',
            'external_id' => 'g-existing',
            'verified_at' => now(),
        ]);

        // Even though the faked Google email matches no second account, the
        // identity resolution wins first — confirming the branch ordering.
        $this->fakeGoogle('g-existing', 'linked@example.com');

        $resp = $this->loginCallback();

        $resp->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_login_mode_links_identity_to_account_matched_by_email(): void
    {
        // Existing account whose email matches the Google profile email, but
        // which has no Google social identity yet. The callback must bind a
        // new google LinkedIdentifier to it and sign that account in.
        $user = $this->makeUser(['email' => 'match@example.com']);

        $this->assertDatabaseMissing('linked_identifiers', [
            'user_id'  => $user->id,
            'kind'     => 'social',
            'provider' => 'google',
        ]);

        $this->fakeGoogle('g-bymatch', 'match@example.com', 'Matched Person');

        $resp = $this->loginCallback();

        $resp->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        // The google identity is now bound to the existing account.
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id'     => $user->id,
            'kind'        => 'social',
            'provider'    => 'google',
            'external_id' => 'g-bymatch',
        ]);
        // No second account was created.
        $this->assertSame(1, User::count());
    }

    public function test_login_mode_email_match_is_case_insensitive(): void
    {
        // Account email is lower-case; Google returns a mixed-case email.
        // fetchProfile() lower-cases the email, so the existing account must
        // still be matched (and not duplicated).
        $user = $this->makeUser(['email' => 'casey@example.com']);

        $this->fakeGoogle('g-case', 'Casey@Example.com');

        $resp = $this->loginCallback();

        $resp->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id'     => $user->id,
            'provider'    => 'google',
            'external_id' => 'g-case',
        ]);
    }

    public function test_login_mode_creates_a_brand_new_account_when_no_match(): void
    {
        // No existing identity, no existing email — the callback must create
        // a fresh free-plan account, bind the google identity, and sign in.
        $this->assertSame(0, User::count());

        $this->fakeGoogle('g-fresh', 'newcomer@example.com', 'Fresh Newcomer');

        $resp = $this->loginCallback();

        $resp->assertRedirect(route('user.dashboard'));

        $created = User::where('email', 'newcomer@example.com')->first();
        $this->assertNotNull($created, 'a new account should have been created');
        $this->assertAuthenticatedAs($created);

        $free = Plan::where('slug', 'free')->first();
        $this->assertSame($free->id, $created->plan_id, 'new account gets the default free plan');
        $this->assertNotNull($created->email_verified_at, 'email is treated as verified');

        $this->assertDatabaseHas('linked_identifiers', [
            'user_id'     => $created->id,
            'kind'        => 'social',
            'provider'    => 'google',
            'external_id' => 'g-fresh',
        ]);
    }

    public function test_login_mode_without_email_and_no_identity_redirects_to_login(): void
    {
        // Simulate a provider profile with an id we don't recognise and no
        // email at all — the email-based create branch must NOT fire, and the
        // visitor falls through to the "no linked account" login error.
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'gtok'], 200),
            'www.googleapis.com/oauth2/v2/userinfo*' => Http::response([
                'id'   => 'g-unknown',
                'name' => 'Nameless',
            ], 200),
        ]);

        $resp = $this->loginCallback();

        $resp->assertRedirect(route('user.login'));
        $resp->assertSessionHas('error');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    // ---------------------------------------------------------- merge offer flow

    public function test_accept_merge_offer_seeds_session_and_redirects_to_preview(): void
    {
        $primary = $this->makeUser(['email' => 'primary@example.com']);
        $other   = $this->makeUser(['email' => 'other@example.com']);

        $resp = $this->actingAs($primary)
            ->withSession(['social_merge_offer' => [
                'secondary_id' => $other->id,
                'provider'     => 'google',
                'label'        => $other->email,
            ]])
            ->post(route('user.social-oauth.merge-offer.accept'));

        $resp->assertRedirect(route('user.merge.preview'));
        $resp->assertSessionHas('merge_secondary_id', $other->id);
        $resp->assertSessionHas('merge_primary_id', $primary->id);
        $resp->assertSessionHas('merge_challenge_active', true);
        // The one-shot offer is consumed.
        $resp->assertSessionMissing('social_merge_offer');
    }

    public function test_accept_merge_offer_with_expired_offer_errors_out(): void
    {
        $primary = $this->makeUser();

        $resp = $this->actingAs($primary)
            ->post(route('user.social-oauth.merge-offer.accept'));

        $resp->assertRedirect(route('user.social-accounts.index'));
        $resp->assertSessionHas('error');
        $resp->assertSessionMissing('merge_secondary_id');
    }

    public function test_accept_merge_offer_when_secondary_account_gone_errors_out(): void
    {
        $primary = $this->makeUser();
        // secondary_id points at a non-existent account.
        $resp = $this->actingAs($primary)
            ->withSession(['social_merge_offer' => [
                'secondary_id' => 999999,
                'provider'     => 'google',
                'label'        => 'gone',
            ]])
            ->post(route('user.social-oauth.merge-offer.accept'));

        $resp->assertRedirect(route('user.social-accounts.index'));
        $resp->assertSessionHas('error');
        $resp->assertSessionMissing('merge_secondary_id');
    }

    public function test_decline_merge_offer_clears_offer_and_leaves_accounts_separate(): void
    {
        $primary = $this->makeUser(['email' => 'primary@example.com']);
        $other   = $this->makeUser(['email' => 'other@example.com']);

        $resp = $this->actingAs($primary)
            ->withSession(['social_merge_offer' => [
                'secondary_id' => $other->id,
                'provider'     => 'google',
                'label'        => $other->email,
            ]])
            ->post(route('user.social-oauth.merge-offer.decline'));

        $resp->assertRedirect(route('user.social-accounts.index'));
        $resp->assertSessionHas('status');
        $resp->assertSessionMissing('social_merge_offer');
        // No merge session was seeded; both accounts remain.
        $resp->assertSessionMissing('merge_secondary_id');
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $primary->id]);
    }
}
