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
 * Bearer-token mirror of the web "Linked identifiers" Account Settings
 * section (LinkedIdentifierController): list every verified email, phone,
 * and social identity on the account, add + verify a new email/phone,
 * remove a non-primary one, and promote any verified email/phone to
 * primary.
 *
 * This generalises the narrow WhatsApp-only surface (WhatsappController):
 * the same AccountMergeService guards back remove + promote here, so the
 * mobile app can finally resolve the "can't remove the primary number"
 * block the WhatsApp disconnect flow surfaces but can't fix on its own —
 * by promoting another verified identifier to primary first.
 *
 * The web flow stashes the pending value in the session between Send and
 * Verify; the stateless API instead carries `kind` + `value` back on
 * Verify alongside the code. The underlying OTP tuple (type `mobile`/`email`,
 * purpose `link`, guard `web`) is identical to the web flow, so a code
 * issued by either surface is interchangeable.
 *
 * Social identities are listed (and can be promoted/removed via the same
 * guards, which refuse a social primary) but are NOT added here — social
 * linking stays on the dedicated OAuth connect flow.
 */
class IdentifierController extends Controller
{
    use ApiResponses;

    /** List all linked identifiers with per-row remove/promote eligibility. */
    public function index(Request $request)
    {
        $user = $request->user();

        $identifiers = $user->linkedIdentifiers()->get();

        $items = $identifiers->map(fn (LinkedIdentifier $i) => $this->present($user, $i, $identifiers))->all();

        return $this->ok([
            'identifiers' => $items,
            // The set of kinds the client may add via this surface. Social
            // identities are read-only here (added through OAuth connect).
            'addable_kinds' => ['email', 'phone'],
        ]);
    }

    /** Begin adding a new email or phone — sends a verification OTP. */
    public function send(Request $request)
    {
        $request->validate([
            'kind'  => 'required|in:email,phone',
            'value' => 'required|string|max:255',
        ]);

        $user  = $request->user();
        $kind  = (string) $request->input('kind');
        $value = LinkedIdentifier::normalize($kind, (string) $request->input('value'));

        if ($value === '') {
            return $this->fail('Enter a valid ' . $kind . '.', 422, 'invalid_value');
        }

        // Reject if already attached to *any* account (mirrors the web add
        // flow so the unique constraint can never 500).
        $existing = LinkedIdentifier::where('kind', $kind)->where('value', $value)->first();
        if ($existing) {
            return $this->fail(
                $existing->user_id === $user->id
                    ? 'That ' . $kind . ' is already linked to your account.'
                    : 'That ' . $kind . ' is already linked to another account.',
                422,
                $existing->user_id === $user->id ? 'already_yours' : 'in_use',
            );
        }

        $otpType = $kind === 'phone' ? 'mobile' : 'email';
        $otp     = new OtpService();
        $code    = $otp->generate($value, $otpType, 'link', 'web', $request->ip());
        try {
            if ($kind === 'email') {
                $otp->sendEmail($value, $code);
            } else {
                $otp->sendSms($value, $code);
            }
        } catch (\Throwable $e) {
            Log::warning('linked identifier OTP send failed (api): ' . $e->getMessage());
        }

        return $this->ok([
            'sent'        => true,
            'kind'        => $kind,
            'value'       => $value,
            'demo_reveal' => AuthMethods::demoRevealMessage($code),
        ]);
    }

    /** Verify the code and link the new email/phone to this account. */
    public function verify(Request $request)
    {
        $request->validate([
            'kind'  => 'required|in:email,phone',
            'value' => 'required|string|max:255',
            'code'  => 'required|string|size:6',
        ]);

        $user  = $request->user();
        $kind  = (string) $request->input('kind');
        $value = LinkedIdentifier::normalize($kind, (string) $request->input('value'));

        if ($value === '') {
            return $this->fail('Enter a valid ' . $kind . '.', 422, 'invalid_value');
        }

        $otpType = $kind === 'phone' ? 'mobile' : 'email';
        $otp     = new OtpService();
        if (!$otp->verify($value, (string) $request->input('code'), $otpType, 'link', 'web')) {
            return $this->fail('Invalid or expired code. Please request a new one.', 422, 'invalid_code');
        }

        // Re-check under the unique constraint: idempotent for this user
        // (repeat/double-tap verify must not 500), clear error if someone
        // else slipped in between Send and Verify.
        $existing = LinkedIdentifier::where('kind', $kind)->where('value', $value)->first();
        if ($existing && $existing->user_id !== $user->id) {
            return $this->fail('That ' . $kind . ' was just linked to another account.', 422, 'in_use');
        }
        if ($existing) {
            if (!$existing->verified_at) {
                $existing->forceFill(['verified_at' => now()])->save();
            }
        } else {
            LinkedIdentifier::create([
                'user_id'     => $user->id,
                'kind'        => $kind,
                'value'       => $value,
                'verified_at' => now(),
                'is_primary'  => false,
            ]);
        }

        return $this->ok([
            'verified'    => true,
            'identifiers' => $user->linkedIdentifiers()->get()
                ->map(fn (LinkedIdentifier $i) => $this->present($user, $i))->all(),
        ]);
    }

    /** Remove a non-primary identifier (reuses AccountMergeService guards). */
    public function destroy(Request $request, int $identifier, AccountMergeService $service)
    {
        $user = $request->user();
        $row  = $user->linkedIdentifiers()->find($identifier);
        if (!$row) {
            return $this->notFound('Identifier not found.');
        }

        try {
            $service->unlink($user, $row);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422, 'cannot_remove');
        }

        return $this->ok([
            'removed'     => true,
            'identifiers' => $user->fresh()->linkedIdentifiers()->get()
                ->map(fn (LinkedIdentifier $i) => $this->present($user, $i))->all(),
        ]);
    }

    /** Promote a verified email/phone to primary (reuses AccountMergeService guards). */
    public function promote(Request $request, int $identifier, AccountMergeService $service)
    {
        $user = $request->user();
        $row  = $user->linkedIdentifiers()->find($identifier);
        if (!$row) {
            return $this->notFound('Identifier not found.');
        }

        try {
            $service->promoteToPrimary($user, $row);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422, 'cannot_promote');
        }

        $fresh = $user->fresh();

        return $this->ok([
            'promoted'    => true,
            'identifiers' => $fresh->linkedIdentifiers()->get()
                ->map(fn (LinkedIdentifier $i) => $this->present($fresh, $i))->all(),
        ]);
    }

    /**
     * Serialise one identifier with its remove/promote eligibility computed
     * up-front, so the client can disable an action with an explanation
     * rather than only discovering the block on the action call.
     *
     * @param \Illuminate\Support\Collection<int,LinkedIdentifier>|null $all
     *        Optional already-loaded collection to compute the "keep at least
     *        one verified email/phone" guard without N extra queries.
     */
    private function present($user, LinkedIdentifier $i, $all = null): array
    {
        $removeReason  = $this->removeBlockedReason($user, $i, $all);
        $promoteReason = $this->promoteBlockedReason($i);

        return [
            'id'                     => $i->id,
            'kind'                   => $i->kind,
            'kind_label'            => $i->kindLabel(),
            'value'                  => $i->displayLabel(),
            'is_primary'             => (bool) $i->is_primary,
            'verified'               => (bool) $i->verified_at,
            'can_remove'             => $removeReason === null,
            'remove_blocked_reason'  => $removeReason,
            'can_promote'            => $promoteReason === null,
            'promote_blocked_reason' => $promoteReason,
        ];
    }

    /**
     * The reason an identifier cannot be removed, or null when it can.
     * Pre-flights the AccountMergeService::unlink guards.
     */
    private function removeBlockedReason($user, LinkedIdentifier $i, $all = null): ?string
    {
        if ($i->is_primary) {
            return 'This is your primary identifier — make another verified email or phone primary first.';
        }

        // Verified rows other than this one.
        if ($all !== null) {
            $verifiedOthers = $all->filter(
                fn (LinkedIdentifier $o) => $o->id !== $i->id && $o->verified_at !== null,
            );
            $remaining   = $verifiedOthers->count();
            $hasContact  = $verifiedOthers->whereIn('kind', ['email', 'phone'])->count() > 0;
        } else {
            $remaining = $user->verifiedIdentifiers()
                ->where('id', '!=', $i->id)
                ->count();
            $hasContact = $user->verifiedIdentifiers()
                ->where('id', '!=', $i->id)
                ->whereIn('kind', ['email', 'phone'])
                ->exists();
        }

        if ($remaining < 1) {
            return 'You must keep at least one verified identifier.';
        }
        if (!$hasContact) {
            return 'You must keep at least one verified email or phone on your account.';
        }

        return null;
    }

    /**
     * The reason an identifier cannot be promoted to primary, or null when
     * it can. Pre-flights the AccountMergeService::promoteToPrimary guards.
     */
    private function promoteBlockedReason(LinkedIdentifier $i): ?string
    {
        if ($i->is_primary) {
            return 'This is already your primary identifier.';
        }
        if (!$i->verified_at) {
            return 'Verify this identifier before making it primary.';
        }
        if ($i->kind === 'social') {
            return 'A social identity cannot be your primary contact.';
        }
        return null;
    }
}
