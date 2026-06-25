<?php

namespace App\Services\AI\AskCoach;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\Billing\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only data tools the AI Coach can call to ground every answer in
 * the asking user's live Sayzio data. Each tool:
 *
 *   - is scoped to a single user (the workspace owner of the asker, so
 *     a viewer chatting in a workspace sees the workspace's data, not
 *     their personal links);
 *   - returns a small structured payload — `summary` text the LLM can
 *     splice into its prompt, `data` for the inline chart/table
 *     renderer, optional `actions[]` deep-links into the right Sayzio
 *     page, and `citation` so each answer can show what it leaned on.
 *
 * The registry is deliberately a thin layer on top of Eloquent — these
 * tools never reach across user boundaries and never expose raw PII
 * (passwords, vault secrets, message bodies). Adding a new tool is one
 * method + one entry in `tools()`.
 */
class AskCoachToolRegistry
{


    /**
     * Catalogue (id => label + summary). Surfaced to the user in the
     * "Coach can see…" panel and to admins in the system-prompt screen.
     *
     * @return array<string,array{label:string,description:string}>
     */
    public function tools(): array
    {
        return [
            'biolinks'    => ['label' => 'Link in Bio',    'description' => 'Recent biolink pages, click counts, active state.'],
            'links'       => ['label' => 'Short Links', 'description' => 'Per-link clicks, top performers, dead links.'],
            'analytics'   => ['label' => 'Analytics',   'description' => 'Clicks over time, device split, drop-off funnel.'],
            'payments'    => ['label' => 'Payments',    'description' => 'Wallet coin balance, billing plan.'],
            'audience'    => ['label' => 'Audience',    'description' => 'Followers, subscribers, recent growth.'],
            'account'     => ['label' => 'Account',     'description' => 'Plan, wallet coins, recent invoices.'],
        ];
    }

    /**
     * Expose the catalogue as OpenAI function-calling tool definitions.
     * Every tool is parameter-less because each one is implicitly
     * scoped to the asking user's workspace — the model just decides
     * *whether* to ask for that snapshot, not *whose* snapshot to ask
     * for. Returns the JSON-serialisable shape the chat API expects.
     *
     * @return list<array{type:string,function:array{name:string,description:string,parameters:array}}>
     */
    public function functionDefinitions(): array
    {
        $defs = [];
        foreach ($this->tools() as $name => $meta) {
            $defs[] = [
                'type' => 'function',
                'function' => [
                    'name'        => $name,
                    'description' => $meta['description'],
                    // Empty object schema: callable with no arguments.
                    // `additionalProperties=false` keeps the model from
                    // inventing parameters we'd then have to ignore.
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => (object) [],
                        'additionalProperties' => false,
                    ],
                ],
            ];
        }
        return $defs;
    }

    /**
     * Fallback router used only when native tool-calling is
     * unavailable (e.g. the chosen model doesn't support it, or the
     * tools-enabled call errored out). Cheap keyword matching keeps
     * the chat answering with grounded data instead of giving up.
     *
     * @return list<string>
     */
    public function pickToolsForQuestion(string $question): array
    {
        $q = strtolower($question);
        $picked = [];

        $rules = [
            'biolinks'  => ['biolink', 'bio link', 'profile page', 'block', 'cta'],
            'links'     => ['short link', 'link ', 'click', 'redirect', 'utm', 'dead link'],
            'analytics' => ['analytic', 'traffic', 'visitor', 'drop', 'funnel', 'conversion', 'bounce', 'mobile', 'desktop'],
            'payments'  => ['sale', 'revenue', 'payment', 'refund', 'checkout', 'product', 'price', 'paypal'],
            'audience'  => ['follower', 'subscriber', 'audience', 'fans', 'growth', 'lead'],
            'account'   => ['plan', 'credit', 'wallet', 'billing', 'invoice', 'upgrade', 'free tier'],
        ];

        foreach ($rules as $tool => $needles) {
            foreach ($needles as $n) {
                if (str_contains($q, $n)) { $picked[] = $tool; break; }
            }
        }

        // Fallback: when no keyword fires, give the model a general
        // overview (analytics + account) so it isn't answering blind.
        if (!$picked) $picked = ['analytics', 'account'];

        return array_values(array_unique($picked));
    }

    /**
     * Run the named tool for $user. Always returns the standard shape:
     *   ['tool' => string, 'summary' => string, 'data' => array,
     *    'actions' => list<{label,url,reason}>,
     *    'citation' => ['label','source']]
     *
     * Tools that error out for a given user (missing relation, etc)
     * return an empty `summary` so the runtime can skip them silently.
     */
    public function run(string $tool, User $user): array
    {
        $base = ['tool' => $tool, 'summary' => '', 'data' => [], 'actions' => [], 'citation' => null];
        try {
            $payload = match ($tool) {
                'biolinks'  => $this->biolinks($user),
                'links'     => $this->links($user),
                'analytics' => $this->analytics($user),
                'payments'  => $this->payments($user),
                'audience'  => $this->audience($user),
                'account'   => $this->account($user),
                default     => $base,
            };
        } catch (\Throwable $e) {
            return $base;
        }
        return array_merge($base, $payload, ['tool' => $tool]);
    }

    // ── individual tools ───────────────────────────────────────────

    protected function biolinks(User $user): array
    {
        $rows = Link::query()
            ->where('user_id', $user->id)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'title', 'alias', 'is_active', 'total_clicks']);

        if ($rows->isEmpty()) {
            return ['summary' => 'You have no Link in Bio pages yet.'];
        }

        $lines = ["You have {$rows->count()} recent Link in Bio page(s):"];
        $tableRows = [];
        foreach ($rows as $r) {
            $lines[] = sprintf('- "%s" (alias %s) — %d clicks · %s',
                $r->title ?: 'Untitled', $r->alias, (int) $r->total_clicks,
                $r->is_active ? 'live' : 'paused');
            $tableRows[] = [
                'title'    => (string) ($r->title ?: 'Untitled'),
                'alias'    => (string) $r->alias,
                'clicks'   => (int) $r->total_clicks,
                'is_active'=> (bool) $r->is_active,
            ];
        }

        return [
            'summary' => implode("\n", $lines),
            'data'    => ['kind' => 'table', 'columns' => ['Title', 'Alias', 'Clicks', 'Status'], 'rows' => $tableRows],
            'citation'=> ['label' => 'Your Link in Bio pages', 'source' => 'biolinks'],
        ];
    }

    protected function links(User $user): array
    {
        $rows = Link::query()
            ->where('user_id', $user->id)
            ->whereNotIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get(['id', 'type', 'title', 'alias', 'long_url', 'total_clicks', 'is_active']);

        if ($rows->isEmpty()) {
            return ['summary' => 'You have no short, file or event links yet.'];
        }

        $lines = ['Top links by lifetime clicks:'];
        $bars = [];
        foreach ($rows as $r) {
            $lines[] = sprintf('- [%s] %s → %d clicks (%s)',
                $r->type, $r->alias, (int) $r->total_clicks, $r->is_active ? 'live' : 'paused');
            $bars[] = [
                'label' => (string) $r->alias,
                'value' => (int) $r->total_clicks,
            ];
        }

        return [
            'summary' => implode("\n", $lines),
            'data'    => ['kind' => 'bar', 'series' => $bars],
            'citation'=> ['label' => 'Your short links', 'source' => 'links'],
        ];
    }

    /**
     * Daily clicks for the last 14 days + a tiny mobile/desktop split,
     * so Coach can answer "where's my traffic from?" with a real chart
     * rather than vibes.
     */
    protected function analytics(User $user): array
    {
        $since = now()->subDays(14)->startOfDay();

        $linkIds = Link::query()->where('user_id', $user->id)->pluck('id');
        if ($linkIds->isEmpty()) {
            return ['summary' => 'No traffic in the last 14 days — you have no links to track.'];
        }

        // Daily click totals. Use an ANSI-portable date cast so this
        // works on MySQL/Postgres/SQLite alike (tests run on sqlite).
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "date(created_at)"
            : "DATE(created_at)";

        $daily = DB::table('link_clicks')
            ->whereIn('link_id', $linkIds)
            ->where('created_at', '>=', $since)
            ->selectRaw("$dateExpr as day, count(*) as c")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $series = [];
        foreach ($daily as $row) {
            $series[] = ['label' => (string) $row->day, 'value' => (int) $row->c];
        }
        $total14 = (int) array_sum(array_column($series, 'value'));

        // Device split — the column name has shifted historically; try
        // a couple of common ones and fall back to "unknown" so we
        // never error the chat.
        $deviceCol = null;
        foreach (['device_type', 'device', 'platform'] as $cand) {
            if (Schema::hasColumn('link_clicks', $cand)) { $deviceCol = $cand; break; }
        }
        $devices = [];
        if ($deviceCol) {
            $rows = DB::table('link_clicks')
                ->whereIn('link_id', $linkIds)
                ->where('created_at', '>=', $since)
                ->selectRaw("$deviceCol as d, count(*) as c")
                ->groupBy('d')
                ->orderByDesc('c')
                ->get();
            foreach ($rows as $r) {
                $devices[] = ['label' => (string) ($r->d ?: 'unknown'), 'value' => (int) $r->c];
            }
        }

        $lines = [
            "Clicks (last 14 days): {$total14}.",
        ];
        if ($devices) {
            $top = $devices[0];
            $lines[] = "Top device: {$top['label']} ({$top['value']} clicks).";
        }

        $actions = [
            $this->action('See full analytics', 'user.dashboard',
                'Drill into your traffic dashboard for the full breakdown.'),
        ];

        return [
            'summary' => implode("\n", $lines),
            'data'    => [
                'kind'    => 'line',
                'series'  => $series,
                'devices' => $devices,
            ],
            'actions' => $actions,
            'citation'=> ['label' => 'Click history (last 14 days)', 'source' => 'analytics'],
        ];
    }

    protected function payments(User $user): array
    {
        try {
            $coins = (int) app(WalletService::class)->getBalance($user);
        } catch (\Throwable $e) {
            $coins = 0;
        }

        // Best-effort revenue: sum Invoice.amount over the last 30 days
        // when the table exists. Coach should still answer "no recent
        // sales" rather than crash if the user has never billed anyone.
        $revenue30 = 0; $invoiceCount = 0;
        if (Schema::hasTable('invoices')) {
            $row = DB::table('invoices')
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as total')
                ->first();
            $revenue30   = (float) ($row->total ?? 0);
            $invoiceCount = (int) ($row->c ?? 0);
        }

        $lines = [
            "Wallet coins on hand: {$coins}.",
            "Last 30 days — {$invoiceCount} invoice(s), revenue " . number_format($revenue30, 2) . '.',
        ];

        $actions = [
            $this->action('Open billing', 'user.dashboard', 'Review payments, refunds and product performance.'),
        ];

        return [
            'summary' => implode("\n", $lines),
            'data'    => [
                'kind'      => 'kv',
                'pairs'     => [
                    ['key' => 'Wallet coins', 'value' => (string) $coins],
                    ['key' => 'Invoices (30d)', 'value' => (string) $invoiceCount],
                    ['key' => 'Revenue (30d)', 'value' => number_format($revenue30, 2)],
                ],
            ],
            'actions' => $actions,
            'citation'=> ['label' => 'Payments & wallet', 'source' => 'payments'],
        ];
    }

    protected function audience(User $user): array
    {
        $followers = (int) ($user->followers_count ?? 0);
        $subscribers = 0;
        try {
            if (method_exists($user, 'subscribers')) {
                $subscribers = (int) $user->subscribers()->count();
            }
        } catch (\Throwable $e) {}

        $lines = ["Followers: {$followers}; subscribers: {$subscribers}."];

        return [
            'summary' => implode("\n", $lines),
            'data'    => [
                'kind'  => 'kv',
                'pairs' => [
                    ['key' => 'Followers',   'value' => (string) $followers],
                    ['key' => 'Subscribers', 'value' => (string) $subscribers],
                ],
            ],
            'actions' => [
                $this->action('See followers', 'user.followers.index', 'Open the followers list to segment or message them.'),
            ],
            'citation'=> ['label' => 'Audience snapshot', 'source' => 'audience'],
        ];
    }

    protected function account(User $user): array
    {
        $plan = $user->plan_id
            ? (optional($user->plan)->slug ?? "plan #{$user->plan_id}")
            : 'free';
        $coins = 0;
        try { $coins = (int) app(WalletService::class)->getBalance($user); } catch (\Throwable $e) {}

        $lines = [
            "Current plan: {$plan}.",
            "Wallet coins: {$coins} (AI usage is charged from your coin wallet).",
        ];

        return [
            'summary' => implode("\n", $lines),
            'data'    => [
                'kind'  => 'kv',
                'pairs' => [
                    ['key' => 'Plan',       'value' => $plan],
                    ['key' => 'Coins',      'value' => (string) $coins],
                ],
            ],
            'actions' => [
                $this->action('Top up coins', 'user.wallet.buy', 'Buy more coins to keep chatting with Coach.'),
            ],
            'citation'=> ['label' => 'Account & billing', 'source' => 'account'],
        ];
    }

    /**
     * Build a deep-link action only when the named route resolves —
     * keeps Coach from pointing users at 404s if a feature route is
     * renamed or feature-flagged off.
     */
    protected function action(string $label, string $routeName, string $reason): ?array
    {
        try {
            if (!Route::has($routeName)) return null;
            return [
                'label'  => $label,
                'url'    => route($routeName),
                'reason' => $reason,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
