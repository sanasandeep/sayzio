<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Plan;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'mobile', 'password', 'phone', 'avatar', 'status', 'role',
        'plan_id', 'billing_cycle', 'plan_expires_at', 'trial_ends_at',
        'timezone', 'language', 'persona', 'onboarded_at', 'settings', 'email_verified_at', 'last_login_at',
        'bio', 'handle', 'discoverable', 'notify_new_follower', 'notify_follower_updates',
        'follower_updates_mode', 'follower_digest_last_sent_at',
        'digest_preferred_hour',
        'last_backlink_digest_sent_at',
        'backlink_digest_preferred_weekday',
        'backlink_digest_preferred_hour',
        'followers_count', 'allow_followers',
        'referral_code', 'referrer_id', 'referral_code_used',
        'social_connection_broken_emails',
        'country',
        'preferred_currency',
        'is_demo',
        'blocked_bot_families',
        'image_reoptimize_files_count',
        'image_reoptimize_bytes_freed',
        'image_reoptimize_notice_dismissed_at',
        // Creator Profile (separate /@handle surface — see Task #1207).
        'cover_image', 'tagline', 'location', 'niche_tags', 'socials',
        'profile_published', 'profile_section_visibility', 'posts_count',
        // Creator payouts + NSFW consent (Task #1208).
        'adult_content_enabled', 'adult_content_enabled_at',
        'age_verified_at',
        'adult_flag_suspended_at', 'adult_flag_suspended_reason', 'adult_flag_suspended_by',
        // Paid DMs (Task #1210).
        'dm_access_mode', 'dm_pay_price_cents', 'dm_pay_currency',
        'dm_min_tier_id', 'dm_read_receipts_enabled',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'last_login_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
            'discoverable' => 'boolean',
            'notify_new_follower' => 'boolean',
            'notify_follower_updates' => 'boolean',
            'follower_digest_last_sent_at' => 'datetime',
            'last_backlink_digest_sent_at' => 'datetime',
            'digest_preferred_hour' => 'integer',
            'backlink_digest_preferred_weekday' => 'integer',
            'backlink_digest_preferred_hour' => 'integer',
            'social_connection_broken_emails' => 'boolean',
            'last_handle_ban_email_sent_at' => 'datetime',
            'blocked_bot_families' => 'array',
            'image_reoptimize_files_count' => 'integer',
            'image_reoptimize_bytes_freed' => 'integer',
            'image_reoptimize_notice_dismissed_at' => 'datetime',
            // Creator Profile fields (Task #1207).
            'niche_tags' => 'array',
            'socials' => 'array',
            'profile_section_visibility' => 'array',
            'profile_published' => 'boolean',
            'posts_count' => 'integer',
            // Creator payouts + NSFW consent (Task #1208).
            'adult_content_enabled'        => 'boolean',
            'adult_content_enabled_at'     => 'datetime',
            'age_verified_at'              => 'datetime',
            'adult_flag_suspended_at'      => 'datetime',
            // Paid DMs (Task #1210).
            'dm_pay_price_cents'           => 'integer',
            'dm_min_tier_id'               => 'integer',
            'dm_read_receipts_enabled'     => 'boolean',
        ];
    }

    /** Allowed DM access modes — see Task #1210. */
    public const DM_MODE_OPEN   = 'open';
    public const DM_MODE_SUBS   = 'subs';
    public const DM_MODE_PAID   = 'paid';
    public const DM_MODE_CLOSED = 'closed';

    public const DM_MODES = [
        self::DM_MODE_OPEN   => 'Open — anyone signed in can DM me',
        self::DM_MODE_SUBS   => 'Subscribers only',
        self::DM_MODE_PAID   => 'Pay to message — first message costs a fee',
        self::DM_MODE_CLOSED => 'Closed — DMs disabled',
    ];

    /**
     * Convenience: per the policy, a creator is publicly tagged 18+
     * when they opted in AND a moderator hasn't suspended the flag.
     * Used by visitor surfaces (age gate, /creators directory filter).
     */
    public function isAdultProfile(): bool
    {
        return (bool) $this->adult_content_enabled
            && empty($this->adult_flag_suspended_at);
    }

    public function paymentConnections()
    {
        return $this->hasMany(\App\Modules\User\Models\CreatorPaymentConnection::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function defaultPaymentConnection(): ?\App\Modules\User\Models\CreatorPaymentConnection
    {
        return $this->paymentConnections()->where('is_default', true)->first();
    }

    /**
     * Default visibility map for the fixed-layout Creator Profile sections.
     * Hero is always shown — toggling it would leave a blank page — so it
     * is intentionally absent from the editor.
     */
    public const PROFILE_DEFAULT_VISIBILITY = [
        'stats'   => true,
        'about'   => true,
        'posts'   => true,
        'socials' => true,
        'biolink' => true,
        'contact' => true,
    ];

    public function profileSectionVisibility(): array
    {
        $stored = is_array($this->profile_section_visibility) ? $this->profile_section_visibility : [];
        return array_merge(self::PROFILE_DEFAULT_VISIBILITY, $stored);
    }

    public function isSectionVisible(string $section): bool
    {
        $vis = $this->profileSectionVisibility();
        return (bool) ($vis[$section] ?? true);
    }

    /**
     * 0–100 score telling the creator how filled-in their profile is.
     * Drives the completeness meter shown in the editor.
     */
    public function profileCompletenessPercent(): int
    {
        $checks = [
            !empty($this->handle),
            !empty($this->avatar),
            !empty($this->cover_image),
            !empty($this->tagline),
            !empty($this->bio),
            !empty($this->location),
            is_array($this->niche_tags) && count($this->niche_tags) > 0,
            is_array($this->socials) && count($this->socials) > 0,
            (int) ($this->posts_count ?? 0) > 0,
            (bool) $this->profile_published,
        ];
        $done = count(array_filter($checks));
        return (int) round(($done / count($checks)) * 100);
    }

    /**
     * On user creation, mirror the email/mobile columns into the
     * linked_identifiers table so every account has at least one
     * verified primary identifier from day one. Both new sign-ups and
     * tests benefit from this — the backfill migration only covers
     * accounts that existed before the feature shipped.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            $primaryAssigned = false;
            if (!empty($user->email)) {
                LinkedIdentifier::firstOrCreate(
                    ['kind' => 'email', 'value' => LinkedIdentifier::normalize('email', $user->email)],
                    [
                        'user_id'     => $user->id,
                        'verified_at' => $user->email_verified_at ?: now(),
                        'is_primary'  => true,
                    ]
                );
                $primaryAssigned = true;
            }
            if (!empty($user->mobile)) {
                LinkedIdentifier::firstOrCreate(
                    ['kind' => 'phone', 'value' => LinkedIdentifier::normalize('phone', $user->mobile)],
                    [
                        'user_id'     => $user->id,
                        'verified_at' => now(),
                        'is_primary'  => !$primaryAssigned,
                    ]
                );
            }
            \App\Modules\User\Services\PersonalTaskBoardProvisioner::ensureFor($user);
            // Make sure the platform-managed "1INME Default Mind"
            // exists so the new account immediately has access to a
            // Mind with product knowledge in it. Per-user "My Mind" is
            // created lazily the first time they open the dashboard.
            try {
                \App\Services\AI\AiMindProvisioner::ensurePlatformDefault();
            } catch (\Throwable $e) {
                // Don't ever break account creation on AI provisioning.
            }
        });
    }

    public function followers()    { return $this->hasMany(Follow::class, 'creator_id'); }
    public function following()    { return $this->hasMany(Follow::class, 'follower_id'); }
    public function posts()        { return $this->hasMany(CreatorPost::class)->latest(); }
    public function publishedPosts() { return $this->hasMany(CreatorPost::class)->whereNotNull('published_at')->latest('published_at'); }
    public function pinnedPost()    { return $this->hasOne(CreatorPost::class)->whereNotNull('pinned_at')->whereNotNull('published_at')->latest('pinned_at'); }
    public function notifications() { return $this->hasMany(UserNotification::class)->latest('created_at'); }

    public function wallet() { return $this->hasOne(Wallet::class); }
    public function walletTransactions() { return $this->hasMany(WalletTransaction::class)->orderByDesc('id'); }

    public function linkedIdentifiers()
    {
        return $this->hasMany(LinkedIdentifier::class)->orderByDesc('is_primary')->orderBy('kind')->orderBy('id');
    }

    /** All verified identifiers (any kind) currently attached to this account. */
    public function verifiedIdentifiers()
    {
        return $this->hasMany(LinkedIdentifier::class)->whereNotNull('verified_at');
    }

    public function primaryIdentifier(): ?LinkedIdentifier
    {
        return $this->linkedIdentifiers()->where('is_primary', true)->first();
    }

    public function isFollowing(int $creatorId): bool
    {
        return Follow::where('follower_id', $this->id)->where('creator_id', $creatorId)->exists();
    }

    public function getInitials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name ?? '?'));
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        return mb_strtoupper($a . $b) ?: '?';
    }

    public function publicHandle(): string
    {
        return $this->handle ?: ('user' . $this->id);
    }

    public function primaryBiolink(): ?Link
    {
        return Link::where('user_id', $this->id)
            ->where('type', 'biolink')
            ->where('is_active', true)
            ->orderByDesc('total_clicks')
            ->first();
    }

    /**
     * Returns the creator's default biolink only if it currently has an
     * active Direct Message block AND, when a viewer is supplied, the
     * viewer is not account-blocked by this creator. Returns null when
     * the creator cannot be messaged from outside one of their biolink
     * pages — used to decide whether to surface a "Message" button on
     * the public Creators directory and to route the resulting chat.
     */
    public function messageableBiolink(?User $viewer = null): ?Link
    {
        $bio = $this->primaryBiolink();
        if (!$bio) return null;

        $hasDm = BiolinkBlock::where('link_id', $bio->id)
            ->where('type', 'direct_message')
            ->where('is_active', true)
            ->exists();
        if (!$hasDm) return null;

        if ($viewer) {
            $blocked = \App\Modules\Common\Models\ViewerDmUserBlock::where('owner_user_id', $this->id)
                ->where('viewer_user_id', $viewer->id)
                ->exists();
            if ($blocked) return null;
        }

        return $bio;
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /** Workspaces this user owns (one-to-many — owner side). */
    public function ownedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_user_id');
    }

    /** Memberships this user has on other people's workspaces. */
    public function workspaceMemberships()
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * Every workspace this user can access — owned + member-of, sorted with
     * owned ones first. Used by the workspace switcher.
     *
     * @return \Illuminate\Support\Collection<\App\Modules\User\Models\Workspace>
     */
    public function accessibleWorkspaces()
    {
        $owned = $this->ownedWorkspaces()->get();
        $memberWsIds = $this->workspaceMemberships()->pluck('workspace_id');
        $joined = $memberWsIds->isEmpty()
            ? collect()
            : Workspace::whereIn('id', $memberWsIds)->get();
        return $owned->merge($joined)->sortBy([['owner_user_id', 'asc'], ['id', 'asc']])->values();
    }

    /** True if this user is the owner OR an active member of $workspace. */
    public function belongsToWorkspace(Workspace $workspace): bool
    {
        if ($this->id === (int) $workspace->owner_user_id) return true;
        return $this->workspaceMemberships()->where('workspace_id', $workspace->id)->exists();
    }

    /** Membership row for this user on $workspace, if any. */
    public function membershipFor(Workspace $workspace): ?WorkspaceMember
    {
        return $this->workspaceMemberships()->where('workspace_id', $workspace->id)->first();
    }

    /**
     * Check a permission against the given workspace. Owner of the workspace
     * (and super-admin) always pass; members consult their permission blob.
     */
    public function canInWorkspace(Workspace $workspace, string $permission): bool
    {
        if ($this->isSuperAdmin()) return true;
        if ((int) $workspace->owner_user_id === $this->id) return true;
        $membership = $this->membershipFor($workspace);
        if (!$membership) return false;
        return $membership->can($permission);
    }

    /**
     * Lazily ensure this user owns at least one workspace, returning their
     * personal one. Auto-creation marks the new row as `is_personal=true`
     * so it can never be deleted from the UI (every user keeps a personal
     * space; team workspaces are added on top via WorkspaceController::store).
     */
    public function ensureDefaultWorkspace(): Workspace
    {
        // Wrap in a transaction with a row-level lock on the user so two
        // concurrent registration / login flows cannot race to create
        // duplicate personal workspaces. The DB also has a partial unique
        // index (workspaces_one_personal_per_owner_unique) as a hard
        // backstop, but the lock keeps the happy path single-creator.
        return \DB::transaction(function () {
            \DB::table('users')->where('id', $this->id)->lockForUpdate()->first();

            $personal = $this->ownedWorkspaces()->where('is_personal', true)->first();
            if ($personal) return $personal;

            $existing = $this->ownedWorkspaces()->orderBy('id')->first();
            if ($existing) {
                $existing->update(['is_personal' => true]);
                return $existing->fresh();
            }

            return $this->ownedWorkspaces()->create([
                'name'        => ($this->name ?: ('User ' . $this->id)) . "'s workspace",
                'slug'        => 'ws-' . $this->id,
                'is_personal' => true,
            ]);
        });
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Single-resume accessor kept for back-compat with callers that
     * predate the multi-version era. Resolves to the user's default
     * version (the row marked is_default=true), or — failing that — the
     * oldest row so we never silently dereference null.
     */
    public function resume()
    {
        return $this->hasOne(Resume::class)
            ->orderByDesc('is_default')
            ->orderBy('id');
    }

    /** Hasmany over all of the user's named resume versions. */
    public function resumes()
    {
        return $this->hasMany(Resume::class)
            ->orderByDesc('is_default')
            ->orderBy('id');
    }

    /**
     * Lazily fetch (or create) the user's *default* Resume row. A user
     * has no resume rows until they open the editor; at that point we
     * create the first row and mark it default so it powers the public
     * /{handle}/resume URL.
     */
    public function ensureResume(): Resume
    {
        $resume = $this->resumes()->where('is_default', true)->first()
              ?? $this->resumes()->first();
        if ($resume) {
            // Heal pre-versioning rows that never received a default
            // flag — promote the oldest to default so /{handle}/resume
            // keeps resolving even after a partial backfill.
            if (!$resume->is_default) {
                $resume->forceFill([
                    'is_default' => true,
                    'name'       => $resume->name ?: 'Default',
                    'slug'       => $resume->slug ?: Resume::DEFAULT_SLUG,
                ])->save();
            }
            return $resume;
        }

        return $this->resumes()->create([
            'template_id'    => \App\Modules\User\Services\ResumeTemplateRegistry::defaultId(),
            'color_theme_id' => \App\Modules\User\Services\ResumeColorThemeRegistry::defaultId(),
            'sections'       => Resume::defaultSections(),
            'name'           => 'Default',
            'slug'           => Resume::DEFAULT_SLUG,
            'is_default'     => true,
        ]);
    }

    /**
     * Resolve which version the current request is targeting. Defaults
     * to the user's default version; can be overridden by the request
     * via `?resume_id=N` (or body / header). Foreign ids are rejected
     * with 403 so the editor can't reach across users.
     */
    public function resolveResume(\Illuminate\Http\Request $request): Resume
    {
        $default = $this->ensureResume();
        $rid = $request->input('resume_id', $request->query('resume_id'));
        if (!$rid && $request->hasHeader('X-Resume-Id')) {
            $rid = $request->header('X-Resume-Id');
        }
        $rid = is_numeric($rid) ? (int) $rid : null;
        if (!$rid || $rid === (int) $default->id) return $default;

        $resume = $this->resumes()->whereKey($rid)->first();
        abort_if(!$resume, 403);
        return $resume;
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    public function splashPages()
    {
        return $this->hasMany(SplashPage::class);
    }

    public function forms()
    {
        return $this->hasMany(Form::class);
    }

    public function pixels()
    {
        return $this->hasMany(Pixel::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function files()
    {
        return $this->hasMany(UserFile::class);
    }

    public function customFonts()
    {
        return $this->hasMany(CustomFont::class);
    }

    public function getStorageUsedBytes(): int
    {
        return (int) $this->files()->sum('size_bytes');
    }

    public function getStorageLimitBytes(): int
    {
        // Super admins have unlimited storage, regardless of plan.
        if ($this->isSuperAdmin()) {
            return PHP_INT_MAX;
        }
        $mb = (int) $this->getPlanFeature('storage_limit_mb', 100);
        return $mb * 1048576;
    }

    public function getStorageRemainingBytes(): int
    {
        return max(0, $this->getStorageLimitBytes() - $this->getStorageUsedBytes());
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasActivePlan(): bool
    {
        if ($this->isOnTrial()) return true;
        if (!$this->plan_expires_at) return false;
        return $this->plan_expires_at->isFuture();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isOnFreePlan(): bool
    {
        return !$this->plan_id || ($this->plan && $this->plan->slug === 'free');
    }

    public function getPlanFeature(string $key, $default = null)
    {
        // Super admins bypass ALL plan gating regardless of what any plan
        // record stores: numeric limits become effectively unlimited and
        // boolean feature flags become enabled. Non-scalar defaults (e.g.
        // arrays for upload_limits) fall through to the explicit plan
        // value if set, otherwise the default — so per-context overrides
        // can still be applied to admins if explicitly configured.
        if ($this->isSuperAdmin()) {
            if (is_int($default) || is_float($default) || $default === null) {
                return PHP_INT_MAX;
            }
            if (is_bool($default)) {
                return true;
            }
            // For non-scalar defaults fall through to plan-explicit value.
        }

        if (!$this->plan || !is_array($this->plan->features)) {
            return $default;
        }
        return $this->plan->features[$key] ?? $default;
    }

    /**
     * Boolean feature flag check that respects super-admin bypass.
     */
    public function planFeatureEnabled(string $key): bool
    {
        return (bool) $this->getPlanFeature($key, false);
    }

    /**
     * True when the current count is below (or at) the plan's max for $key.
     * -1 means unlimited; super_admin bypass already returns PHP_INT_MAX.
     */
    public function planUnderLimit(string $key, int $currentCount, int $default = 0): bool
    {
        $max = (int) $this->getPlanFeature($key, $default);
        if ($max === -1) return true;
        if ($this->isSuperAdmin()) return true;
        return $currentCount < $max;
    }

    /**
     * True when the user's plan permits a given biolink block type.
     * `block_types_allowed` may be `'*'` (all), or an array of block-type
     * slugs. Empty / missing entry means "all allowed" (legacy behavior).
     */
    public function userCanUseBlockType(string $slug): bool
    {
        if ($this->isSuperAdmin()) return true;
        $allowed = $this->getPlanFeature('block_types_allowed', '*');
        if ($allowed === '*' || $allowed === null || $allowed === '') return true;
        if (!is_array($allowed)) return true;
        return in_array($slug, $allowed, true);
    }

    /**
     * True when the user's plan permits a given per-link advanced setting.
     * Mapping is centralized so controllers/middleware stay consistent.
     */
    public function userCanUseLinkSetting(string $setting): bool
    {
        if ($this->isSuperAdmin()) return true;
        $key = match ($setting) {
            'password'         => 'link_password',
            'expiry'           => 'link_expiry',
            'geo_targeting'    => 'link_geo_targeting',
            'device_targeting' => 'link_device_targeting',
            'deep_link'        => 'link_deep_link',
            'smart_rules'      => 'link_smart_rules',
            'active_window'    => 'link_active_window',
            default            => null,
        };
        if ($key === null) return true;
        return (bool) $this->getPlanFeature($key, false);
    }

    /**
     * Returns the cheapest active plan that unlocks $key, or null when no
     * plan unlocks it. Used by the lock-banner partial and middleware to
     * surface a concrete "Upgrade to <plan>" message instead of a generic
     * one.  Boolean keys: any truthy value qualifies.  Numeric "max_*"
     * keys: the plan must allow strictly more than the user's current
     * effective cap (a -1 / "unlimited" plan always qualifies).
     */
    public function planThatUnlocks(string $key, $current = null)
    {
        $plans = \App\Modules\Admin\Models\Plan::where('status', 'active')
            ->orderBy('monthly_price')->get();
        $isNumeric = str_starts_with($key, 'max_') || $key === 'storage_limit_mb' || $key === 'contacts_max';
        // Compare against the user's CURRENT PLAN CAP (not the usage count)
        // so the suggested plan is the one that meaningfully raises the
        // limit. If the caller passed $current and it's higher than the cap
        // (i.e. they've blown past it), use that as the floor instead.
        $currentCap = (int) $this->getPlanFeature($key, 0);
        $floor = is_numeric($current ?? null) ? max($currentCap, (int) $current) : $currentCap;
        foreach ($plans as $p) {
            $val = ($p->features[$key] ?? null);
            if ($isNumeric) {
                $vNum = (int) $val;
                if ($vNum === -1) return $p;
                if ($vNum > $floor) return $p;
            } else {
                if (!empty($val)) return $p;
            }
        }
        return null;
    }

    /**
     * Min/max custom-alias length permitted for this user's plan. Admins
     * configure these per plan; sane defaults apply for users on the free /
     * unconfigured tier so link creation always works.
     *
     * @return array{min:int,max:int}
     */
    public function getAliasLengthLimits(): array
    {
        // Super admins (and users on plans without explicit alias limits) get
        // no constraint — sentinel "PHP_INT_MAX" returned by getPlanFeature
        // for super admins must NOT become a literal min length.
        if ($this->isSuperAdmin()) {
            return ['min' => 1, 'max' => 191];
        }
        $min = (int) $this->getPlanFeature('min_alias_length', 3);
        $max = (int) $this->getPlanFeature('max_alias_length', 50);
        if ($min < 1)    $min = 1;
        if ($max < 1)    $max = 1;
        if ($max > 191)  $max = 191; // matches DB column width
        if ($min > $max) $min = $max;
        return ['min' => $min, 'max' => $max];
    }

    /**
     * Maximum number of additional aliases per biolink for this user.
     * The primary alias does NOT count toward the limit (it's free with the link).
     * `-1` means unlimited.
     */
    public function getMaxAliasesPerLink(): int
    {
        return (int) $this->getPlanFeature('max_aliases_per_link', 0);
    }
}
