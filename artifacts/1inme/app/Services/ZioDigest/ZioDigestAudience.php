<?php

namespace App\Services\ZioDigest;

use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Common\Models\ZioDigest;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves a Zio Digest audience definition into recipient queries/counts.
 *
 * Audience shape (zio_digests.audience JSON):
 *   ['mode' => 'all'|'opted_in'|'plans', 'plan_ids' => [int, ...]]
 *
 * Rules:
 *  - Suspended accounts are never included.
 *  - Email channel additionally requires an email address and always honors
 *    the user-level digest opt-out (regardless of mode).
 *  - 'opted_in' restricts to users who have NOT opted out of digest email —
 *    for the WhatsApp channel it is the closest analogue we have to
 *    "subscribed".
 *  - 'plans' restricts to users whose plan_id is in plan_ids.
 *  - WhatsApp channel requires a phone identity (phone or mobile).
 */
class ZioDigestAudience
{
    /** @return array{mode:string,plan_ids:array<int>} */
    public static function normalize($audience): array
    {
        $audience = is_array($audience) ? $audience : [];
        $mode = in_array($audience['mode'] ?? null, ['all', 'opted_in', 'plans'], true)
            ? $audience['mode'] : 'opted_in';
        $planIds = array_values(array_unique(array_map('intval', array_filter(
            (array) ($audience['plan_ids'] ?? []),
            fn ($v) => is_numeric($v) && (int) $v > 0,
        ))));

        return ['mode' => $mode, 'plan_ids' => $planIds];
    }

    /** Base audience query (channel-agnostic). */
    public static function baseQuery(array $audience): Builder
    {
        $audience = self::normalize($audience);

        $q = User::query()
            ->whereNull('suspended_at');

        if ($audience['mode'] === 'opted_in') {
            $q->where('digest_email_opt_out', false);
        } elseif ($audience['mode'] === 'plans') {
            $q->whereIn('plan_id', $audience['plan_ids'] ?: [-1]);
        }

        return $q;
    }

    /** Email-eligible recipients: has an email + not opted out (always). */
    public static function emailQuery(array $audience): Builder
    {
        return self::baseQuery($audience)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('digest_email_opt_out', false);
    }

    /** WhatsApp-eligible recipients: has a phone identity. */
    public static function whatsappQuery(array $audience): Builder
    {
        return self::baseQuery($audience)->where(function (Builder $q) {
            $q->where(fn ($w) => $w->whereNotNull('phone')->where('phone', '!=', ''))
              ->orWhere(fn ($w) => $w->whereNotNull('mobile')->where('mobile', '!=', ''));
        });
    }

    /**
     * Live counts for the composer UI.
     *
     * @return array{total:int,email:int,whatsapp:int,email_opted_out:int,no_phone:int}
     */
    public static function counts(array $audience): array
    {
        $total    = self::baseQuery($audience)->count();
        $email    = self::emailQuery($audience)->count();
        $whatsapp = self::whatsappQuery($audience)->count();

        return [
            'total'           => $total,
            'email'           => $email,
            'whatsapp'        => $whatsapp,
            'email_opted_out' => max(0, $total - $email),
            'no_phone'        => max(0, $total - $whatsapp),
        ];
    }

    /** True when the user must never be broadcast to (protected accounts). */
    public static function isExcluded(User $user): bool
    {
        try {
            return ProtectedAccount::isProtected($user);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Resolve the phone identity used for the WhatsApp channel. */
    public static function phoneFor(User $user): ?string
    {
        foreach ([$user->phone ?? null, $user->mobile ?? null] as $candidate) {
            if (is_string($candidate) && preg_replace('/\D+/', '', $candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
