<?php

namespace Tests\Feature;

use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Support\ContactPrivacy;
use App\Modules\User\Support\OnboardingSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3837 — lockstep guard between the REST onboarding-status payload
 * ({@see \App\Modules\Api\Controllers\OnboardingController::status}) and the
 * visible web onboarding stepper ({@see OnboardingSteps::forUser}), which is
 * itself already pinned to the redirect gate by
 * {@see OnboardingStepperGateLockstepTest}.
 *
 * The mobile setup flow (artifacts/1inme-mobile/app/setup.tsx) builds its
 * "Step X of Y" list from the status payload's `whatsapp_pending` /
 * `privacy_pending` flags. If those flags ever diverged from the same
 * predicates the web stepper/gate read, mobile would silently promise a stage
 * the API never asks for (or hide one it does) — the exact confusing
 * "Step X of Y that never appears" bug the web test guards against, but on the
 * mobile surface.
 *
 * These tests pin that agreement directly: for a range of user states the
 * status payload flags equal both the {@see OnboardingSteps} predicates AND
 * whether the visible stepper carries that optional stage.
 *
 * @see \App\Modules\Api\Controllers\OnboardingController::status
 * @see \App\Modules\User\Support\OnboardingSteps
 * @see \Tests\Feature\OnboardingStepperGateLockstepTest
 */
class OnboardingStatusStepLockstepTest extends TestCase
{
    use RefreshDatabase;

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

    /** Attach a verified phone identifier so `hasWhatsappNumber()` returns true. */
    private function giveVerifiedWhatsappNumber(User $user): void
    {
        // A distinct number per user — phone identifiers are globally unique,
        // so reusing one string across the several users this suite creates
        // would trip the unique constraint.
        $suffix = str_pad((string) ($user->id % 10000000), 7, '0', STR_PAD_LEFT);
        LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'phone',
            'value'       => LinkedIdentifier::normalize('phone', '+1555' . $suffix),
            'verified_at' => now(),
            'is_primary'  => false,
        ]);
    }

    /** Whether the visible web stepper for this user carries the given stage key. */
    private function stepperHasStage(User $user, string $key): bool
    {
        foreach (OnboardingSteps::forUser($user) as $step) {
            if (($step['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hit the REST onboarding-status endpoint as this user with a real bearer
     * token (Sanctum::actingAs breaks the token-touch middleware — see the
     * `sanctum-api-tests` memo) and return the decoded `data` payload.
     *
     * @return array<string, mixed>
     */
    private function statusPayload(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        // Feature tests fire several of these requests in a single PHP process;
        // the Sanctum guard memoizes the first resolved user, so without this
        // the second request would silently re-authenticate the previous user
        // (and read its state) instead of the token we just minted.
        $this->app['auth']->forgetGuards();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->getJson('/api/v1/onboarding');

        $response->assertOk();

        return $response->json('data');
    }

    /**
     * For the given user, assert the status payload's optional-stage flags are
     * in lockstep with BOTH the raw {@see OnboardingSteps} predicates and the
     * visible web stepper's stage membership.
     */
    private function assertPayloadInLockstep(User $user): void
    {
        $user = $user->fresh();
        $payload = $this->statusPayload($user);

        // 1) The payload flag equals the canonical server-side predicate.
        $this->assertSame(
            OnboardingSteps::whatsappPending($user),
            $payload['whatsapp_pending'],
            'status.whatsapp_pending must equal OnboardingSteps::whatsappPending().'
        );
        $this->assertSame(
            OnboardingSteps::privacyPending($user),
            $payload['privacy_pending'],
            'status.privacy_pending must equal OnboardingSteps::privacyPending().'
        );

        // 2) …and equals whether the visible web stepper carries that stage,
        //    so mobile (which builds its steps from the flags) can't drift from
        //    what web actually shows.
        $this->assertSame(
            $this->stepperHasStage($user, 'whatsapp'),
            $payload['whatsapp_pending'],
            'status.whatsapp_pending must match the web stepper WhatsApp stage.'
        );
        $this->assertSame(
            $this->stepperHasStage($user, 'privacy'),
            $payload['privacy_pending'],
            'status.privacy_pending must match the web stepper privacy stage.'
        );
    }

    public function test_whatsapp_pending_flag_tracks_the_stepper(): void
    {
        // Fresh user: no verified number, step never shown → WhatsApp pending.
        $user = $this->makeUser();
        $this->assertTrue($this->statusPayload($user)['whatsapp_pending']);
        $this->assertPayloadInLockstep($user);

        // Once the one-time step is stamped shown, the flag drops in lockstep.
        $this->markWhatsappStepShown($user);
        $this->assertFalse($this->statusPayload($user->fresh())['whatsapp_pending']);
        $this->assertPayloadInLockstep($user->fresh());

        // A user with a verified number never has the flag set.
        $withNumber = $this->makeUser();
        $this->giveVerifiedWhatsappNumber($withNumber);
        $this->assertFalse($this->statusPayload($withNumber->fresh())['whatsapp_pending']);
        $this->assertPayloadInLockstep($withNumber->fresh());
    }

    public function test_privacy_pending_flag_tracks_the_stepper(): void
    {
        // Never configured → privacy pending.
        $user = $this->makeUser();
        $this->assertTrue($this->statusPayload($user)['privacy_pending']);
        $this->assertPayloadInLockstep($user);

        // Once configured, the flag drops in lockstep.
        ContactPrivacy::markConfigured($user->fresh());
        $this->assertFalse($this->statusPayload($user->fresh())['privacy_pending']);
        $this->assertPayloadInLockstep($user->fresh());
    }

    public function test_flags_are_independent_across_the_four_state_combinations(): void
    {
        // Both pending (fresh user).
        $this->assertPayloadInLockstep($this->makeUser());

        // WhatsApp resolved, privacy still pending.
        $waDone = $this->makeUser();
        $this->giveVerifiedWhatsappNumber($waDone);
        $this->assertPayloadInLockstep($waDone->fresh());

        // Privacy resolved, WhatsApp still pending.
        $privacyDone = $this->makeUser();
        ContactPrivacy::markConfigured($privacyDone->fresh());
        $this->assertPayloadInLockstep($privacyDone->fresh());

        // Both resolved.
        $bothDone = $this->makeUser();
        $this->giveVerifiedWhatsappNumber($bothDone);
        ContactPrivacy::markConfigured($bothDone->fresh());
        $bothDone = $bothDone->fresh();
        $payload = $this->statusPayload($bothDone);
        $this->assertFalse($payload['whatsapp_pending']);
        $this->assertFalse($payload['privacy_pending']);
        $this->assertPayloadInLockstep($bothDone);
    }
}
