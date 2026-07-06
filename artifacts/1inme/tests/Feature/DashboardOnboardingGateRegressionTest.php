<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\ContactPrivacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3821 — regression guard for the dashboard onboarding soft gate
 * ({@see \App\Modules\User\Middleware\RedirectToOnboarding}).
 *
 * The gate 302-redirects the dashboard to the one-time WhatsApp connect step
 * and then the one-time contact-privacy step, but ONLY for users onboarded
 * within the last 14 days. Two silent, user-visible regressions are possible
 * if a future edit moves the recency window or the WhatsApp/privacy
 * predicates:
 *   - established users could be trapped bouncing off the dashboard forever;
 *   - genuinely new users could stop seeing the one-time nudges at all.
 *
 * These tests pin the boundary: a freshly onboarded user sees WhatsApp once,
 * then privacy, then the dashboard; an established user is never
 * force-redirected; and `whatsapp_step_shown_at` makes the WhatsApp redirect
 * fire only once.
 *
 * @see \App\Modules\User\Middleware\RedirectToOnboarding
 * @see \App\Modules\User\Support\OnboardingSteps
 */
class DashboardOnboardingGateRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an active, already-onboarded user. `onboarded_at` defaults to
     * "just now" (inside the 14-day recency window) so the WhatsApp/privacy
     * nudges are eligible to fire; individual tests override it to place the
     * account on either side of the boundary.
     */
    private function makeUser(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'         => 'U ' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'onboarded_at' => now(),
        ], $attrs));
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    /** Stamp the one-time WhatsApp step as already shown, as the step handlers do. */
    private function markWhatsappStepShown(User $user): void
    {
        $settings = $user->settings ?? [];
        $settings['whatsapp_step_shown_at'] = now()->toIso8601String();
        $user->forceFill(['settings' => $settings])->save();
    }

    public function test_freshly_onboarded_user_sees_whatsapp_then_privacy_then_dashboard(): void
    {
        // Just onboarded, no verified WhatsApp number, privacy not yet chosen.
        $user = $this->makeUser();

        $this->assertFalse($user->hasWhatsappNumber());
        $this->assertNull(ContactPrivacy::forUser($user->fresh())['configured_at']);

        // 1) First dashboard hit → one-time WhatsApp connect step.
        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertRedirect(route('user.onboarding.whatsapp'));

        // Skipping the step stamps `whatsapp_step_shown_at` so it never fires again.
        $this->actingAs($user->fresh())
            ->post(route('user.onboarding.whatsapp.skip'))
            ->assertRedirect(route('user.dashboard'));

        // 2) Next dashboard hit → the one-time contact-privacy step.
        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertRedirect(route('user.onboarding.privacy'));

        // Skipping privacy stamps `configured_at` so it never fires again.
        $this->actingAs($user->fresh())
            ->post(route('user.onboarding.privacy.skip'))
            ->assertRedirect(route('user.dashboard'));

        // 3) With both one-time steps out of the way → the dashboard renders.
        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertOk();
    }

    public function test_user_onboarded_more_than_14_days_ago_is_never_force_redirected(): void
    {
        // Established account (past the 14-day recency window) with no WhatsApp
        // number and privacy never configured — i.e. it would match both nudge
        // predicates if not for the recency gate.
        $user = $this->makeUser(['onboarded_at' => now()->subDays(15)]);

        $this->assertFalse($user->hasWhatsappNumber());
        $this->assertNull(ContactPrivacy::forUser($user->fresh())['configured_at']);

        // The dashboard loads directly — no WhatsApp/privacy redirect.
        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertOk();
    }

    public function test_whatsapp_step_shown_stamp_makes_the_redirect_fire_only_once(): void
    {
        // Freshly onboarded, no WhatsApp number, but privacy already configured
        // so the WhatsApp redirect is the only thing that could fire — this
        // isolates the `whatsapp_step_shown_at` gate.
        $user = $this->makeUser();
        ContactPrivacy::markConfigured($user);

        // Without the stamp, the first dashboard hit redirects to WhatsApp.
        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertRedirect(route('user.onboarding.whatsapp'));

        // Once the step has been shown, the WhatsApp redirect must not fire
        // again — even though the user still has no verified number and is
        // still inside the 14-day recency window. The dashboard now renders.
        $this->markWhatsappStepShown($user->fresh());

        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertOk();
    }
}
