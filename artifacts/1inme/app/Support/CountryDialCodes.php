<?php

namespace App\Support;

class CountryDialCodes
{
    /** @return array<int, array{code:string, name:string, dial:string, flag:string}> */
    public static function all(): array
    {
        return [
            ['code' => 'US', 'name' => 'United States',           'dial' => '+1',    'flag' => '🇺🇸'],
            ['code' => 'CA', 'name' => 'Canada',                  'dial' => '+1',    'flag' => '🇨🇦'],
            ['code' => 'GB', 'name' => 'United Kingdom',          'dial' => '+44',   'flag' => '🇬🇧'],
            ['code' => 'AU', 'name' => 'Australia',               'dial' => '+61',   'flag' => '🇦🇺'],
            ['code' => 'NZ', 'name' => 'New Zealand',             'dial' => '+64',   'flag' => '🇳🇿'],
            ['code' => 'IN', 'name' => 'India',                   'dial' => '+91',   'flag' => '🇮🇳'],
            ['code' => 'PK', 'name' => 'Pakistan',                'dial' => '+92',   'flag' => '🇵🇰'],
            ['code' => 'BD', 'name' => 'Bangladesh',              'dial' => '+880',  'flag' => '🇧🇩'],
            ['code' => 'LK', 'name' => 'Sri Lanka',               'dial' => '+94',   'flag' => '🇱🇰'],
            ['code' => 'NP', 'name' => 'Nepal',                   'dial' => '+977',  'flag' => '🇳🇵'],
            ['code' => 'DE', 'name' => 'Germany',                 'dial' => '+49',   'flag' => '🇩🇪'],
            ['code' => 'FR', 'name' => 'France',                  'dial' => '+33',   'flag' => '🇫🇷'],
            ['code' => 'IT', 'name' => 'Italy',                   'dial' => '+39',   'flag' => '🇮🇹'],
            ['code' => 'ES', 'name' => 'Spain',                   'dial' => '+34',   'flag' => '🇪🇸'],
            ['code' => 'NL', 'name' => 'Netherlands',             'dial' => '+31',   'flag' => '🇳🇱'],
            ['code' => 'BE', 'name' => 'Belgium',                 'dial' => '+32',   'flag' => '🇧🇪'],
            ['code' => 'CH', 'name' => 'Switzerland',             'dial' => '+41',   'flag' => '🇨🇭'],
            ['code' => 'AT', 'name' => 'Austria',                 'dial' => '+43',   'flag' => '🇦🇹'],
            ['code' => 'SE', 'name' => 'Sweden',                  'dial' => '+46',   'flag' => '🇸🇪'],
            ['code' => 'NO', 'name' => 'Norway',                  'dial' => '+47',   'flag' => '🇳🇴'],
            ['code' => 'DK', 'name' => 'Denmark',                 'dial' => '+45',   'flag' => '🇩🇰'],
            ['code' => 'FI', 'name' => 'Finland',                 'dial' => '+358',  'flag' => '🇫🇮'],
            ['code' => 'PT', 'name' => 'Portugal',                'dial' => '+351',  'flag' => '🇵🇹'],
            ['code' => 'PL', 'name' => 'Poland',                  'dial' => '+48',   'flag' => '🇵🇱'],
            ['code' => 'CZ', 'name' => 'Czech Republic',          'dial' => '+420',  'flag' => '🇨🇿'],
            ['code' => 'HU', 'name' => 'Hungary',                 'dial' => '+36',   'flag' => '🇭🇺'],
            ['code' => 'RO', 'name' => 'Romania',                 'dial' => '+40',   'flag' => '🇷🇴'],
            ['code' => 'GR', 'name' => 'Greece',                  'dial' => '+30',   'flag' => '🇬🇷'],
            ['code' => 'IE', 'name' => 'Ireland',                 'dial' => '+353',  'flag' => '🇮🇪'],
            ['code' => 'RU', 'name' => 'Russia',                  'dial' => '+7',    'flag' => '🇷🇺'],
            ['code' => 'TR', 'name' => 'Turkey',                  'dial' => '+90',   'flag' => '🇹🇷'],
            ['code' => 'UA', 'name' => 'Ukraine',                 'dial' => '+380',  'flag' => '🇺🇦'],
            ['code' => 'AE', 'name' => 'UAE',                     'dial' => '+971',  'flag' => '🇦🇪'],
            ['code' => 'SA', 'name' => 'Saudi Arabia',            'dial' => '+966',  'flag' => '🇸🇦'],
            ['code' => 'IL', 'name' => 'Israel',                  'dial' => '+972',  'flag' => '🇮🇱'],
            ['code' => 'QA', 'name' => 'Qatar',                   'dial' => '+974',  'flag' => '🇶🇦'],
            ['code' => 'KW', 'name' => 'Kuwait',                  'dial' => '+965',  'flag' => '🇰🇼'],
            ['code' => 'BH', 'name' => 'Bahrain',                 'dial' => '+973',  'flag' => '🇧🇭'],
            ['code' => 'OM', 'name' => 'Oman',                    'dial' => '+968',  'flag' => '🇴🇲'],
            ['code' => 'JO', 'name' => 'Jordan',                  'dial' => '+962',  'flag' => '🇯🇴'],
            ['code' => 'LB', 'name' => 'Lebanon',                 'dial' => '+961',  'flag' => '🇱🇧'],
            ['code' => 'EG', 'name' => 'Egypt',                   'dial' => '+20',   'flag' => '🇪🇬'],
            ['code' => 'ZA', 'name' => 'South Africa',            'dial' => '+27',   'flag' => '🇿🇦'],
            ['code' => 'NG', 'name' => 'Nigeria',                 'dial' => '+234',  'flag' => '🇳🇬'],
            ['code' => 'KE', 'name' => 'Kenya',                   'dial' => '+254',  'flag' => '🇰🇪'],
            ['code' => 'GH', 'name' => 'Ghana',                   'dial' => '+233',  'flag' => '🇬🇭'],
            ['code' => 'ET', 'name' => 'Ethiopia',                'dial' => '+251',  'flag' => '🇪🇹'],
            ['code' => 'MA', 'name' => 'Morocco',                 'dial' => '+212',  'flag' => '🇲🇦'],
            ['code' => 'TZ', 'name' => 'Tanzania',                'dial' => '+255',  'flag' => '🇹🇿'],
            ['code' => 'UG', 'name' => 'Uganda',                  'dial' => '+256',  'flag' => '🇺🇬'],
            ['code' => 'CN', 'name' => 'China',                   'dial' => '+86',   'flag' => '🇨🇳'],
            ['code' => 'JP', 'name' => 'Japan',                   'dial' => '+81',   'flag' => '🇯🇵'],
            ['code' => 'KR', 'name' => 'South Korea',             'dial' => '+82',   'flag' => '🇰🇷'],
            ['code' => 'SG', 'name' => 'Singapore',               'dial' => '+65',   'flag' => '🇸🇬'],
            ['code' => 'MY', 'name' => 'Malaysia',                'dial' => '+60',   'flag' => '🇲🇾'],
            ['code' => 'ID', 'name' => 'Indonesia',               'dial' => '+62',   'flag' => '🇮🇩'],
            ['code' => 'PH', 'name' => 'Philippines',             'dial' => '+63',   'flag' => '🇵🇭'],
            ['code' => 'TH', 'name' => 'Thailand',                'dial' => '+66',   'flag' => '🇹🇭'],
            ['code' => 'VN', 'name' => 'Vietnam',                 'dial' => '+84',   'flag' => '🇻🇳'],
            ['code' => 'HK', 'name' => 'Hong Kong',               'dial' => '+852',  'flag' => '🇭🇰'],
            ['code' => 'TW', 'name' => 'Taiwan',                  'dial' => '+886',  'flag' => '🇹🇼'],
            ['code' => 'BR', 'name' => 'Brazil',                  'dial' => '+55',   'flag' => '🇧🇷'],
            ['code' => 'MX', 'name' => 'Mexico',                  'dial' => '+52',   'flag' => '🇲🇽'],
            ['code' => 'AR', 'name' => 'Argentina',               'dial' => '+54',   'flag' => '🇦🇷'],
            ['code' => 'CO', 'name' => 'Colombia',                'dial' => '+57',   'flag' => '🇨🇴'],
            ['code' => 'CL', 'name' => 'Chile',                   'dial' => '+56',   'flag' => '🇨🇱'],
            ['code' => 'PE', 'name' => 'Peru',                    'dial' => '+51',   'flag' => '🇵🇪'],
            ['code' => 'VE', 'name' => 'Venezuela',               'dial' => '+58',   'flag' => '🇻🇪'],
        ];
    }

    /**
     * Parse an existing stored phone string into [dialCode, localNumber].
     * Returns ['+1', ''] when the value cannot be parsed.
     *
     * @return array{0:string, 1:string}
     */
    public static function parse(string $phone): array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return ['+1', ''];
        }
        if (!str_starts_with($phone, '+')) {
            return ['+1', $phone];
        }
        // Try each dial code longest-first so +44 beats +4
        $codes = array_map(fn ($c) => $c['dial'], self::all());
        $codes = array_unique($codes);
        usort($codes, fn ($a, $b) => strlen($b) - strlen($a));
        foreach ($codes as $code) {
            if (str_starts_with($phone, $code)) {
                $rest = ltrim(substr($phone, strlen($code)));
                return [$code, $rest];
            }
        }
        return ['+1', $phone];
    }

    /**
     * Return country list keyed by dial code for easy lookup.
     * When multiple countries share a code, the first wins.
     *
     * @return array<string, array{code:string, name:string, dial:string, flag:string}>
     */
    public static function byDial(): array
    {
        $map = [];
        foreach (self::all() as $c) {
            $map[$c['dial']] ??= $c;
        }
        return $map;
    }
}
