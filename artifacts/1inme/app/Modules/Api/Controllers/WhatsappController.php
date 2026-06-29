<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Services\AccountMergeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Bearer-token mirror of the web WhatsApp connect step
 * (OnboardingController::whatsappSend / whatsappVerify) so a mobile creator
 * can add + verify a WhatsApp number without leaving the app. This unblocks
 * the mobile WhatsApp alert toggles (form submissions + payment events) which
 * are gated on a verified WhatsApp number.
 *
 * The web flow stashes the pending number in the session between Send and
 * Verify; the stateless API instead carries the number back on Verify, so the
 * client passes both `mobile` and `code`. The underlying OTP tuple (type
 * `mobile`, purpose `link`, guard `web`) is identical to the web flow, so a
 * code issued by either surface is interchangeable.
 */
class WhatsappController extends Controller
{
    use ApiResponses;

    /** Send a 6-digit verification code over WhatsApp to the entered number. */
    public function send(Request $request)
    {
        $request->validate(['mobile' => 'required|string|max:32']);

        $user  = $request->user();
        $value = LinkedIdentifier::normalize('phone', (string) $request->input('mobile'));

        if ($value === '') {
            return $this->fail('Enter a valid WhatsApp number.', 422, 'invalid_number');
        }

        // Reject if already attached to *any* account (mirrors the web flow and
        // the linked-identifier add flow so the unique constraint can never 500).
        $existing = LinkedIdentifier::where('kind', 'phone')->where('value', $value)->first();
        if ($existing) {
            return $this->fail(
                $existing->user_id === $user->id
                    ? 'That number is already linked to your account.'
                    : 'That number is already linked to another account.',
                422,
                'number_in_use',
            );
        }

        $otp  = new OtpService();
        $code = $otp->generate($value, 'mobile', 'link', 'web', $request->ip());
        try {
            $otp->sendWhatsApp($value, $code);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp connect OTP send failed (api): ' . $e->getMessage());
        }

        return $this->ok([
            'sent'        => true,
            'mobile'      => $value,
            'demo_reveal' => AuthMethods::demoRevealMessage($code),
        ]);
    }

    /** Verify the code and link the WhatsApp number to this account. */
    public function verify(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|max:32',
            'code'   => 'required|string|size:6',
        ]);

        $user  = $request->user();
        $value = LinkedIdentifier::normalize('phone', (string) $request->input('mobile'));

        if ($value === '') {
            return $this->fail('Enter a valid WhatsApp number.', 422, 'invalid_number');
        }

        $otp = new OtpService();
        if (!$otp->verify($value, (string) $request->input('code'), 'mobile', 'link', 'web')) {
            return $this->fail('Invalid or expired code. Please request a new one.', 422, 'invalid_code');
        }

        // Re-check under the unique constraint (idempotent for this user, a clear
        // error if someone else linked it between Send and Verify).
        $existing = LinkedIdentifier::where('kind', 'phone')->where('value', $value)->first();
        if ($existing && $existing->user_id !== $user->id) {
            return $this->fail('That number was just linked to another account.', 422, 'number_in_use');
        }
        if (!$existing) {
            LinkedIdentifier::create([
                'user_id'     => $user->id,
                'kind'        => 'phone',
                'value'       => $value,
                'verified_at' => now(),
                'is_primary'  => false,
            ]);
        } elseif (!$existing->verified_at) {
            $existing->forceFill(['verified_at' => now()])->save();
        }

        return $this->ok([
            'has_whatsapp_number' => true,
            'mobile'              => $value,
        ]);
    }

    /**
     * Report the currently connected WhatsApp number (masked) so the mobile
     * settings surface can show what is on file and whether it can be removed.
     * `can_remove` mirrors the web linked-identifier guards (you can't remove a
     * primary identifier or the last verified email/phone), surfacing the same
     * reason up-front instead of only failing on the disconnect call.
     */
    public function status(Request $request)
    {
        $user = $request->user();

        $identifier = $user->linkedIdentifiers()
            ->where('kind', 'phone')
            ->whereNotNull('verified_at')
            ->first();

        $reason = $identifier ? $this->removeBlockedReason($user, $identifier) : null;

        return $this->ok([
            'has_whatsapp_number'   => (bool) $identifier,
            'mobile_masked'         => $user->maskedWhatsappNumber(),
            'can_remove'            => $identifier ? $reason === null : false,
            'remove_blocked_reason' => $reason,
        ]);
    }

    /**
     * Disconnect the verified WhatsApp number from this account. Mirrors the web
     * disconnect path (Account Settings → linked identifiers → remove), reusing
     * AccountMergeService::unlink so the same guards apply (can't drop a primary
     * identifier or the last verified email/phone). Once removed the dependent
     * alert toggles can no longer deliver, so they read as disabled (gated on a
     * verified number) the moment the client refetches.
     */
    public function disconnect(Request $request, AccountMergeService $service)
    {
        $user = $request->user();

        $identifier = $user->linkedIdentifiers()
            ->where('kind', 'phone')
            ->whereNotNull('verified_at')
            ->first();

        if (!$identifier) {
            return $this->fail('No WhatsApp number is connected.', 422, 'no_number');
        }

        try {
            $service->unlink($user, $identifier);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422, 'cannot_remove');
        }

        return $this->ok([
            'has_whatsapp_number' => false,
            'mobile_masked'       => null,
        ]);
    }

    /**
     * The reason a verified phone identifier cannot be removed, or null when it
     * can. Pre-flights the AccountMergeService::unlink guards so the client can
     * disable the Remove action with an explanation rather than discovering the
     * block only on the DELETE call.
     */
    private function removeBlockedReason($user, LinkedIdentifier $identifier): ?string
    {
        if ($identifier->is_primary) {
            return 'This is your primary number — make another verified email or phone primary first.';
        }

        $hasOtherContact = $user->verifiedIdentifiers()
            ->where('id', '!=', $identifier->id)
            ->whereIn('kind', ['email', 'phone'])
            ->exists();

        if (!$hasOtherContact) {
            return 'You must keep at least one verified email or phone on your account.';
        }

        return null;
    }
}
