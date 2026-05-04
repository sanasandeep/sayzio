<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Common\Services\LoginAlertService;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Native social sign-in for the mobile app.
 *
 * SECURITY: We MUST NOT trust a client-supplied `external_id` / `email`
 * by themselves — that would let any client mint a session for any
 * account by guessing the victim's social user-id. Instead the client
 * sends the provider's signed ID token (Apple, Google) or its OAuth
 * access token (Facebook), and we verify it server-side against the
 * provider's public keys / userinfo endpoint before linking or logging
 * in.
 */
class SocialAuthController extends Controller
{
    use ApiResponses;

    public function exchange(Request $request)
    {
        $data = $request->validate([
            'provider'     => ['required', Rule::in(['apple', 'google', 'facebook'])],
            'id_token'     => ['nullable', 'string', 'max:8000'],
            'access_token' => ['nullable', 'string', 'max:8000'],
            'name'         => ['nullable', 'string', 'max:120'],
            'avatar'       => ['nullable', 'string', 'max:500'],
            'device'       => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $verified = match ($data['provider']) {
                'apple'    => $this->verifyApple($data['id_token'] ?? null),
                'google'   => $this->verifyGoogle($data['id_token'] ?? null),
                'facebook' => $this->verifyFacebook($data['access_token'] ?? null),
            };
        } catch (\Throwable $e) {
            \Log::warning('social_verify_failed', [
                'provider' => $data['provider'],
                'reason'   => $e->getMessage(),
            ]);
            return $this->fail('We could not verify your sign-in. Please try again.', 401, 'social_verify_failed');
        }

        if (empty($verified['external_id'])) {
            return $this->fail('Provider did not return a stable user id', 401, 'social_verify_failed');
        }

        $externalId = (string) $verified['external_id'];
        $email      = $verified['email']  ?? null;
        $name       = $verified['name']   ?? ($data['name']   ?? null);
        $avatar     = $verified['avatar'] ?? ($data['avatar'] ?? null);

        // Find the existing user by social identity, then by verified email.
        $user = LinkedIdentifier::resolveUser('social', '', $data['provider'], $externalId);
        if (!$user && $email) {
            $user = LinkedIdentifier::resolveUser('email', $email)
                ?: User::where('email', strtolower($email))->first();
        }

        $created = false;
        if (!$user) {
            $freePlan = Plan::where('slug', 'free')->first();
            $user = User::create([
                'name'              => $name ?: ucfirst($data['provider']) . ' user',
                'email'             => $email ? strtolower($email) : null,
                'avatar'            => $avatar,
                'password'          => Hash::make(Str::random(48)),
                'plan_id'           => $freePlan?->id,
                'status'            => 'active',
                'email_verified_at' => $email ? now() : null,
            ]);
            if (method_exists($user, 'ensureDefaultWorkspace')) {
                $user->ensureDefaultWorkspace();
            }
            $created = true;
        }

        // Bind/refresh the social identifier — refuse if it already
        // belongs to a different live account (the user must merge from
        // the web first).
        $value = LinkedIdentifier::normalize('social', '', $data['provider'], $externalId);
        $existing = LinkedIdentifier::where('kind', 'social')->where('value', $value)->first();
        if ($existing && $existing->user_id !== $user->id) {
            return $this->fail('That identity is already linked to another account', 409, 'identity_taken');
        }
        if (!$existing) {
            LinkedIdentifier::create([
                'user_id'     => $user->id,
                'kind'        => 'social',
                'value'       => $value,
                'provider'    => $data['provider'],
                'external_id' => $externalId,
                'verified_at' => now(),
                'is_primary'  => false,
            ]);
        } else {
            $existing->forceFill(['verified_at' => now()])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $newToken = $user->createToken($data['device'] ?? 'mobile-social');
        app(LoginAlertService::class)->record($user, $request, 'mobile_social_' . $data['provider'], [
            'personal_access_token_id' => $newToken->accessToken->id ?? null,
            'device_label'             => $data['device'] ?? null,
        ]);

        return $this->ok([
            'user'    => UserResource::toArray($user, self: true),
            'token'   => $newToken->plainTextToken,
            'created' => $created,
        ]);
    }

    /**
     * Verify an Apple "Sign in with Apple" identity token.
     * Validates signature against Apple's published JWKS, plus issuer
     * and audience (== our configured bundle id).
     */
    private function verifyApple(?string $idToken): array
    {
        if (!$idToken) throw new \RuntimeException('Missing id_token');

        $jwks = Http::timeout(5)->get('https://appleid.apple.com/auth/keys')->json('keys') ?? [];
        $payload = $this->verifyJwt($idToken, $jwks, [
            'iss' => 'https://appleid.apple.com',
            'aud' => env('APPLE_BUNDLE_ID', 'com.oneinme.app'),
        ]);
        return [
            'external_id' => $payload['sub']   ?? null,
            'email'       => $payload['email'] ?? null,
        ];
    }

    /**
     * Verify a Google ID token via Google's hosted tokeninfo endpoint.
     * Returns the verified payload; the endpoint is rate-limited and
     * uses HTTPS, so this is safe and does not require the firebase/php-jwt
     * package.
     */
    private function verifyGoogle(?string $idToken): array
    {
        if (!$idToken) throw new \RuntimeException('Missing id_token');

        $resp = Http::timeout(5)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        if (!$resp->ok()) throw new \RuntimeException('Google rejected the id token');
        $payload = $resp->json();
        $expectedAud = env('GOOGLE_CLIENT_ID');
        if ($expectedAud && ($payload['aud'] ?? null) !== $expectedAud) {
            throw new \RuntimeException('aud mismatch');
        }
        if (!in_array(($payload['iss'] ?? ''), ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new \RuntimeException('iss mismatch');
        }
        if (((int) ($payload['exp'] ?? 0)) < time()) {
            throw new \RuntimeException('id token expired');
        }
        return [
            'external_id' => $payload['sub']     ?? null,
            'email'       => $payload['email']   ?? null,
            'name'        => $payload['name']    ?? null,
            'avatar'      => $payload['picture'] ?? null,
        ];
    }

    /**
     * Verify a Facebook user-access-token by calling Graph API
     * /debug_token (requires app-access-token) and /me.
     */
    private function verifyFacebook(?string $accessToken): array
    {
        if (!$accessToken) throw new \RuntimeException('Missing access_token');

        $appId     = env('FACEBOOK_APP_ID');
        $appSecret = env('FACEBOOK_APP_SECRET');
        if (!$appId || !$appSecret) throw new \RuntimeException('Facebook is not configured on this server');

        $appToken = $appId . '|' . $appSecret;
        $debug = Http::timeout(5)->get('https://graph.facebook.com/debug_token', [
            'input_token'  => $accessToken,
            'access_token' => $appToken,
        ])->json('data');
        if (!is_array($debug) || empty($debug['is_valid']) || ($debug['app_id'] ?? null) !== $appId) {
            throw new \RuntimeException('Facebook rejected the access token');
        }
        $me = Http::timeout(5)->get('https://graph.facebook.com/me', [
            'fields'       => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ])->json();
        return [
            'external_id' => $me['id']    ?? ($debug['user_id'] ?? null),
            'email'       => $me['email'] ?? null,
            'name'        => $me['name']  ?? null,
            'avatar'      => $me['picture']['data']['url'] ?? null,
        ];
    }

    /**
     * Minimal JWT verifier supporting RS256 (Apple). Avoids pulling in
     * a JWT library for this single use case.
     */
    private function verifyJwt(string $jwt, array $jwks, array $expected): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new \RuntimeException('Malformed JWT');
        [$h64, $p64, $s64] = $parts;

        $b64url = fn ($d) => strtr(base64_encode($d), '+/', '-_');
        $b64urld = fn ($s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));

        $header  = json_decode($b64urld($h64), true) ?: [];
        $payload = json_decode($b64urld($p64), true) ?: [];
        $sig     = $b64urld($s64);

        if (($header['alg'] ?? '') !== 'RS256') throw new \RuntimeException('Unsupported alg: ' . ($header['alg'] ?? ''));

        $kid = $header['kid'] ?? null;
        $jwk = collect($jwks)->firstWhere('kid', $kid) ?: ($jwks[0] ?? null);
        if (!$jwk) throw new \RuntimeException('Signing key not found');

        $pem = $this->jwkToPem($jwk);
        $ok = openssl_verify($h64 . '.' . $p64, $sig, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) throw new \RuntimeException('Bad signature');

        if (isset($expected['iss']) && ($payload['iss'] ?? '') !== $expected['iss']) throw new \RuntimeException('iss mismatch');
        if (isset($expected['aud']) && ($payload['aud'] ?? '') !== $expected['aud']) throw new \RuntimeException('aud mismatch');
        if (((int) ($payload['exp'] ?? 0)) < time()) throw new \RuntimeException('expired');

        return $payload;
    }

    private function jwkToPem(array $jwk): string
    {
        $b64urld = fn ($s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
        $n = $b64urld($jwk['n']);
        $e = $b64urld($jwk['e']);

        // Build a minimal DER RSA public key. Sequence{ Sequence{ OID rsaEncryption, NULL }, BIT STRING { Sequence{ INTEGER n, INTEGER e } } }
        $intToAsn1 = function ($bytes) {
            if (ord($bytes[0]) & 0x80) $bytes = "\x00" . $bytes;
            return "\x02" . $this->asn1Length(strlen($bytes)) . $bytes;
        };
        $rsaPub = "\x30" . $this->asn1Length(strlen($intToAsn1($n)) + strlen($intToAsn1($e))) . $intToAsn1($n) . $intToAsn1($e);
        $bitStr = "\x03" . $this->asn1Length(strlen($rsaPub) + 1) . "\x00" . $rsaPub;
        $oid    = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $seq    = "\x30" . $this->asn1Length(strlen($oid) + strlen($bitStr)) . $oid . $bitStr;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($seq), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function asn1Length(int $len): string
    {
        if ($len < 0x80) return chr($len);
        $bytes = '';
        while ($len > 0) { $bytes = chr($len & 0xff) . $bytes; $len >>= 8; }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
