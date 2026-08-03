<?php

namespace App\Modules\Common\Support;

use App\Modules\Common\Services\GeoIpService;

/**
 * Infers a sensible billing country for a brand-new account now that the
 * sign-up forms no longer ask for one. Country drives billing currency
 * (config/country_currency.php: IN => INR, everything else => USD), so
 * without this every new signup would silently default to USD — including
 * Indian creators.
 *
 * Resolution order:
 *   1. Phone number's international dialling code (WhatsApp/OTP signups) —
 *      the strongest signal we have, chosen by the user themselves.
 *   2. GeoIP lookup on the signup request's IP (best-effort, 2s timeout,
 *      cached; returns null on private/unresolvable IPs).
 *
 * Only used at account-creation time; existing users keep whatever country
 * they already have (or can change it from Profile → Billing country).
 */
class SignupCountry
{
    /**
     * International dialling code (digits only) → ISO 3166-1 alpha-2.
     * Longest-prefix match wins ("880…" is BD, not the "8x" family).
     * "+1" ambiguously covers NANP; US is the currency-equivalent pick.
     *
     * @var array<string,string>
     */
    private const DIAL_TO_ISO = [
        '1'   => 'US',
        '7'   => 'RU',
        '20'  => 'EG',
        '27'  => 'ZA',
        '30'  => 'GR',
        '31'  => 'NL',
        '33'  => 'FR',
        '34'  => 'ES',
        '39'  => 'IT',
        '40'  => 'RO',
        '41'  => 'CH',
        '43'  => 'AT',
        '44'  => 'GB',
        '45'  => 'DK',
        '46'  => 'SE',
        '47'  => 'NO',
        '48'  => 'PL',
        '49'  => 'DE',
        '52'  => 'MX',
        '55'  => 'BR',
        '60'  => 'MY',
        '61'  => 'AU',
        '62'  => 'ID',
        '63'  => 'PH',
        '64'  => 'NZ',
        '65'  => 'SG',
        '66'  => 'TH',
        '81'  => 'JP',
        '82'  => 'KR',
        '84'  => 'VN',
        '86'  => 'CN',
        '90'  => 'TR',
        '91'  => 'IN',
        '92'  => 'PK',
        '94'  => 'LK',
        '230' => 'MU',
        '234' => 'NG',
        '254' => 'KE',
        '351' => 'PT',
        '353' => 'IE',
        '358' => 'FI',
        '380' => 'UA',
        '852' => 'HK',
        '880' => 'BD',
        '960' => 'MV',
        '965' => 'KW',
        '966' => 'SA',
        '968' => 'OM',
        '971' => 'AE',
        '972' => 'IL',
        '973' => 'BH',
        '974' => 'QA',
        '977' => 'NP',
    ];

    /**
     * ISO country from a phone number's dialling code, or null when the
     * number is blank / has no recognised prefix. Compares on digits only
     * so "+91 98…", "+9198…" and "9198…" all resolve to IN (mirrors
     * AuthMethods::isAllowedMobile's normalisation).
     */
    public static function fromMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';
        if ($digits === '') {
            return null;
        }
        // Longest dialling code is 3 digits in our map; try 3 → 2 → 1.
        foreach ([3, 2, 1] as $len) {
            $prefix = substr($digits, 0, $len);
            $iso = self::DIAL_TO_ISO[$prefix] ?? null;
            if (is_string($iso) && strlen($iso) === 2) {
                return $iso;
            }
        }
        return null;
    }

    /**
     * Best-effort country for a new signup: phone dialling code first,
     * then GeoIP on the request IP. Never throws; null when nothing can
     * be determined (the user can still set it on their profile, and the
     * upgrade page's currency switcher keeps working as before).
     */
    public static function infer(?string $mobile, ?string $ip): ?string
    {
        if ($cc = self::fromMobile($mobile)) {
            return $cc;
        }
        if (is_string($ip) && $ip !== '') {
            try {
                $cc = app(GeoIpService::class)->detectCountry($ip);
                if (is_string($cc) && preg_match('/^[A-Za-z]{2}$/', $cc)) {
                    return strtoupper($cc);
                }
            } catch (\Throwable $e) {
                // GeoIP is best-effort; a failed lookup must never block signup.
            }
        }
        return null;
    }
}
