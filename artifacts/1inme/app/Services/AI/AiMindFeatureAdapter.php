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
    /** Whitelist of selectable features + human labels. */
    public const FEATURES = [
        'biolinks'    => 'Biolinks',
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

    public static function isFeature(string $key): bool
    {
        return array_key_exists($key, self::FEATURES);
    }

    public static function label(string $key): string
    {
        return self::FEATURES[$key] ?? $key;
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

    protected function biolinks(User $user): string
    {
        $links = $user->links()
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->latest('updated_at')
            ->limit(20)
            ->get(['id','title','alias','clicks','active','updated_at']);
        if ($links->isEmpty()) return 'You have no biolinks.';
        $lines = ["Biolinks ({$links->count()} most-recent):"];
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
            $credits = (int) app(AiCreditService::class)->getBalance($user);
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
