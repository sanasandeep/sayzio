<?php

namespace App\Services;

use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\User;

/**
 * Task #6739 — seed a Marketing Plan Calculator payload from an AI
 * Marketing Strategist plan.
 *
 * Pure PHP transformer (no AI spend): it starts from the spreadsheet
 * benchmarks in {@see MarketingPlanDefaults}, then
 *  - re-weights the channel allocations toward the channels the
 *    strategist actually recommended (paid + organic plays),
 *  - pulls the annual ad budget from the strategy's stated budget
 *    parameter when it can be parsed,
 *  - carries over the company/project name.
 *
 * Everything it produces is a *starting point* — the calculator editor
 * keeps every field editable before the plan is saved.
 */
class MarketingPlanAiSeed
{
    /**
     * Keyword → calculator channel-key map used to recognise the
     * strategist's free-form channel names ("Meta Ads", "Google Search"…).
     *
     * @var array<string,array<int,string>>
     */
    protected const CHANNEL_KEYWORDS = [
        'instagram'  => ['instagram', 'reels', 'ig '],
        'facebook'   => ['facebook', 'meta'],
        'whatsapp'   => ['whatsapp'],
        'twitter'    => ['twitter', 'x ads', ' x '],
        'linkedin'   => ['linkedin'],
        'gdisplay'   => ['display', 'discovery'],
        'youtube'    => ['youtube', 'video ads', 'pre-roll'],
        'amazon'     => ['amazon', 'retail media'],
        'othersoc'   => ['tiktok', 'pinterest', 'snapchat', 'reddit'],
        'search'     => ['search', 'google ads', 'ppc', 'sem'],
        'seo'        => ['seo', 'content marketing', 'blog'],
        'email'      => ['email', 'newsletter'],
        'influencer' => ['influencer', 'affiliate', 'creator partnership', 'collab'],
        'events'     => ['event', 'webinar', 'meetup', 'workshop'],
        'pr'         => ['pr', 'press', 'newspaper', 'media outreach', 'earned media'],
    ];

    /** Matched channels get this multiplier on their default allocation before re-normalising. */
    protected const BOOST = 3.0;

    /**
     * Build the seeded payload.
     *
     * @return array{payload:array<string,mixed>,name:string,matched:array<int,string>}
     */
    public static function fromStrategy(MarketingStrategy $strategy, ?User $user = null): array
    {
        $payload = MarketingPlanDefaults::defaults($user);

        $matchedKeys = self::matchedChannelKeys($strategy);
        $matchedNames = [];

        if ($matchedKeys) {
            // Re-weight: boost recommended channels, then re-normalise the
            // non-fixed allocations back to exactly 100%.
            $channels = $payload['channels'];
            $weights = [];
            $total = 0.0;
            foreach ($channels as $i => $c) {
                if (!empty($c['fixed'])) continue;
                $w = (float) ($c['alloc'] ?? 0) * (in_array($c['key'], $matchedKeys, true) ? self::BOOST : 1.0);
                $weights[$i] = $w;
                $total += $w;
            }
            if ($total > 0) {
                $running = 0.0;
                $lastIdx = array_key_last($weights);
                foreach ($weights as $i => $w) {
                    if ($i === $lastIdx) {
                        $alloc = round(100 - $running, 1); // absorb rounding drift
                    } else {
                        $alloc = round($w / $total * 100, 1);
                        $running += $alloc;
                    }
                    $channels[$i]['alloc'] = max(0, $alloc);
                    if (in_array($channels[$i]['key'], $matchedKeys, true)) {
                        $channels[$i]['notes'] = 'AI-recommended — ' . (string) $channels[$i]['notes'];
                        $matchedNames[] = (string) $channels[$i]['name'];
                    }
                }
                $payload['channels'] = $channels;
            }
        }

        // Annual ad budget from the strategy's stated budget parameter.
        $budget = self::parseAnnualBudgetInr((string) (($strategy->parameters['budget'] ?? '') ?: ($strategy->profile_snapshot['budget'] ?? '')), (string) ($strategy->parameters['currency'] ?? ($strategy->profile_snapshot['currency'] ?? '')));
        if ($budget !== null && $budget > 0) {
            $payload['annual_budget'] = $budget;
        }

        // Company / project name.
        $company = trim((string) ($strategy->profile_snapshot['business_name']
            ?? $strategy->profile_snapshot['name']
            ?? ''));
        if ($company !== '') {
            $payload['company'] = mb_substr($company, 0, 160);
        }

        return [
            'payload' => $payload,
            'name'    => mb_substr('AI: ' . (trim((string) $strategy->title) ?: 'Marketing Plan'), 0, 160),
            'matched' => $matchedNames,
        ];
    }

    /**
     * Which calculator channel keys the strategy's plays recommend.
     *
     * @return array<int,string>
     */
    protected static function matchedChannelKeys(MarketingStrategy $strategy): array
    {
        $plan = (array) $strategy->strategy;
        $haystacks = [];
        foreach (['paid', 'organic'] as $side) {
            foreach ((array) ($plan[$side] ?? []) as $play) {
                $haystacks[] = mb_strtolower(trim(
                    (string) (is_array($play) ? ($play['channel'] ?? '') : '') . ' '
                    . (string) (is_array($play) ? ($play['title'] ?? '') : '')
                ));
            }
        }
        $haystacks = array_filter($haystacks);
        if (!$haystacks) return [];

        $matched = [];
        foreach (self::CHANNEL_KEYWORDS as $key => $needles) {
            foreach ($haystacks as $h) {
                foreach ($needles as $needle) {
                    // Pad so word-ish needles like ' x ' can match at the edges.
                    if (str_contains(' ' . $h . ' ', $needle)) {
                        $matched[] = $key;
                        continue 3;
                    }
                }
            }
        }
        return $matched;
    }

    /**
     * Parse a free-form budget string ("₹50,000/month", "$500 monthly",
     * "2 lakh per year") into an annual INR figure, or null when it
     * cannot be understood. USD amounts convert at the calculator's
     * default display rate.
     */
    protected static function parseAnnualBudgetInr(string $raw, string $currency = ''): ?float
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/([\d][\d,\.]*)/', $raw, $m)) {
            return null;
        }
        $amount = (float) str_replace(',', '', $m[1]);
        if ($amount <= 0) return null;

        $lower = mb_strtolower($raw . ' ' . $currency);

        if (str_contains($lower, 'lakh') || str_contains($lower, 'lac')) $amount *= 100_000;
        elseif (str_contains($lower, 'crore')) $amount *= 10_000_000;
        elseif (preg_match('/(\d)\s*k\b/', $lower)) $amount *= 1_000;

        // USD → INR at the same default rate the calculator displays with.
        if (str_contains($lower, '$') || str_contains($lower, 'usd') || str_contains($lower, 'dollar')) {
            $amount *= 83.0;
        }

        // Assume monthly unless the text says otherwise.
        $annual = (str_contains($lower, 'year') || str_contains($lower, 'annual') || str_contains($lower, '/yr') || str_contains($lower, 'per yr'))
            ? $amount
            : $amount * 12;

        return round(min($annual, 10_000_000_000));
    }
}
