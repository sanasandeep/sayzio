<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;

/**
 * Live snapshots of a user's 1INME data, exposed as compact text the
 * agent can reason over. Centralised here so the Persona task and the
 * Coach task both read from the same surface.
 *
 * Each adapter is intentionally small — it returns a short bullet
 * summary and (where useful) a few representative rows. Returning
 * thousands of rows would just blow up the prompt and the embedding
 * bill.
 */
class AiMindFeatureAdapter
{
    /** Whitelist of selectable per-USER features + human labels. */
    public const FEATURES = [
        'biolinks'    => 'Link in Bio',
        'links'       => 'Short Links',
        'analytics'   => 'Analytics',
        'payments'    => 'Payments & Wallet',
        'audience'    => 'Followers & Subscribers',
        'vault'       => 'Vault',
        'tasks'       => 'Task Boards',
        'forms'       => 'Forms',
        'inbox'       => 'Inbox',
        'social'      => 'Social Connections',
        'profile'     => 'Profile',
    ];

    /**
     * PUBLIC, account-independent snapshots. These read ONLY the public
     * pricing/feature catalogue (the exact same active + public-scoped
     * sources that power the marketing /pricing and /features pages), so
     * they carry no user data and are identical for everyone — anonymous
     * visitors and signed-in users alike. They're produced without an
     * owner (see publicSnapshot()) and are intended to be attached to the
     * platform default Mind so the Site Assistant always reflects current
     * public offerings. They are deliberately NOT offered in the per-user
     * feature picker (which iterates FEATURES only).
     */
    public const PUBLIC_FEATURES = [
        'pricing'  => 'Live Pricing & Coins',
        'features' => 'Feature Catalog',
    ];

    public static function isFeature(string $key): bool
    {
        return array_key_exists($key, self::FEATURES)
            || array_key_exists($key, self::PUBLIC_FEATURES);
    }

    /**
     * True for snapshots that read only public/active catalogue data and
     * never touch a user account — so they can be served to anyone,
     * including anonymous visitors, with no leakage risk.
     */
    public static function isPublicFeature(string $key): bool
    {
        return array_key_exists($key, self::PUBLIC_FEATURES);
    }

    public static function label(string $key): string
    {
        return self::FEATURES[$key] ?? self::PUBLIC_FEATURES[$key] ?? $key;
    }

    /**
     * Returns a short text snapshot the LLM can splice into prompts.
     * Empty string means "no data / feature unavailable for this user".
     */
    public function snapshot(User $user, string $key): string
    {
        return match ($key) {
            'biolinks'  => $this->biolinks($user),
            'links'     => $this->links($user),
            'analytics' => $this->analytics($user),
            'payments'  => $this->payments($user),
            'audience'  => $this->audience($user),
            'vault'     => $this->vault($user),
            'tasks'     => $this->tasks($user),
            'forms'     => $this->forms($user),
            'inbox'     => $this->inbox($user),
            'social'    => $this->social($user),
            'profile'   => $this->profile($user),
            default     => '',
        };
    }

    /**
     * Account-independent snapshot for the PUBLIC_FEATURES keys. Takes no
     * user — every byte it returns comes from the public, active pricing /
     * feature catalogue, so the result is identical regardless of who is
     * asking. This is what lets the Site Assistant answer pricing/feature
     * questions for anonymous visitors without any data-leakage risk.
     */
    public function publicSnapshot(string $key): string
    {
        return match ($key) {
            'pricing'  => $this->pricing(),
            'features' => $this->featureCatalogue(),
            default    => '',
        };
    }

    /**
     * Live pricing snapshot — active, public (non-internal) plans with
     * their per-currency monthly/annual prices, plus active coin packages
     * with coin amount + bonus. Mirrors PricingPagesController::plans()
     * exactly (same `Plan::active()->public()` / `CoinPackage::active()`
     * scopes and the same PricingResolver price resolution) so it can
     * never surface an internal, archived, or inactive offering.
     */
    protected function pricing(): string
    {
        $currencies = ['USD', 'INR'];

        $plans = \App\Modules\Admin\Models\Plan::active()->public()->with('prices')->ordered()->get();
        $lines = ['Live 1INME plans (current public pricing, matches the /pricing page):'];
        foreach ($plans as $plan) {
            $priceBits = [];
            foreach ($currencies as $cur) {
                $monthly = \App\Services\PricingResolver::priceForCurrency($plan, $cur, 'monthly');
                $annual  = \App\Services\PricingResolver::priceForCurrency($plan, $cur, 'annual');
                $priceBits[] = sprintf('%s/mo or %s/yr', $monthly['formatted'], $annual['formatted']);
            }
            $tags = [];
            if ($plan->is_default) $tags[] = 'free/default';
            if ($plan->is_popular) $tags[] = 'most popular';
            $suffix = $tags ? ' (' . implode(', ', $tags) . ')' : '';
            $lines[] = sprintf('- %s%s: %s', $plan->name, $suffix, implode(' | ', $priceBits));
        }
        if ($plans->isEmpty()) {
            $lines[] = '- (No public plans are currently available.)';
        }

        $packages = \App\Modules\Admin\Models\CoinPackage::active()->with('prices')->ordered()->get();
        $lines[] = '';
        $lines[] = 'Coin packages (current public packs):';
        foreach ($packages as $pkg) {
            $priceBits = [];
            foreach ($currencies as $cur) {
                $pc = \App\Services\PricingResolver::priceForCurrency($pkg, $cur, 'monthly');
                $priceBits[] = $pc['formatted'];
            }
            $bonus = (int) $pkg->bonus_coins;
            $coins = $bonus > 0
                ? sprintf('%d coins + %d bonus = %d total', (int) $pkg->coin_amount, $bonus, $pkg->totalCoins())
                : sprintf('%d coins', (int) $pkg->coin_amount);
            $lines[] = sprintf('- %s: %s — %s', $pkg->name, $coins, implode(' / ', $priceBits));
        }
        if ($packages->isEmpty()) {
            $lines[] = '- (No coin packages are currently available.)';
        }

        return implode("\n", $lines);
    }

    /**
     * Live feature-catalogue snapshot — the public premium-feature list
     * (name, plain-English description, group) plus which public plans
     * unlock each feature. Reuses PremiumFeatures (the same catalogue the
     * /features page renders) and the `Plan::active()->public()` scope for
     * the unlock map, so internal/inactive plans never appear.
     */
    protected function featureCatalogue(): string
    {
        $plans   = \App\Modules\Admin\Models\Plan::active()->public()->ordered()->get();
        $unlocks = \App\Modules\Common\Support\PremiumFeatures::unlocksByFeature($plans);
        $planNames = $plans->pluck('name', 'slug');

        $byGroup = [];
        foreach (\App\Modules\Common\Support\PremiumFeatures::catalogue() as $entry) {
            $byGroup[$entry['group']][] = $entry;
        }

        $lines = ['Live 1INME feature catalog (matches the /features page):'];
        foreach ($byGroup as $group => $entries) {
            $lines[] = '';
            $lines[] = $group . ':';
            foreach ($entries as $entry) {
                $slugs = $unlocks[$entry['key']] ?? [];
                $names = array_values(array_filter(array_map(
                    fn ($s) => $planNames[$s] ?? null,
                    $slugs
                )));
                $unlock = $names ? ' [plans: ' . implode(', ', $names) . ']' : '';
                $desc = \Illuminate\Support\Str::limit((string) ($entry['description'] ?? ''), 180);
                $lines[] = sprintf('- %s — %s%s', $entry['name'], $desc, $unlock);
            }
        }

        return implode("\n", $lines);
    }

    protected function biolinks(User $user): string
    {
        $links = $user->links()
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->latest('updated_at')
            ->limit(20)
            ->get(['id','title','alias','clicks','active','updated_at']);
        if ($links->isEmpty()) return 'You have no Link in Bio pages.';
        $lines = ["Link in Bio ({$links->count()} most-recent):"];
        foreach ($links as $l) {
            $lines[] = sprintf('- %s (alias %s) — %d clicks, %s',
                $l->title ?? 'Untitled', $l->alias, (int) $l->clicks,
                ($l->active ?? true) ? 'active' : 'paused');
        }
        return implode("\n", $lines);
    }

    protected function links(User $user): string
    {
        $links = $user->links()
            ->whereNotIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->latest('updated_at')
            ->limit(30)
            ->get(['id','type','title','alias','target_url','clicks','active']);
        if ($links->isEmpty()) return 'You have no short/file/event links.';
        $lines = ["Short links ({$links->count()}):"];
        foreach ($links as $l) {
            $lines[] = sprintf('- [%s] %s → %s (%d clicks)',
                $l->type, $l->alias, $l->target_url ?? '(internal)', (int) $l->clicks);
        }
        return implode("\n", $lines);
    }

    protected function analytics(User $user): string
    {
        $totalClicks = (int) $user->links()->sum('clicks');
        $totalLinks  = (int) $user->links()->count();
        $top = $user->links()
            ->orderByDesc('clicks')
            ->limit(5)
            ->get(['title','alias','clicks']);
        $lines = [
            "Analytics summary:",
            "- {$totalLinks} total links, {$totalClicks} cumulative clicks.",
        ];
        if ($top->isNotEmpty()) {
            $lines[] = "- Top performers:";
            foreach ($top as $l) {
                $lines[] = sprintf('   • %s (%s) — %d clicks', $l->title ?? '—', $l->alias, (int) $l->clicks);
            }
        }
        return implode("\n", $lines);
    }

    protected function payments(User $user): string
    {
        try {
            $wallet = app(\App\Services\Billing\WalletService::class);
            $coins  = (int) $wallet->getBalance($user);
        } catch (\Throwable $e) {
            $coins = 0;
        }
        try {
            $credits = (int) app(AiUsageCharger::class)->getBalance($user);
        } catch (\Throwable $e) {
            $credits = 0;
        }
        $plan = $user->plan_id ? "plan #{$user->plan_id}" : 'free plan';
        return "Payments — wallet coins: {$coins}; AI credits: {$credits}; current {$plan}.";
    }

    protected function audience(User $user): string
    {
        $followers   = (int) ($user->followers_count ?? 0);
        $subscribers = method_exists($user, 'subscribers')
            ? (int) $user->subscribers()->count() : 0;
        return "Audience — followers: {$followers}; subscribers: {$subscribers}.";
    }

    protected function vault(User $user): string
    {
        // Vault contents are encrypted on purpose — surface only counts.
        $clientCount = method_exists($user, 'vaultClients')
            ? (int) $user->vaultClients()->count() : 0;
        $credCount   = method_exists($user, 'vaultCredentials')
            ? (int) $user->vaultCredentials()->count() : 0;
        return "Vault — {$clientCount} clients, {$credCount} stored credentials. (Secrets stay encrypted; never include their values.)";
    }

    protected function tasks(User $user): string
    {
        if (!method_exists($user, 'taskBoards')) return '';
        $boards = $user->taskBoards()->limit(10)->get(['id','title']);
        if ($boards->isEmpty()) return 'No task boards yet.';
        $lines = ['Task boards:'];
        foreach ($boards as $b) $lines[] = "- {$b->title}";
        return implode("\n", $lines);
    }

    protected function forms(User $user): string
    {
        if (!method_exists($user, 'forms')) return '';
        $forms = $user->forms()->limit(10)->get(['id','name','active']);
        if ($forms->isEmpty()) return 'No forms yet.';
        $lines = ['Forms:'];
        foreach ($forms as $f) {
            $lines[] = sprintf('- %s (%s)', $f->name ?? 'Untitled', ($f->active ?? true) ? 'live' : 'paused');
        }
        return implode("\n", $lines);
    }

    protected function inbox(User $user): string
    {
        // Just the count — message bodies might contain PII the user
        // didn't consent to embed in an LLM prompt.
        try {
            $unread = (int) \App\Modules\User\Models\InboxReply::query()
                ->whereHas('conversation', fn($q) => $q->where('user_id', $user->id))
                ->where('status', 'open')
                ->count();
        } catch (\Throwable $e) {
            $unread = 0;
        }
        return "Inbox — {$unread} open items.";
    }

    protected function social(User $user): string
    {
        try {
            $accounts = \App\Modules\User\Models\SocialAccount::where('user_id', $user->id)
                ->get(['provider','status','followers_count']);
            if ($accounts->isEmpty()) return 'No social accounts connected.';
            $lines = ['Social accounts:'];
            foreach ($accounts as $a) {
                $lines[] = sprintf('- %s — %s (%s followers)',
                    $a->provider, $a->status ?? 'connected', $a->followers_count ?? '—');
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function profile(User $user): string
    {
        $bits = array_filter([
            $user->name ? "Name: {$user->name}" : null,
            $user->handle ? "Handle: @{$user->handle}" : null,
            $user->email ? "Email: {$user->email}" : null,
            $user->bio ? "Bio: " . \Illuminate\Support\Str::limit($user->bio, 240) : null,
            $user->country ? "Country: {$user->country}" : null,
            $user->timezone ? "Timezone: {$user->timezone}" : null,
        ]);
        return $bits ? "Profile:\n- " . implode("\n- ", $bits) : '';
    }
}
