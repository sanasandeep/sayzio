<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\LinkedIdentifier;
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
}
