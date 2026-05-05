<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\User;

/**
 * Per-creator and per-post country gating (Task #1211).
 *
 * Two-list model:
 *   - allow list: when non-empty, ONLY listed countries can view.
 *   - block list: every country except those listed can view.
 * Allow list wins when both are set on the same level. Per-post
 * overrides override creator-level lists when the post defines them.
 */
class CountryGate
{
    public function __construct(protected GeoIpService $geo) {}

    /**
     * Decide if $viewerIp may view $creator (and optionally a $post).
     * Returns ['allowed' => bool, 'reason' => ?string, 'country' => ?string].
     */
    public function decide(User $creator, ?CreatorPost $post, ?string $viewerIp): array
    {
        $cc = $viewerIp ? $this->geo->detectCountry($viewerIp) : null;
        $cc = $cc ? strtoupper($cc) : null;

        // Without a detectable country we fail open — every other
        // moderation surface (paywall, age gate) does the same to
        // avoid locking out legitimate viewers behind broken geoip.
        if (!$cc) {
            return ['allowed' => true, 'reason' => null, 'country' => null];
        }

        // Per-post overrides take precedence when set.
        $postAllow = is_array($post?->country_allow_list ?? null) ? $post->country_allow_list : null;
        $postBlock = is_array($post?->country_block_list ?? null) ? $post->country_block_list : null;
        if ($postAllow || $postBlock) {
            return $this->evaluate($cc, $postAllow, $postBlock, "this post");
        }

        // Profile-level lists.
        $cAllow = is_array($creator->country_allow_list ?? null) ? $creator->country_allow_list : null;
        $cBlock = is_array($creator->country_block_list ?? null) ? $creator->country_block_list : null;
        return $this->evaluate($cc, $cAllow, $cBlock, "this profile");
    }

    /**
     * Allow list wins over block list when both are set: an allow list
     * is an explicit "only these regions" decision, a block list is a
     * curated set of exceptions to "everyone else".
     */
    protected function evaluate(string $cc, ?array $allow, ?array $block, string $label): array
    {
        $allow = $allow ? array_map('strtoupper', $allow) : null;
        $block = $block ? array_map('strtoupper', $block) : null;

        if (!empty($allow)) {
            $ok = in_array($cc, $allow, true);
            return [
                'allowed' => $ok,
                'reason'  => $ok ? null : "The creator has not made {$label} available in your region ({$cc}).",
                'country' => $cc,
            ];
        }
        if (!empty($block) && in_array($cc, $block, true)) {
            return [
                'allowed' => false,
                'reason'  => "The creator has restricted {$label} in your region ({$cc}).",
                'country' => $cc,
            ];
        }
        return ['allowed' => true, 'reason' => null, 'country' => $cc];
    }

    /** ISO-3166-1 alpha-2 country codes the editor offers as quick-picks. */
    public const POPULAR_COUNTRIES = [
        'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
        'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France',
        'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands',
        'SE' => 'Sweden', 'NO' => 'Norway', 'BR' => 'Brazil',
        'MX' => 'Mexico', 'AR' => 'Argentina', 'JP' => 'Japan',
        'KR' => 'South Korea', 'IN' => 'India', 'PK' => 'Pakistan',
        'BD' => 'Bangladesh', 'ID' => 'Indonesia', 'PH' => 'Philippines',
        'TH' => 'Thailand', 'VN' => 'Vietnam', 'SG' => 'Singapore',
        'TR' => 'Turkey', 'SA' => 'Saudi Arabia', 'AE' => 'UAE',
        'EG' => 'Egypt', 'NG' => 'Nigeria', 'ZA' => 'South Africa',
        'RU' => 'Russia', 'UA' => 'Ukraine', 'PL' => 'Poland',
        'CN' => 'China', 'HK' => 'Hong Kong', 'TW' => 'Taiwan',
    ];
}
