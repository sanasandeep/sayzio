<?php

namespace App\Modules\Common\Services\Carbon;

/**
 * Country-level grid carbon intensity (grams CO2 per kWh of electricity)
 * lifted from Ember/IEA 2024 datasets and rounded. We carry only a small
 * table — countries not present fall back to the global average. The
 * table is intentionally small + static so we never need a network call
 * to compute a snapshot, and so the methodology page can render the
 * full source list without paginating.
 *
 * Source-of-truth lives in the docs file referenced by the methodology
 * page. Bumping numbers requires bumping `MODEL_VERSION` in the
 * emissions service so existing snapshots remain reproducible.
 */
class GridIntensityTable
{
    public const GLOBAL_AVG_G_PER_KWH = 442.0;

    /** ISO-3166 alpha-2 → grams CO2 / kWh (electricity, 2024 estimate). */
    public const TABLE = [
        'US' => 369, 'CA' => 120, 'MX' => 423,
        'BR' => 95,  'AR' => 305,
        'GB' => 207, 'IE' => 299, 'FR' => 56,  'DE' => 380, 'ES' => 158,
        'IT' => 257, 'NL' => 268, 'BE' => 156, 'PT' => 180, 'CH' => 47,
        'PL' => 661, 'SE' => 41,  'NO' => 30,  'DK' => 151, 'FI' => 79,
        'AT' => 110, 'CZ' => 414, 'GR' => 332, 'RO' => 240,
        'RU' => 351, 'TR' => 442, 'UA' => 224,
        'IN' => 713, 'CN' => 537, 'JP' => 466, 'KR' => 431,
        'ID' => 706, 'PH' => 612, 'TH' => 482, 'VN' => 466,
        'MY' => 619, 'SG' => 408, 'AU' => 511, 'NZ' => 119,
        'ZA' => 928, 'NG' => 312, 'EG' => 442, 'MA' => 706,
        'AE' => 444, 'SA' => 580, 'IL' => 522,
    ];

    public static function intensity(string $countryCode): float
    {
        $cc = strtoupper(trim($countryCode));
        return (float) (self::TABLE[$cc] ?? self::GLOBAL_AVG_G_PER_KWH);
    }

    /**
     * Weighted-average intensity for a {country_code => percent} mix.
     * Percentages may sum to anything (we re-normalise) so callers
     * don't have to massage their PageSession query output.
     */
    public static function weightedAverage(array $countryMix): float
    {
        $total = 0.0;
        $weighted = 0.0;
        foreach ($countryMix as $cc => $share) {
            $share = (float) $share;
            if ($share <= 0) continue;
            $total    += $share;
            $weighted += self::intensity((string) $cc) * $share;
        }
        if ($total <= 0) return self::GLOBAL_AVG_G_PER_KWH;
        return round($weighted / $total, 2);
    }
}
