<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\AccountMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;

/**
 * Native (mobile) account-merge flow — a stateless mirror of the web
 * {@see \App\Modules\User\Controllers\AccountMergeController}:
 *
 *   challenge → verify → preview → confirm
 *
 * The challenge proves the signed-in user also controls the *other*
 * account; on success the merge actually moves data via the shared
 * {@see AccountMergeService}, so the web and mobile flows behave
 * identically.
 *
 * Because the Sanctum API has no session, the secondary-account binding
 * the web flow stashes in session keys is instead carried between steps
 * in a short-lived, APP_KEY-encrypted "merge token". The token encodes
 * the primary id (who started the merge), the proven secondary id, and an
 * expiry; every step re-checks the token's primary against the
 * authenticated user so a leaked token can't be used by another account.
 */
class AccountMergeController extends Controller
{
    use ApiResponses;

    /** Merge-token lifetime (minutes) — bounds how long a proven challenge stays usable. */
    private const TOKEN_TTL_MINUTES = 15;

    /** Step 1: send a one-time code to the OTHER account's identifier. */
    public function challenge(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'kind'  => 'required|in:email,phone',
            'value' => 'required|string|max:255',
        ]);
        $primary = $request->user();

        $kind    = $data['kind'];
        $value   = LinkedIdentifier::normalize($kind, (string) $data['value']);
        $otpType = $kind === 'phone' ? 'mobile' : 'email';

        // Refuse if the identifier already belongs to the signed-in user
        // — there's no "other account" to merge in that case.
        $owner = LinkedIdentifier::resolveUser($kind, $value);
        if ($owner && $owner->id === $primary->id) {
            return $this->fail(
                'That identifier is already on your current account — nothing to merge.',
                422,
                'merge_self'
            );
        }

        $code = $otp->generate($value, $otpType, 'login', 'web');
        try {
            $kind === 'email' ? $otp->sendEmail($value, $code) : $otp->sendWhatsApp($value, $code);
        } catch (\Throwable $e) {
            \Log::warning('merge challenge OTP send failed (api): ' . $e->getMessage());
        }

        // Generic success either way so a request can't enumerate which
        // identifiers map to a real account.
        return $this->ok([
            'sent'        => true,
            'kind'        => $kind,
            'value'       => $value,
            'message'     => 'If that account exists, a verification code has been sent.',
            'demo_reveal' => \App\Modules\Common\Support\AuthMethods::demoRevealMessage($code),
        ]);
    }

    /** Step 2: verify the code, resolve the secondary account, return a merge token + preview. */
    public function verify(Request $request, OtpService $otp, AccountMergeService $service): JsonResponse
    {
        $data = $request->validate([
            'kind'  => 'required|in:email,phone',
            'value' => 'required|string|max:255',
            'code'  => 'required|string|size:6',
        ]);
        $primary = $request->user();

        $kind    = $data['kind'];
        $value   = LinkedIdentifier::normalize($kind, (string) $data['value']);
        $otpType = $kind === 'phone' ? 'mobile' : 'email';

        // Challenge codes are issued with the same (purpose, guard) tuple
        // as the web flow so verification matches exactly.
        if (!$otp->verify($value, $data['code'], $otpType, 'login', 'web')) {
            return $this->fail('Invalid or expired code', 400, 'invalid_otp');
        }

        $secondary = $this->resolveSecondary($kind, $value);
        if (!$secondary) {
            return $this->fail('No account found for that identifier.', 404, 'user_not_found');
        }
        if ($secondary->id === $primary->id) {
            return $this->fail(
                'That identifier is already on your current account — nothing to merge.',
                422,
                'merge_self'
            );
        }
        if ($secondary->roles()->exists() || $primary->roles()->exists()) {
            return $this->fail('Admin accounts cannot be merged.', 422, 'merge_admin');
        }

        return $this->ok([
            'merge_token' => $this->issueToken($primary->id, $secondary->id),
            'preview'     => $this->presentPreview($service->preview($primary, $secondary), $primary, $secondary),
        ]);
    }

    /** Step 3 (re-fetch): rebuild the preview from a still-valid merge token. */
    public function preview(Request $request, AccountMergeService $service): JsonResponse
    {
        $data = $request->validate(['merge_token' => 'required|string']);
        [$primary, $secondary, $err] = $this->resolveToken($request, $data['merge_token']);
        if ($err) return $err;

        return $this->ok([
            'merge_token' => $data['merge_token'],
            'preview'     => $this->presentPreview($service->preview($primary, $secondary), $primary, $secondary),
        ]);
    }

    /** Step 4: execute the merge inside the service transaction. */
    public function confirm(Request $request, AccountMergeService $service): JsonResponse
    {
        $data = $request->validate([
            'merge_token'    => 'required|string',
            'keep_plan_from' => 'nullable|in:primary,secondary',
        ]);
        [$primary, $secondary, $err] = $this->resolveToken($request, $data['merge_token']);
        if ($err) return $err;

        try {
            $summary = $service->merge($primary, $secondary, $data['keep_plan_from'] ?? 'primary');
        } catch (\Throwable $e) {
            // Log the technical detail for support, but show the user a
            // generic message — exception text can leak schema internals.
            \Log::error('Account merge failed (api)', [
                'primary_id'   => $primary->id,
                'secondary_id' => $secondary->id,
                'message'      => $e->getMessage(),
            ]);
            $userMsg = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                ? $e->getMessage()
                : "We couldn't complete the merge. No changes were made — please try again or contact support.";
            return $this->fail($userMsg, 422, 'merge_failed');
        }

        $rowTotal = array_sum($summary['reassigned'] ?? []);

        return $this->ok([
            'merged'          => true,
            'records_moved'   => $rowTotal,
            'kept_plan_from'  => $summary['kept_plan_from'] ?? 'primary',
            'secondary_email' => $summary['secondary_email'] ?? null,
            'user'            => UserResource::toArray($primary->fresh(), self: true),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function resolveSecondary(string $kind, string $value): ?User
    {
        $user = LinkedIdentifier::resolveUser($kind, $value);
        if ($user) return $user;
        return $kind === 'email'
            ? User::where('email', strtolower($value))->first()
            : User::where('mobile', $value)->first();
    }

    private function issueToken(int $primaryId, int $secondaryId): string
    {
        return Crypt::encryptString((string) json_encode([
            'p'   => $primaryId,
            's'   => $secondaryId,
            'exp' => now()->addMinutes(self::TOKEN_TTL_MINUTES)->getTimestamp(),
        ]));
    }

    /**
     * Decode + validate a merge token against the authenticated user.
     *
     * @return array{0:?User, 1:?User, 2:?JsonResponse} [primary, secondary, errorResponse]
     */
    private function resolveToken(Request $request, string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return [null, null, $this->fail('Merge session expired. Please start again.', 422, 'merge_expired')];
        }
        $primaryId   = (int) ($payload['p'] ?? 0);
        $secondaryId = (int) ($payload['s'] ?? 0);
        $exp         = (int) ($payload['exp'] ?? 0);

        if (!$primaryId || !$secondaryId || $exp < time()) {
            return [null, null, $this->fail('Merge session expired. Please start again.', 422, 'merge_expired')];
        }

        $primary = $request->user();
        if (!$primary || $primary->id !== $primaryId) {
            return [null, null, $this->fail('This merge was started by a different account.', 403, 'merge_mismatch')];
        }

        $secondary = User::find($secondaryId);
        if (!$secondary || $secondary->id === $primary->id) {
            return [null, null, $this->fail('The other account could not be found.', 404, 'user_not_found')];
        }
        if ($secondary->roles()->exists() || $primary->roles()->exists()) {
            return [null, null, $this->fail('Admin accounts cannot be merged.', 422, 'merge_admin')];
        }

        return [$primary, $secondary, null];
    }

    /** Shape the service preview into a compact, JSON-friendly payload for the app. */
    private function presentPreview(array $preview, User $primary, User $secondary): array
    {
        $items = [];
        foreach (($preview['counts'] ?? []) as $key => $n) {
            $items[] = [
                'key'   => $key,
                'label' => $this->humanizeCountKey((string) $key),
                'count' => (int) $n,
            ];
        }
        // Most-impactful first so the preview leads with what matters.
        usort($items, fn ($a, $b) => $b['count'] <=> $a['count']);

        $identifiers = [];
        foreach (($preview['identifiers'] ?? []) as $idf) {
            $identifiers[] = [
                'kind'  => $idf->kind,
                'label' => $this->maskIdentifier($idf->kind, (string) $idf->value, $idf->provider ?? null),
            ];
        }

        return [
            'total_records'           => array_sum(array_map(static fn ($i) => $i['count'], $items)),
            'items'                   => $items,
            'identifiers'             => $identifiers,
            'primary_has_paid_plan'   => (bool) ($preview['primary_has_paid_plan'] ?? false),
            'secondary_has_paid_plan' => (bool) ($preview['secondary_has_paid_plan'] ?? false),
            'primary'                 => ['name' => $primary->name, 'email' => $primary->email],
            'secondary'               => ['name' => $secondary->name, 'email' => $secondary->email],
        ];
    }

    /** Turn a "table.column" counts key into a human-friendly table label. */
    private function humanizeCountKey(string $key): string
    {
        $table = explode('.', $key)[0] ?: $key;
        return ucfirst(str_replace('_', ' ', $table));
    }

    /** Partially mask an identifier so the preview doesn't fully reveal the other account's contacts. */
    private function maskIdentifier(string $kind, string $value, ?string $provider): string
    {
        if ($kind === 'social') {
            return ucfirst($provider ?: 'social') . ' account';
        }
        if ($kind === 'email' && str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);
            $maskedLocal = strlen($local) <= 2 ? ($local[0] ?? '') . '…' : substr($local, 0, 2) . '…';
            return $maskedLocal . '@' . $domain;
        }
        return strlen($value) > 4 ? '…' . substr($value, -4) : $value;
    }
}
