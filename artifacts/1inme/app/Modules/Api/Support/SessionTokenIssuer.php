<?php

namespace App\Modules\Api\Support;

use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\NewAccessToken;

/**
 * Mints Sanctum access tokens with the device / UA / IP metadata that
 * powers the Devices & sessions page (task #1111). Centralised so that
 * the email/password, OTP and social sign-in entry points all populate
 * the same fields and the sessions list can describe every kind of
 * client.
 */
class SessionTokenIssuer
{
    /**
     * Mint a new token for $user and stamp it with request metadata.
     *
     * @param string $deviceFallback Used when the client did not supply a `device` value.
     */
    public static function issue(
        User $user,
        Request $request,
        ?string $clientDevice,
        string $deviceFallback,
        string $clientKind = 'mobile',
    ): NewAccessToken {
        $deviceLabel = self::cleanDevice($clientDevice) ?? self::guessDeviceLabel($request);
        $ua          = (string) ($request->userAgent() ?? '');
        $ip          = (string) ($request->ip() ?? '');
        $country     = null;

        if ($ip !== '') {
            try {
                $country = app(GeoIpService::class)->detectCountry($ip);
            } catch (\Throwable) {
                $country = null;
            }
        }

        $platform = self::guessPlatform($request, $ua);

        // Token "name" stays the device-or-fallback string so existing
        // log/admin tooling that reads the Sanctum name keeps working.
        $token = $user->createToken($clientDevice ?: $deviceFallback);

        $token->accessToken->forceFill([
            'device_label'       => self::truncate($deviceLabel, 120),
            'platform'           => $platform,
            'client_kind'        => $clientKind,
            'created_ip'         => $ip ?: null,
            'created_country'    => $country,
            'created_user_agent' => self::truncate($ua, 500),
            'last_ip'            => $ip ?: null,
            'last_country'       => $country,
            'last_user_agent'    => self::truncate($ua, 500),
        ])->save();

        return $token;
    }

    private static function cleanDevice(?string $device): ?string
    {
        if ($device === null) return null;
        $device = trim($device);
        return $device === '' ? null : $device;
    }

    private static function guessDeviceLabel(Request $request): string
    {
        $ua = (string) ($request->userAgent() ?? '');
        if ($ua === '') return 'Unknown device';

        // Mobile app sends a recognisable client header.
        $client = (string) ($request->header('X-1INME-Client') ?? '');
        if (str_contains($client, '1INMEMobileApp')) {
            return str_contains($client, 'ios') ? 'iPhone / iPad' : 'Android device';
        }

        if (preg_match('/iPhone|iPad|iPod/i', $ua))   return 'iPhone / iPad';
        if (preg_match('/Android/i', $ua))            return 'Android device';
        if (preg_match('/Mac OS X/i', $ua))           return 'Mac';
        if (preg_match('/Windows/i', $ua))            return 'Windows PC';
        if (preg_match('/Linux/i', $ua))              return 'Linux';
        return 'Web browser';
    }

    private static function guessPlatform(Request $request, string $ua): ?string
    {
        $client = (string) ($request->header('X-1INME-Client') ?? '');
        if (str_contains($client, 'ios'))     return 'ios';
        if (str_contains($client, 'android')) return 'android';
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'ios';
        if (preg_match('/Android/i', $ua))          return 'android';
        if (preg_match('/Mac OS X/i', $ua))         return 'macos';
        if (preg_match('/Windows/i', $ua))          return 'windows';
        if (preg_match('/Linux/i', $ua))            return 'linux';
        return null;
    }

    private static function truncate(?string $s, int $n): ?string
    {
        if ($s === null) return null;
        return mb_substr($s, 0, $n);
    }
}
