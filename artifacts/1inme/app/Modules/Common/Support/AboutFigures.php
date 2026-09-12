<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\SiteStat;
use Illuminate\Support\Carbon;

/**
 * Resolves the three figures in the About page hero.
 *
 * These used to be typed by hand into the page's stored content, which is how
 * the live site ended up claiming "0 Years young", "1 Teammates" and 14K
 * creators on a site whose home page says 375,000. A hand-typed number is
 * wrong the moment nobody remembers to retype it.
 *
 * So a stat may now carry the literal value "auto", and the key that follows
 * the label decides where the real number comes from:
 *
 *   years     the whole years since the founding date
 *   team      the team size app setting
 *   creators  the first active row in Site Stats, the same source the home
 *             page's "by the numbers" band already reads
 *
 * Anything else is passed through untouched, so an admin can still type a
 * literal figure when they want one.
 */
class AboutFigures
{
    /** Sayzio's first day. Overridable in app settings. */
    public const FOUNDED_ON = '2023-05-05';

    /** Fallback when the team-size setting has never been saved. */
    public const TEAM_SIZE = 10;

    public static function foundedOn(): Carbon
    {
        $stored = AppSetting::get('company_founded_on', self::FOUNDED_ON);
        if (is_array($stored)) {
            $stored = reset($stored);
        }
        $raw = trim((string) $stored);

        try {
            return Carbon::parse($raw !== '' ? $raw : self::FOUNDED_ON);
        } catch (\Throwable $e) {
            return Carbon::parse(self::FOUNDED_ON);
        }
    }

    /** Whole years since founding, never negative. */
    public static function yearsSinceFounding(): int
    {
        return max(0, self::foundedOn()->diffInYears(Carbon::now()));
    }

    public static function teamSize(): int
    {
        $raw = AppSetting::get('company_team_size', self::TEAM_SIZE);
        if (is_array($raw)) {
            $raw = reset($raw);
        }
        $n = (int) $raw;

        return $n > 0 ? $n : self::TEAM_SIZE;
    }

    /**
     * The headline user figure, taken from the same Site Stats row the home
     * page uses so the two can never disagree.
     *
     * @return array{value:string,suffix:string}|null
     */
    public static function creators(): ?array
    {
        try {
            $stat = SiteStat::cachedActive()->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $stat) {
            return null;
        }

        return [
            // displayValue(), not ->value. The raw column holds what an admin
            // typed, which for this row is `3,75,000` -- Indian grouping, and
            // correct in Hyderabad. SiteStat::displayValue() exists to regroup
            // it as `375,000` for everyone else, and the home page goes through
            // it. Reading the column directly here is what put `375,000+` on
            // the home page and `3,75,000+` on About: one number, two
            // spellings, and a reader who concludes one of them is wrong.
            'value'  => $stat->displayValue(),
            'suffix' => (string) $stat->suffix,
        ];
    }

    /**
     * Resolve one stored hero stat into what should actually be printed.
     *
     * @param  array{value?:mixed,suffix?:mixed,label?:mixed}  $stat
     * @return array{value:string,suffix:string}
     */
    public static function resolve(array $stat): array
    {
        $value  = trim((string) ($stat['value'] ?? ''));
        $suffix = (string) ($stat['suffix'] ?? '');

        if (strtolower($value) !== 'auto') {
            return ['value' => $value, 'suffix' => $suffix];
        }

        $key = strtolower(trim((string) ($stat['key'] ?? $stat['label'] ?? '')));

        if (str_contains($key, 'year')) {
            return ['value' => (string) self::yearsSinceFounding(), 'suffix' => $suffix];
        }

        if (str_contains($key, 'team') || str_contains($key, 'mate')) {
            return ['value' => (string) self::teamSize(), 'suffix' => $suffix];
        }

        if (str_contains($key, 'creator') || str_contains($key, 'user')) {
            $creators = self::creators();
            if ($creators !== null) {
                return $creators;
            }
        }

        // An "auto" we cannot resolve prints nothing rather than the word.
        return ['value' => '', 'suffix' => ''];
    }
}
