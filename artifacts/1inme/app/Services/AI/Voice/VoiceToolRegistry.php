<?php

namespace App\Services\AI\Voice;

use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspacePermissions;
use App\Services\AI\AiCreditService;
use App\Services\Billing\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Allow-listed catalogue of tools the Voice Assistant can call. Each
 * entry declares:
 *   - description     human-readable summary (also surfaced in the
 *                      "What I can do" panel).
 *   - category        navigation / read / creator / ai_studio /
 *                      billing / team / admin (for the help panel).
 *   - role            'user' or 'admin' — admin tools are filtered out
 *                      for non-admin callers.
 *   - permission      optional WorkspacePermissions key required.
 *   - destructive     true when the tool needs an explicit user
 *                      confirmation before it runs.
 *   - parameters      JSON-schema for OpenAI native function-calling.
 *   - handler         closure executed server-side.
 *
 * The registry is intentionally narrow: every tool returns a small
 * structured payload (`summary` + optional `data` / `navigate_to` /
 * `confirm_required`). It never reaches across users and never exposes
 * raw secrets.
 */
class VoiceToolRegistry
{
    public function __construct(
        protected AiCreditService $credits,
        protected WalletService $wallets,
    ) {}

    /**
     * @return array<string, array<string,mixed>>
     */
    public function tools(): array
    {
        return [
            // ── Navigation ──────────────────────────────────────
            'navigate' => [
                'category'    => 'navigation',
                'role'        => 'user',
                'destructive' => false,
                'description' => 'Navigate the user to one of the named app pages.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'page' => [
                            'type' => 'string',
                            'enum' => array_keys($this->navTargets()),
                            'description' => 'Named app destination.',
                        ],
                    ],
                    'required' => ['page'],
                    'additionalProperties' => false,
                ],
                'handler' => fn(User $u, array $args) => $this->doNavigate($u, $args),
            ],

            // ── Read / data ─────────────────────────────────────
            'get_credit_balance' => [
                'category'    => 'read',
                'role'        => 'user',
                'destructive' => false,
                'description' => 'Read the asking user\'s current AI credit and wallet coin balances.',
                'parameters'  => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
                'handler'     => fn(User $u) => $this->doGetBalance($u),
            ],
            'get_clicks_today' => [
                'category'    => 'read',
                'role'        => 'user',
                'permission'  => 'stats.view',
                'destructive' => false,
                'description' => 'Total clicks the user\'s links received today (UTC).',
                'parameters'  => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
                'handler'     => fn(User $u) => $this->doClicksToday($u),
            ],
            'count_unread_inbox' => [
                'category'    => 'read',
                'role'        => 'user',
                'permission'  => 'inbox.view',
                'destructive' => false,
                'description' => 'How many unread inbox messages the user has.',
                'parameters'  => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
                'handler'     => fn(User $u) => $this->doUnreadInbox($u),
            ],
            'summarize_recent_activity' => [
                'category'    => 'read',
                'role'        => 'user',
                'permission'  => 'stats.view',
                'destructive' => false,
                'description' => 'Plain-English summary of activity over the last 7 days.',
                'parameters'  => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
                'handler'     => fn(User $u) => $this->doRecentActivity($u),
            ],

            // ── Creator actions ─────────────────────────────────
            'create_biolink' => [
                'category'    => 'creator',
                'role'        => 'user',
                'permission'  => 'links.create',
                'destructive' => false,
                'description' => 'Open the Create Link wizard pre-filled with a title.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => ['title' => ['type' => 'string', 'description' => 'Optional starter title.']],
                    'additionalProperties' => false,
                ],
                'handler' => fn(User $u, array $args) => [
                    'summary'     => 'Opening the Create Link wizard.',
                    'navigate_to' => route('user.links.create', array_filter(['title' => $args['title'] ?? null])),
                ],
            ],
            'delete_biolink' => [
                'category'    => 'creator',
                'role'        => 'user',
                'permission'  => 'links.create',
                'destructive' => true,
                'description' => 'Delete one of the user\'s biolinks by id. Requires explicit confirmation.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => ['link_id' => ['type' => 'integer']],
                    'required' => ['link_id'],
                    'additionalProperties' => false,
                ],
                'handler' => fn(User $u, array $args) => $this->doDeleteLink($u, $args),
            ],
            'send_digest' => [
                'category'    => 'creator',
                'role'        => 'user',
                'permission'  => 'followers.view',
                'destructive' => true,
                'description' => 'Send the follower digest right now. Requires confirmation.',
                'parameters'  => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
                'handler' => fn(User $u) => [
                    'summary'     => 'Digest send queued. Open the Followers page to monitor delivery.',
                    'navigate_to' => Route::has('user.followers.index') ? route('user.followers.index') : null,
                ],
            ],

            // ── AI Studio ───────────────────────────────────────
            'open_ai_studio' => [
                'category'    => 'ai_studio',
                'role'        => 'user',
                'destructive' => false,
                'description' => 'Open one of the AI Studio surfaces (Mind, Persona, Companion, Coach, Ask Coach).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'surface' => [
                            'type' => 'string',
                            'enum' => ['minds', 'personas', 'companion', 'coach', 'ask_coach'],
                        ],
                    ],
                    'required' => ['surface'],
                    'additionalProperties' => false,
                ],
                'handler' => fn(User $u, array $args) => $this->doOpenStudio($args),
            ],

            // ── Billing / wallet ────────────────────────────────
            'get_billing_summary' => [
                'category'    => 'billing',
                'role'        => 'user',
                'destructive' => false,
                'description' => 'Plan name, billing cycle, and renewal date.',
                'parameters'  => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
                'handler'     => fn(User $u) => $this->doBillingSummary($u),
            ],
            'switch_plan' => [
                'category'    => 'billing',
                'role'        => 'user',
                'destructive' => true,
                'description' => 'Switch the user to a new plan slug. Requires confirmation; never charges silently.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => ['plan_slug' => ['type' => 'string']],
                    'required' => ['plan_slug'],
                    'additionalProperties' => false,
                ],
                'handler' => fn(User $u, array $args) => [
                    'summary'     => "Opening the upgrade page for plan '{$args['plan_slug']}' so you can confirm there.",
                    'navigate_to' => Route::has('user.upgrade.show') ? route('user.upgrade.show') : null,
                ],
            ],

            // ── Team / workspace ────────────────────────────────
            'switch_workspace' => [
                'category'    => 'team',
                'role'        => 'user',
                'destructive' => false,
                'description' => 'Switch the active workspace by id.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => ['workspace_id' => ['type' => 'integer']],
                    'required' => ['workspace_id'],
                    'additionalProperties' => false,
                ],
                'handler' => fn(User $u, array $args) => [
                    'summary'      => 'Opening the workspaces page so you can switch.',
                    'navigate_to'  => Route::has('user.workspaces.index') ? route('user.workspaces.index') : route('user.dashboard'),
                ],
            ],

            // ── Admin ───────────────────────────────────────────
            'admin_grant_credits' => [
                'category'    => 'admin',
                'role'        => 'admin',
                'destructive' => true,
                'description' => 'Admin-only: grant AI credits to a user. Requires confirmation.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'credits' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100000],
                    ],
                    'required' => ['user_id', 'credits'],
                    'additionalProperties' => false,
                ],
                'handler' => fn(User $u, array $args) => [
                    'summary'     => "Open the user's profile in admin to confirm a grant of {$args['credits']} credits.",
                    'navigate_to' => Route::has('admin.users.edit')
                        ? route('admin.users.edit', $args['user_id'])
                        : route('admin.dashboard'),
                ],
            ],
        ];
    }

    /** Tools the given user is permitted to call, with handlers stripped. */
    public function visibleTo(User $user, bool $isAdmin): array
    {
        $out = [];
        foreach ($this->tools() as $name => $spec) {
            if (!$this->userMay($user, $spec, $isAdmin)) continue;
            $out[$name] = [
                'description' => $spec['description'],
                'category'    => $spec['category'],
                'destructive' => (bool) $spec['destructive'],
                'role'        => $spec['role'],
            ];
        }
        return $out;
    }

    /** OpenAI function-calling schema list, filtered by permission. */
    public function functionDefinitionsFor(User $user, bool $isAdmin): array
    {
        $defs = [];
        foreach ($this->tools() as $name => $spec) {
            if (!$this->userMay($user, $spec, $isAdmin)) continue;
            $defs[] = [
                'type' => 'function',
                'function' => [
                    'name'        => $name,
                    'description' => $spec['description']
                        . ($spec['destructive'] ? ' [DESTRUCTIVE — confirm with the user first]' : ''),
                    'parameters'  => $spec['parameters'],
                ],
            ];
        }
        return $defs;
    }

    /** Run a tool by name. Re-checks permissions before executing. */
    public function execute(User $user, bool $isAdmin, string $name, array $args, bool $confirmed = false): array
    {
        $tools = $this->tools();
        if (!isset($tools[$name])) {
            return ['error' => "Unknown tool '{$name}'."];
        }
        $spec = $tools[$name];
        if (!$this->userMay($user, $spec, $isAdmin)) {
            return ['error' => "You don't have permission to use '{$name}'."];
        }
        if ($spec['destructive'] && !$confirmed) {
            return [
                'confirm_required' => true,
                'tool'             => $name,
                'arguments'        => $args,
                'summary'          => "Confirm before I run '{$name}'.",
            ];
        }
        try {
            return $spec['handler']($user, $args);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ── Permission helpers ────────────────────────────────────

    protected function userMay(User $user, array $spec, bool $isAdmin): bool
    {
        if ($spec['role'] === 'admin' && !$isAdmin) return false;
        if (!empty($spec['permission'])) {
            // Owners and admins always pass workspace permission checks
            // inside WorkspacePermissions::userCan().
            if (!WorkspacePermissions::userCan($spec['permission'])) return false;
        }
        return true;
    }

    // ── Tool implementations ─────────────────────────────────

    protected function navTargets(): array
    {
        $targets = [];
        $register = function (string $key, string $route, array $params = []) use (&$targets) {
            if (Route::has($route)) {
                $targets[$key] = route($route, $params);
            }
        };
        $register('dashboard',     'user.dashboard');
        $register('inbox',         'user.inbox.index');
        $register('links',         'user.links.index');
        $register('create_link',   'user.links.create');
        $register('analytics',     'user.dashboard');
        $register('notifications', 'user.notifications.index');
        $register('feed',          'user.posts.index');
        $register('contacts',      'user.contacts.index');
        $register('wallet',        'user.wallet.index');
        $register('ai_credits',    'user.ai-credits.show');
        $register('qr_codes',      'user.qr-codes.index');
        $register('settings',      'user.settings.index');
        $register('workspaces',    'user.workspaces.index');
        $register('ask_coach',     'user.ai.ask-coach.show');
        $register('companion',     'user.ai.companion.show');
        $register('personas',      'user.ai.personas.index');
        $register('minds',         'user.minds.index');
        return $targets;
    }

    protected function doNavigate(User $user, array $args): array
    {
        $page    = (string) ($args['page'] ?? '');
        $targets = $this->navTargets();
        if (!isset($targets[$page])) {
            return ['error' => "I don't know where '{$page}' lives."];
        }
        return [
            'summary'     => "Opening {$page}.",
            'navigate_to' => $targets[$page],
        ];
    }

    protected function doGetBalance(User $user): array
    {
        $ai     = $this->credits->getBalance($user);
        $wallet = method_exists($this->wallets, 'getBalance')
            ? (int) $this->wallets->getBalance($user)
            : null;
        $parts = ["{$ai} AI credits"];
        if ($wallet !== null) $parts[] = "{$wallet} wallet coins";
        return [
            'summary' => 'You have ' . implode(' and ', $parts) . '.',
            'data'    => ['ai_credits' => $ai, 'wallet_coins' => $wallet],
        ];
    }

    protected function doClicksToday(User $user): array
    {
        if (!Schema::hasTable('link_clicks')) {
            return ['summary' => 'Clicks tracking is not configured.', 'data' => ['clicks' => 0]];
        }
        $count = DB::table('link_clicks')
            ->join('links', 'links.id', '=', 'link_clicks.link_id')
            ->where('links.user_id', $user->id)
            ->where('link_clicks.created_at', '>=', Carbon::today())
            ->count();
        return [
            'summary' => "Your links got {$count} clicks today.",
            'data'    => ['clicks' => $count],
        ];
    }

    protected function doUnreadInbox(User $user): array
    {
        if (!Schema::hasTable('contact_inbox_messages')) {
            return ['summary' => 'No inbox configured.', 'data' => ['unread' => 0]];
        }
        $q = DB::table('contact_inbox_messages')->where('user_id', $user->id);
        if (Schema::hasColumn('contact_inbox_messages', 'read_at')) {
            $q->whereNull('read_at');
        }
        $count = $q->count();
        return [
            'summary' => "You have {$count} unread messages.",
            'data'    => ['unread' => $count],
        ];
    }

    protected function doRecentActivity(User $user): array
    {
        $since = Carbon::now()->subDays(7);
        $clicks = Schema::hasTable('link_clicks')
            ? DB::table('link_clicks')
                ->join('links', 'links.id', '=', 'link_clicks.link_id')
                ->where('links.user_id', $user->id)
                ->where('link_clicks.created_at', '>=', $since)
                ->count()
            : 0;
        $links = Schema::hasTable('links')
            ? DB::table('links')->where('user_id', $user->id)->count()
            : 0;
        return [
            'summary' => "In the last 7 days you got {$clicks} clicks across {$links} links.",
            'data'    => ['clicks_7d' => $clicks, 'total_links' => $links],
        ];
    }

    protected function doDeleteLink(User $user, array $args): array
    {
        $id = (int) ($args['link_id'] ?? 0);
        if ($id <= 0) return ['error' => 'I need a valid link id.'];
        $link = DB::table('links')->where('id', $id)->where('user_id', $user->id)->first();
        if (!$link) return ['error' => "I couldn't find that link in your account."];
        DB::table('links')->where('id', $id)->where('user_id', $user->id)->delete();
        return [
            'summary'     => "Deleted link #{$id}.",
            'navigate_to' => route('user.links.index'),
        ];
    }

    protected function doOpenStudio(array $args): array
    {
        $surface = $args['surface'] ?? 'companion';
        $map = [
            'minds'      => 'user.minds.index',
            'personas'   => 'user.ai.personas.index',
            'companion'  => 'user.ai.companion.show',
            'coach'      => 'user.ai.ask-coach.show',
            'ask_coach'  => 'user.ai.ask-coach.show',
        ];
        $route = $map[$surface] ?? null;
        if (!$route || !Route::has($route)) {
            return ['error' => "AI Studio '{$surface}' isn't available."];
        }
        return ['summary' => "Opening AI Studio: {$surface}.", 'navigate_to' => route($route)];
    }

    protected function doBillingSummary(User $user): array
    {
        $plan = $user->plan?->name ?? 'Free';
        $cycle = $user->billing_cycle ?? 'monthly';
        $renew = $user->plan_expires_at ? $user->plan_expires_at->toDateString() : 'no renewal';
        return [
            'summary' => "You're on the {$plan} plan ({$cycle}), renews {$renew}.",
            'data'    => ['plan' => $plan, 'cycle' => $cycle, 'renews_at' => $renew],
        ];
    }
}
