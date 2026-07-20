<?php

namespace Tests\Feature;

use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Support\ContactPrivacy;
use App\Modules\User\Support\OnboardingSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #3831 — lockstep guard between the visible onboarding stepper
 * ({@see \App\Modules\User\Support\OnboardingSteps::forUser}) and the actual
 * redirect gate ({@see \App\Modules\User\Middleware\RedirectToOnboarding}).
 *
 * Both read the same `whatsappPending` / `privacyPending` predicates but from
 * two separate code paths. Task #3821 pinned the redirect side; nothing yet
 * asserts the stepper adds/drops the optional WhatsApp and privacy stages in
 * lockstep with what the gate will actually route a recently-onboarded user
 * to. A future edit could make the stepper promise a "Step 3 of 4" stage the
 * user is never redirected to (or silently hide one they will be), a
 * confusing UX with no test failure.
 *
 * These tests pin that agreement: for a freshly onboarded user (inside the
 * 14-day recency window, so the nudges are eligible) the stepper contains the
 * `whatsapp` stage exactly when the dashboard gate 302s to the WhatsApp step,
 * and the `privacy` stage exactly when it 302s to the privacy step — and both
 * drop the stage together once the step is shown / a number exists / privacy
 * is configured.
 *
 * @see \App\Modules\User\Support\OnboardingSteps
 * @see \App\Modules\User\Middleware\RedirectToOnboarding
 * @see \Tests\Feature\DashboardOnboardingGateRegressionTest
 */
class OnboardingStepperGateLockstepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an active, already-onboarded user. `onboarded_at` defaults to
     * "just now" (inside the 14-day recency window) so the WhatsApp/privacy
     * nudges are eligible to fire from the gate.
     */
    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    /** Stamp the creator-profile step as already shown, as the step handler does. */
    private function markCreatorProfileStepShown(User $user): void
    {
        $settings = $user->settings ?? [];
        $settings['creator_profile_step_shown_at'] = now()->toIso8601String();
        $user->forceFill(['settings' => $settings])->save();
    }

    /** Stamp the one-time WhatsApp step as already shown, as the step handlers do. */
    private function markWhatsappStepShown(User $user): void
    {
        $settings = $user->settings ?? [];
        $settings['whatsapp_step_shown_at'] = now()->toIso8601String();
        $user->forceFill(['settings' => $settings])->save();
    }

    /** Attach a verified phone identifier so `hasWhatsappNumber()` returns true. */
    private function giveVerifiedWhatsappNumber(User $user): void
    {
        LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'phone',
            'value'       => LinkedIdentifier::normalize('phone', '+15551234567'),
            'verified_at' => now(),
            // Not primary: `User::created` already attaches a primary verified
            // email identifier, and `linked_identifiers_one_primary_per_user`
            // allows only one primary per user. `hasWhatsappNumber()` only
            // needs a verified phone, not the primary flag.
            'is_primary'  => false,
        ]);
    }

    /** Whether the visible stepper for this user contains the given stage key. */
    private function stepperHasStage(User $user, string $key): bool
    {
        foreach (OnboardingSteps::forUser($user) as $step) {
            if (($step['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /** Whether a dashboard GET is 302-redirected to exactly the given route by the gate. */
    private function gateRedirectsTo(User $user, string $route): bool
    {
        $response = $this->actingAs($user->fresh())->get(route('user.dashboard'));

        return $response->isRedirect()
            && $response->headers->get('Location') === $route;
    }

    /**
     * Assert the stepper's `creator_profile` stage and the gate's creator-profile
     * redirect agree, and that both match the expected presence.
     */
    private function assertCreatorProfileLockstep(User $user, bool $expected): void
    {
        $stepperHas = $this->stepperHasStage($user, 'creator_profile');
        $gateGoes   = $this->gateRedirectsTo($user, route('user.onboarding.creator-profile'));

        $this->assertSame(
            $expected,
            $stepperHas,
            'Stepper creator_profile stage presence did not match the expectation.'
        );
        $this->assertSame(
            $stepperHas,
            $gateGoes,
            'Stepper and redirect gate disagree on the creator_profile stage.'
        );
    }

    /**
     * Assert the stepper's `whatsapp` stage and the gate's WhatsApp redirect
     * agree, and that both match the expected presence.
     * Caller must have already cleared the creator_profile step so the gate
     * is not intercepted before it reaches the WhatsApp check.
     */
    private function assertWhatsappLockstep(User $user, bool $expected): void
    {
        $stepperHas = $this->stepperHasStage($user, 'whatsapp');
        $gateGoes = $this->gateRedirectsTo($user, route('user.onboarding.whatsapp'));

        $this->assertSame(
            $expected,
            $stepperHas,
            'Stepper WhatsApp stage presence did not match the expectation.'
        );
        $this->assertSame(
            $stepperHas,
            $gateGoes,
            'Stepper and redirect gate disagree on the WhatsApp stage.'
        );
    }

    /**
     * Assert the stepper's `privacy` stage and the gate's privacy redirect
     * agree, and that both match the expected presence.
     * Caller must have already cleared both creator_profile and WhatsApp steps.
     */
    private function assertPrivacyLockstep(User $user, bool $expected): void
    {
        $stepperHas = $this->stepperHasStage($user, 'privacy');
        $gateGoes = $this->gateRedirectsTo($user, route('user.onboarding.privacy'));

        $this->assertSame(
            $expected,
            $stepperHas,
            'Stepper privacy stage presence did not match the expectation.'
        );
        $this->assertSame(
            $stepperHas,
            $gateGoes,
            'Stepper and redirect gate disagree on the privacy stage.'
        );
    }

    public function test_stepper_creator_profile_stage_matches_gate_redirect(): void
    {
        // 1) Freshly onboarded, step never shown → the stepper carries the
        //    creator_profile stage AND the gate routes there.
        $user = $this->makeUser();
        $this->assertCreatorProfileLockstep($user, true);

        // 2) Once the step is stamped as shown, the stage drops and the gate
        //    stops routing there — in lockstep.
        $this->markCreatorProfileStepShown($user);
        $this->assertCreatorProfileLockstep($user->fresh(), false);
    }

    public function test_stepper_whatsapp_stage_matches_gate_redirect(): void
    {
        // Creator-profile step must be cleared first — the gate checks it
        // before the WhatsApp check, so without this the redirect never
        // reaches the WhatsApp route and the assertions would disagree.
        $user = $this->makeUser();
        $this->markCreatorProfileStepShown($user);

        // 1) Freshly onboarded (creator_profile cleared), no verified number,
        //    WhatsApp step never shown → stepper carries 'whatsapp' AND the
        //    gate routes there.
        $this->assertFalse($user->fresh()->hasWhatsappNumber());
        $this->assertWhatsappLockstep($user->fresh(), true);

        // 2) Once the one-time step is stamped as shown, the stage drops and
        //    the gate stops routing there — in lockstep.
        $this->markWhatsappStepShown($user);
        $this->assertWhatsappLockstep($user->fresh(), false);

        // 3) A user who already has a verified number never sees the stage,
        //    and the gate never routes there either.
        $withNumber = $this->makeUser();
        $this->markCreatorProfileStepShown($withNumber);
        $this->giveVerifiedWhatsappNumber($withNumber);
        $this->assertTrue($withNumber->fresh()->hasWhatsappNumber());
        $this->assertWhatsappLockstep($withNumber->fresh(), false);
    }

    public function test_stepper_privacy_stage_matches_gate_redirect(): void
    {
        // Both creator_profile and WhatsApp steps must be cleared — the gate
        // checks them in order before it reaches the privacy check.
        $user = $this->makeUser();
        $this->markCreatorProfileStepShown($user);
        $this->markWhatsappStepShown($user);

        // 1) Privacy never configured → the stepper carries the privacy stage
        //    AND the gate routes there.
        $this->assertNull(ContactPrivacy::forUser($user->fresh())['configured_at']);
        $this->assertPrivacyLockstep($user->fresh(), true);

        // 2) Once privacy is configured, the stage drops and the gate stops
        //    routing there — in lockstep.
        ContactPrivacy::markConfigured($user->fresh());
        $this->assertNotNull(ContactPrivacy::forUser($user->fresh())['configured_at']);
        $this->assertPrivacyLockstep($user->fresh(), false);
    }
}
