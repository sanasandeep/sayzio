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
        'followers_count', 'allow_followers',
        'referral_code', 'referrer_id', 'referral_code_used',
        'social_connection_broken_emails',
        'country',
        'is_demo',
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
            'digest_preferred_hour' => 'integer',
            'social_connection_broken_emails' => 'boolean',
            'last_handle_ban_email_sent_at' => 'datetime',
        ];
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
            // Auto-provision a "My Tasks" personal kanban board for the new
            // user on their personal workspace. Wrapped in try/catch so a
            // failure here can never block account creation.
            try {
                \App\Modules\User\Services\PersonalTaskBoardProvisioner::ensureFor($user);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function followers()    { return $this->hasMany(Follow::class, 'creator_id'); }
    public function following()    { return $this->hasMany(Follow::class, 'follower_id'); }
    public function posts()        { return $this->hasMany(CreatorPost::class)->latest(); }
    public function publishedPosts() { return $this->hasMany(CreatorPost::class)->whereNotNull('published_at')->latest('published_at'); }
    public function pinnedPost()    { return $this->hasOne(CreatorPost::class)->whereNotNull('pinned_at')->whereNotNull('published_at')->latest('pinned_at'); }
    public function notifications() { return $this->hasMany(UserNotification::class)->latest('created_at'); }

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
