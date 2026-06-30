<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use App\Modules\Admin\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Domain extends Model
{
    
    use BelongsToWorkspace;

    /** Cache key for the marketing showcase list of global domains. */
    public const SHOWCASE_CACHE_KEY = 'marketing.global_domains.showcase';

    /** Cache key for the set of admin-global (platform) domain ids. */
    public const PLATFORM_IDS_CACHE_KEY = 'domains.platform_ids';

    /** Static branded fallback shown when no global domains are configured. */
    public const SHOWCASE_FALLBACK = ['1in.me', 'bizs.club', 'getbio.one', 'Sayzio.app'];

protected $fillable = [
        'user_id', 'domain', 'type', 'is_verified', 'is_active', 'is_primary',
        'verification_token', 'cname_target', 'verified_at',
        'dns_status', 'dns_last_checked_at', 'dns_last_target',
        'dns_drift_started_at', 'dns_drift_notified_at',
        'dns_unverified_warning_sent_at',
        'brand_logo_light_url', 'brand_logo_dark_url', 'brand_icon_url',
        'relationship_blurb',
    ];

    protected static function booted(): void
    {
        // Any time an admin adds, renames, toggles or removes a global
        // domain, drop the marketing showcase cache so the home section and
        // /domains page reflect the change on the next request.
        static::saved(fn () => static::flushShowcaseCache());
        static::deleted(fn () => static::flushShowcaseCache());
    }

    /** Forget the cached marketing showcase list. Never breaks the write path. */
    public static function flushShowcaseCache(): void
    {
        try {
            Cache::forget(self::SHOWCASE_CACHE_KEY);
            Cache::forget(self::PLATFORM_IDS_CACHE_KEY);
        } catch (\Throwable $e) {
            // Cache flushing must never break the write path.
        }
    }

    /**
     * Ids of every admin-global (platform-owned) domain — i.e. rows with no
     * owning user, such as sayzio.app and 1in.me. Used by alias resolution so
     * that a request on any platform host resolves links bound to *any* of
     * the platform's global domains (plus the legacy domain_id IS NULL links),
     * without leaking user-owned custom-domain links across hosts.
     *
     * Cached briefly and flushed on any domain write; falls back to a direct
     * query so resolution never breaks if the cache store is unavailable.
     *
     * @return array<int,int>
     */
    public static function platformDomainIds(): array
    {
        $query = fn () => static::query()->whereNull('user_id')->pluck('id')->all();
        try {
            return Cache::remember(self::PLATFORM_IDS_CACHE_KEY, 600, $query);
        } catch (\Throwable $e) {
            return $query();
        }
    }

    /**
     * Up to $limit active, verified admin-global domains (no owning user),
     * for display on marketing surfaces. Primary first, then alphabetical.
     * Falls back to a static branded list when none are configured (or on
     * any DB error) so the marketing copy is never empty.
     *
     * @return array<int,string>
     */
    public static function showcase(int $limit = 4): array
    {
        try {
            $domains = Cache::remember(self::SHOWCASE_CACHE_KEY, 600, function () use ($limit) {
                return static::query()
                    ->whereNull('user_id')
                    ->where('is_active', true)
                    ->where('is_verified', true)
                    ->orderByDesc('is_primary')
                    ->orderBy('domain')
                    ->limit($limit)
                    ->pluck('domain')
                    ->all();
            });
        } catch (\Throwable $e) {
            return self::SHOWCASE_FALLBACK;
        }

        return !empty($domains) ? $domains : self::SHOWCASE_FALLBACK;
    }

    /** True when this global domain has at least one custom logo uploaded. */
    public function hasCustomBranding(): bool
    {
        return !empty($this->brand_logo_light_url)
            || !empty($this->brand_logo_dark_url)
            || !empty($this->brand_icon_url);
    }

    public const DNS_STATUS_HEALTHY    = 'healthy';
    public const DNS_STATUS_DRIFTING   = 'drifting';
    public const DNS_STATUS_UNVERIFIED = 'unverified';

    protected function casts(): array
    {
        return [
            'is_verified'                    => 'boolean',
            'is_active'                      => 'boolean',
            'is_primary'                     => 'boolean',
            'verified_at'                    => 'datetime',
            'dns_last_checked_at'            => 'datetime',
            'dns_drift_started_at'           => 'datetime',
            'dns_drift_notified_at'          => 'datetime',
            'dns_unverified_warning_sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    /**
     * Plans tagged for an admin-global domain. Ignored for user-owned domains
     * (those follow user ownership rather than plan tagging).
     */
    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'domain_plan');
    }

    /**
     * Account badges tagged for an admin-global domain. Mirrors plans(): a
     * global domain tagged with badges is offered to accounts holding any of
     * those badges. Ignored for user-owned domains.
     */
    public function badges()
    {
        return $this->belongsToMany(
            \App\Modules\Admin\Models\AccountBadge::class,
            'account_badge_domain',
            'domain_id',
            'account_badge_id'
        );
    }

    /** True when this is an admin-managed domain (no owning user). */
    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Mark this global domain as the platform-wide primary, clearing the
     * flag on every other global domain so exactly one is primary at a
     * time. Only a global domain (no owning user) can ever be primary.
     */
    public function makePrimary(): void
    {
        if (!$this->isGlobal()) {
            throw new \InvalidArgumentException('Only global domains can be marked primary.');
        }

        DB::transaction(function () {
            static::query()
                ->whereNull('user_id')
                ->where('id', '!=', $this->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $this->forceFill(['is_primary' => true])->save();
        });
    }

    /**
     * The admin-chosen primary global domain, if one is set. Used to
     * pre-select the default host in user create/edit flows.
     */
    public static function primary(): ?self
    {
        return static::query()
            ->whereNull('user_id')
            ->where('is_primary', true)
            ->first();
    }

    /**
     * Resolve the entitlement context for domain availability.
     *
     * Domains are entitled against the *workspace owner*, not the acting
     * member: when a team member is working inside their owner's workspace
     * (bound by SetActiveWorkspace as `workspace_owner`), they get the
     * global domains the OWNER's plan + badges unlock, plus that
     * WORKSPACE's custom domains. For a solo/personal workspace the owner
     * is the user themselves, so behaviour is unchanged.
     *
     * Falls back to the passed user when no workspace is bound (e.g. the
     * stateless Sanctum API, CLI), preserving the original solo semantics.
     *
     * @return array{workspace: ?\App\Modules\User\Models\Workspace, owner: User, planId: ?int, hasCustomDomains: bool, badgeIds: array<int,int>}
     */
    protected static function entitlementContext(User $user): array
    {
        $workspace = app()->bound('current_workspace') ? app('current_workspace') : null;
        $owner     = app()->bound('workspace_owner') ? app('workspace_owner') : $user;

        return [
            'workspace'        => $workspace,
            'owner'            => $owner,
            'planId'           => $owner->plan_id,
            'hasCustomDomains' => !empty($owner->plan?->features['custom_domains']),
            'badgeIds'         => $owner->accountBadges()->pluck('account_badges.id')->all(),
        ];
    }

    /**
     * Constrain a query to admin-global (no owning user) domains the holder
     * of $planId / $badgeIds is entitled to. Gating combines across tag
     * types with OR: an untagged domain (no plan AND no badge tags) is open
     * to everyone; a tagged one matches when ANY plan tag OR ANY badge tag
     * matches. Always limited to verified rows.
     *
     * @param  array<int,int>  $badgeIds
     */
    protected static function scopeGlobalEntitled($query, ?int $planId, array $badgeIds): void
    {
        $query->whereNull('user_id')
            ->where('is_verified', true)
            ->where(function ($g) use ($planId, $badgeIds) {
                // Untagged → open to everyone.
                $g->where(function ($open) {
                    $open->whereDoesntHave('plans')->whereDoesntHave('badges');
                });
                // OR matches any tagged plan.
                if ($planId) {
                    $g->orWhereHas('plans', fn ($pp) => $pp->where('plans.id', $planId));
                }
                // OR matches any tagged badge.
                if (!empty($badgeIds)) {
                    $g->orWhereHas('badges', fn ($bb) => $bb->whereIn('account_badges.id', $badgeIds));
                }
            });
    }

    /**
     * Admin-global active domains the given user (resolved through their
     * active workspace owner) is entitled to use. Excludes any user-owned
     * domains. Team-aware + badge-aware.
     */
    public static function globalAvailableTo(User $user)
    {
        $ctx = static::entitlementContext($user);

        return static::query()
            ->withoutGlobalScope('workspace')
            ->where('is_active', true)
            ->where(function ($g) use ($ctx) {
                static::scopeGlobalEntitled($g, $ctx['planId'], $ctx['badgeIds']);
            })
            ->orderBy('domain');
    }

    /**
     * Domains the given user can attach links to: the active workspace's
     * verified custom domains plus admin-global active domains the workspace
     * owner's plan + badges unlock (or untagged ones, open to everyone).
     *
     * Team-aware: when a team workspace is active, a member sees the
     * WORKSPACE's custom domains and the OWNER's entitled global domains.
     * A downgrade hides custom domains (the rows are kept, so an upgrade
     * restores them) but never deletes them.
     */
    public static function availableTo(User $user)
    {
        $ctx = static::entitlementContext($user);

        return static::query()
            // Global domains live with a NULL workspace_id, so the workspace
            // global scope would hide them; custom domains are scoped
            // explicitly below instead.
            ->withoutGlobalScope('workspace')
            ->where('is_active', true)
            ->where(function ($q) use ($ctx) {
                // Workspace-owned verified custom domains — only while the
                // owner's plan still includes the `custom_domains` feature.
                if ($ctx['hasCustomDomains']) {
                    $q->orWhere(function ($own) use ($ctx) {
                        $own->whereNotNull('user_id')->where('is_verified', true);
                        if ($ctx['workspace']) {
                            $own->where('workspace_id', $ctx['workspace']->id);
                        } else {
                            $own->where('user_id', $ctx['owner']->id);
                        }
                    });
                }
                // Admin-global entitled domains (plan OR badge, untagged open).
                $q->orWhere(function ($global) use ($ctx) {
                    static::scopeGlobalEntitled($global, $ctx['planId'], $ctx['badgeIds']);
                });
            })
            ->orderBy('domain');
    }
}
