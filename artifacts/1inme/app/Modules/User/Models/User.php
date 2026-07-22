<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Plan;
use Database\Factories\UserDatabaseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Point the standard {@see HasFactory::factory()} entry point at the
     * factory in {@see \Database\Factories\UserDatabaseFactory}. This is
     * required because Laravel's default factory-name resolver derives
     * `Database\Factories\Modules\User\Models\UserFactory` from this model's
     * module namespace — which does not exist. Without this override,
     * `User::factory()` throws BadMethodCallException in test setUp before a
     * single assertion runs, silently erroring every test that reaches for
     * the idiomatic factory helper.
     */
    protected static function newFactory(): Factory
    {
        return UserDatabaseFactory::new();
    }

    /** Per-instance memo of plan features merged with active subscription addons. */
    protected ?array $effectivePlanFeaturesCache = null;

    protected $fillable = [
        'name', 'email', 'mobile', 'password', 'phone', 'avatar', 'creator_avatar', 'status',
        'plan_id', 'billing_cycle', 'plan_expires_at', 'trial_ends_at',
        'timezone', 'language', 'persona', 'onboarded_at', 'settings', 'email_verified_at', 'last_login_at',
        'bio', 'handle', 'discoverable', 'notify_new_follower', 'notify_follower_updates',
        'follower_updates_mode', 'follower_digest_last_sent_at',
        'digest_preferred_hour',
        'last_backlink_digest_sent_at',
        'backlink_digest_preferred_weekday',
        'backlink_digest_preferred_hour',
        'email_verification_reminders_sent',
        'email_verification_reminder_sent_at',
        // Starter (free) plan 1-year free window + yearly re-confirmation.
        'starter_free_window_ends_at',
        'starter_renewal_reminder_sent_at',
        'api_usage_warning_threshold',
        'followers_count', 'allow_followers',
        'referral_code', 'referrer_id', 'referral_code_used',
        'social_connection_broken_emails',
        'country',
        'preferred_currency',
        'is_demo',
        'is_readonly_demo',
        'blocked_bot_families',
        'image_reoptimize_files_count',
        'image_reoptimize_bytes_freed',
        'image_reoptimize_notice_dismissed_at',
        // Creator Profile (separate /@handle surface — see Task #1207).
        'cover_image', 'tagline', 'location', 'niche_tags', 'socials',
        'profile_published', 'profile_section_visibility', 'profile_showcase', 'posts_count',
        'profile_theme_color',
        // Creator payouts + NSFW consent (Task #1208).
        'adult_content_enabled', 'adult_content_enabled_at',
        'age_verified_at',
        // Location-based new-event alerts (Task #3593).
        'event_alerts_enabled', 'event_alert_latitude', 'event_alert_longitude',
        'event_alert_radius_km', 'event_alert_frequency',
        'adult_flag_suspended_at', 'adult_flag_suspended_reason', 'adult_flag_suspended_by',
        // Paid DMs (Task #1210).
        'dm_access_mode', 'dm_pay_price_cents', 'dm_pay_currency',
        'dm_min_tier_id', 'dm_read_receipts_enabled',
        // Creator safety & discovery (Task #1211).
        'mute_words', 'watermark_settings',
        'country_block_list', 'country_allow_list',
        'dmca_email', 'creator_digest_last_sent_at',
        // Default Calendar account that "Keep in sync" event invites push to (Task #1233).
        'auto_sync_calendar_account_id',
        // Admin/staff user-management suite (Task #2106): temporary
        // hold/suspend + comp/time-limited plan window.
        'suspended_at', 'suspension_reason', 'suspended_by', 'reactivate_at',
        'comp_plan_expires_at', 'comp_plan_granted_by',
        // Reusable event organizer profile (Task #3699).
        'organizer_profile',
        // Account-level creator profile verification (Task #5439).
        'profile_verification_status', 'profile_verification_type_id',
        'profile_verified_name', 'profile_verified_avatar', 'profile_verified_at',
    ];

    protected $hidden = ['password', 'remember_token', 'my_calendar_feed_token'];

    /**
     * Return this user's long-lived "My Calendar" ICS subscription token,
     * minting one on first access. This token authenticates the session-less
     * feed URL that external calendar apps (Google / Apple / Outlook) poll,
     * so it must be unguessable and stable until deliberately rotated.
     */
    public function myCalendarFeedToken(): string
    {
        if (blank($this->my_calendar_feed_token)) {
            $this->regenerateMyCalendarFeedToken();
        }

        return (string) $this->my_calendar_feed_token;
    }

    /**
     * Rotate the "My Calendar" feed token, invalidating any previously shared
     * feed URL. Retries on the (astronomically unlikely) unique collision.
     */
    public function regenerateMyCalendarFeedToken(): string
    {
        do {
            $token = \Illuminate\Support\Str::random(48);
        } while (static::where('my_calendar_feed_token', $token)->exists());

        $this->forceFill(['my_calendar_feed_token' => $token])->save();

        return $token;
    }

    /**
     * New accounts default to the platform timezone (IST) until the user
     * explicitly picks their own (Task #3480).
     */
    protected $attributes = [
        'timezone' => \App\Support\PlatformTimezone::DEFAULT,
    ];

    /** Effective timezone: the user's chosen zone, else the platform default (IST). */
    public function effectiveTimezone(): string
    {
        return \App\Support\PlatformTimezone::forUser($this);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'starter_free_window_ends_at' => 'datetime',
            'starter_renewal_reminder_sent_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'last_login_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
            'discoverable' => 'boolean',
            'is_readonly_demo' => 'boolean',
            'notify_new_follower' => 'boolean',
            'notify_follower_updates' => 'boolean',
            'follower_digest_last_sent_at' => 'datetime',
            'last_backlink_digest_sent_at' => 'datetime',
            'digest_preferred_hour' => 'integer',
            'backlink_digest_preferred_weekday' => 'integer',
            'backlink_digest_preferred_hour' => 'integer',
            'email_verification_reminders_sent' => 'integer',
            'email_verification_reminder_sent_at' => 'datetime',
            'api_usage_warning_threshold' => 'integer',
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
            'profile_showcase' => 'array',
            'profile_published' => 'boolean',
            'posts_count' => 'integer',
            'profile_theme_color' => 'string',
            // Creator payouts + NSFW consent (Task #1208).
            'adult_content_enabled'        => 'boolean',
            'adult_content_enabled_at'     => 'datetime',
            'age_verified_at'              => 'datetime',
            'adult_flag_suspended_at'      => 'datetime',
            // Location-based new-event alerts (Task #3593).
            'event_alerts_enabled'         => 'boolean',
            'event_alert_latitude'         => 'float',
            'event_alert_longitude'        => 'float',
            'event_alert_radius_km'        => 'integer',
            // Paid DMs (Task #1210).
            'dm_pay_price_cents'           => 'integer',
            'dm_min_tier_id'               => 'integer',
            'dm_read_receipts_enabled'     => 'boolean',
            // Creator safety & discovery (Task #1211).
            'mute_words'                    => 'array',
            'watermark_settings'            => 'array',
            'country_block_list'            => 'array',
            'country_allow_list'            => 'array',
            'creator_digest_last_sent_at'   => 'datetime',
            // Account-level creator profile verification (Task #5439).
            'profile_verified_at'            => 'datetime',
            // Admin/staff user-management suite (Task #2106).
            'suspended_at'                  => 'datetime',
            'reactivate_at'                 => 'datetime',
            'comp_plan_expires_at'          => 'datetime',
            'suspended_by'                  => 'integer',
            'comp_plan_granted_by'          => 'integer',
            // Reusable event organizer profile (Task #3699).
            'organizer_profile'             => 'array',
        ];
    }

    /**
     * Field keys that make up the reusable event organizer profile. Set
     * once per account (Creator Profile editor) and shown on all of the
     * creator's events — never a per-event override (explicitly out of
     * scope, see Task #3699).
     */
    public const ORGANIZER_PROFILE_FIELDS = [
        'logo', 'name', 'description', 'website',
        'contact_name', 'contact_phone', 'contact_email', 'address',
    ];

    /**
     * The resolved organizer profile, normalized to always contain every
     * key (blank string when unset) plus a `socials` array and a `filled`
     * flag. Display surfaces (event detail page, /@handle/events) should
     * use `filled` to decide between the rich organizer card and today's
     * simple "Hosted by" fallback, instead of re-deriving emptiness
     * themselves.
     */
    // =========================================================================
    // Account-level creator profile verification (Task #5439)
    // =========================================================================

    /**
     * True when the user holds an active verified tick (not pending reverification
     * from an admin's perspective — the tick is still displayed while pending
     * reverification).
     */
    public function isVerified(): bool
    {
        return in_array($this->profile_verification_status, ['verified', 'pending_reverification'], true);
    }

    /** True when the user is fully verified without any pending change. */
    public function isFullyVerified(): bool
    {
        return $this->profile_verification_status === 'verified';
    }

    /** True when a re-verification review is in flight. */
    public function isPendingReverification(): bool
    {
        return $this->profile_verification_status === 'pending_reverification';
    }

    /** True when the user's profile name/avatar are locked (they are verified). */
    public function isNameAvatarLocked(): bool
    {
        return $this->isVerified();
    }

    /** Relation to the current tick type. */
    public function verificationTickType()
    {
        return $this->belongsTo(VerificationTickType::class, 'profile_verification_type_id');
    }

    /** All profile verification requests for this user. */
    public function profileVerificationRequests()
    {
        return $this->hasMany(ProfileVerificationRequest::class);
    }

    /**
     * Render the colored verification tick badge HTML.
     * Returns empty string when the user is not verified.
     */
    public function verificationTickHtml(string $sizeClass = 'text-sm'): string
    {
        if (!$this->isVerified() || !$this->profile_verification_type_id) {
            return '';
        }
        static $cache = [];
        $id = (int) $this->profile_verification_type_id;
        if (!isset($cache[$id])) {
            $cache[$id] = VerificationTickType::find($id);
        }
        return $cache[$id] ? $cache[$id]->tickHtml($sizeClass) : '';
    }

    public function organizerProfile(): array
    {
        $stored = is_array($this->organizer_profile) ? $this->organizer_profile : [];

        $profile = [];
        foreach (self::ORGANIZER_PROFILE_FIELDS as $key) {
            $value = $stored[$key] ?? null;
            $profile[$key] = is_string($value) ? trim($value) : '';
        }
        $socials = is_array($stored['socials'] ?? null) ? $stored['socials'] : [];
        $profile['socials'] = array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $socials
        ));

        $hasCore = collect($profile)
            ->except('socials')
            ->filter(fn ($v) => $v !== '')
            ->isNotEmpty();
        $profile['filled'] = $hasCore || !empty($profile['socials']);

        return $profile;
    }

    /**
     * Whether the account is on an admin temporary hold. `suspended_at`
     * is the source of truth (orthogonal to the active/inactive/banned
     * `status` column and the separate 18+ adult_flag_* suspension), so
     * login enforcement and the admin UI both branch on this.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
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
        'stats'          => true,
        'about'          => true,
        'posts'          => true,
        'socials'        => true,
        'biolink'        => true,
        'contact'        => true,
        'events'         => true,
        // Showcase additions (Task #5431).
        'featured_links' => true,
        'showcase'       => true,
        'highlights'     => true,
        'cta'            => true,
    ];

    /**
     * Default structure for profile_showcase when no value is stored yet.
     * All showcased items and CTA buttons are opt-in so the array is empty
     * by default; only booleans need a default value.
     *
     * @return array<string,mixed>
     */
    public static function defaultProfileShowcase(): array
    {
        return [
            'featured_links'       => [],
            'featured_links_style' => 'classic',
            'show_link_stats'      => false,
            'showcase_items'       => [],
            'highlights' => [
                'show_followers'   => true,
                'show_links'       => true,
                'show_member_since'=> true,
                'show_verified'    => true,
            ],
            'cta' => [
                'primary'   => null,
                'secondary' => [],
            ],
        ];
    }

    /**
     * Return the resolved showcase config — stored value merged over the
     * defaults so callers never have to null-check every key.
     *
     * Backward compat: older records store featured_link_ids (array of ints).
     * We transparently upgrade those to the richer featured_links format
     * (array of {id, enabled}) on read so all callers use the new shape.
     *
     * @return array<string,mixed>
     */
    public function resolvedProfileShowcase(): array
    {
        $stored   = is_array($this->profile_showcase) ? $this->profile_showcase : [];
        $defaults = self::defaultProfileShowcase();

        // Resolve featured_links: prefer new format; fall back to legacy featured_link_ids.
        if (isset($stored['featured_links']) && is_array($stored['featured_links'])) {
            $featuredLinks = array_values(array_filter(
                array_map(function ($item) {
                    if (!is_array($item) || empty($item['id'])) return null;
                    return ['id' => (int) $item['id'], 'enabled' => (bool) ($item['enabled'] ?? true)];
                }, $stored['featured_links'])
            ));
        } elseif (isset($stored['featured_link_ids']) && is_array($stored['featured_link_ids'])) {
            // Legacy upgrade: convert plain ID array to rich format (all enabled by default).
            $featuredLinks = array_values(array_filter(array_map(function ($id) {
                $id = (int) $id;
                return $id > 0 ? ['id' => $id, 'enabled' => true] : null;
            }, $stored['featured_link_ids'])));
        } else {
            $featuredLinks = [];
        }

        $validStyles = array_keys(\App\Modules\User\Controllers\CreatorProfileController::FEATURED_LINK_STYLES);
        $storedStyle = (string) ($stored['featured_links_style'] ?? '');
        $featuredLinksStyle = in_array($storedStyle, $validStyles, true) ? $storedStyle : 'classic';

        return [
            'featured_links'       => $featuredLinks,
            'featured_links_style' => $featuredLinksStyle,
            'show_link_stats'      => (bool) ($stored['show_link_stats'] ?? $defaults['show_link_stats']),
            'showcase_items'       => is_array($stored['showcase_items'] ?? null)
                ? $stored['showcase_items']
                : $defaults['showcase_items'],
            'highlights'           => array_merge(
                $defaults['highlights'],
                is_array($stored['highlights'] ?? null) ? $stored['highlights'] : []
            ),
            'cta' => [
                'primary'   => $stored['cta']['primary'] ?? null,
                'secondary' => is_array($stored['cta']['secondary'] ?? null) ? $stored['cta']['secondary'] : [],
            ],
        ];
    }

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
            // Every new account starts on the free Starter plan, which has a
            // rolling 1-year free window. Stamp the first window on creation
            // so the yearly re-confirmation reminder has a deadline to track.
            // Reminder-only: lapsing never locks the account or downgrades it.
            if (empty($user->starter_free_window_ends_at)) {
                $user->forceFill(['starter_free_window_ends_at' => now()->addYear()])->saveQuietly();
            }
            \App\Modules\User\Services\PersonalTaskBoardProvisioner::ensureFor($user);
            // Make sure the platform-managed "Sayzio Default Mind" exists so
            // the new account has access to a Mind with product knowledge in
            // it. This is deliberately pushed onto the queue rather than run
            // inline: it creates AI rows, recounts stats and dispatches
            // source-ingest jobs, none of which should sit on the account-
            // creation request path (a slow/misconfigured AI backend, or a
            // sync-queue install where the ingest jobs would run inline, must
            // never be able to stall a first-time sign-up). The provisioner is
            // idempotent, and per-user "My Mind" plus the platform default are
            // both also provisioned lazily the first time a user opens the
            // Mind dashboard, so deferring here is safe. Only dispatch when the
            // platform default is actually missing so we don't enqueue a no-op
            // job on every single sign-up.
            try {
                $platformMindMissing = \App\Modules\User\Models\AiMind::query()
                    ->whereNull('user_id')
                    ->where('is_default', true)
                    ->doesntExist();
                if ($platformMindMissing) {
                    \App\Jobs\ProvisionPlatformAiMindJob::dispatchDeferred();
                }
            } catch (\Throwable $e) {
                // Don't ever break account creation on AI provisioning.
            }
        });

        // Mirror the role-pivot cascade into the audit ledger so that
        // deleting a user account doesn't silently drop the 'detached'
        // rows that reviewers rely on. The `user_roles` foreign key is
        // declared with `cascadeOnDelete()`, which means the DB will
        // wipe pivot rows the instant the user row is gone — without
        // this hook we'd see the original 'attached' audit but no
        // matching 'detached' counterpart. Snapshotting BEFORE the
        // delete fires lets `UserRoleAuditLogger` capture the role
        // slug/name (the role record itself is still around) and the
        // currently-authenticated actor (the admin doing the destroy).
        // Wrapped in try/catch so an audit miss can never block the
        // primary delete; the logger also swallows write failures.
        static::deleting(function (User $user) {
            try {
                $roleIds = $user->roles()->pluck('roles.id')->all();
                if (empty($roleIds)) {
                    return;
                }
                app(\App\Modules\User\Services\UserRoleAuditLogger::class)
                    ->recordDiff(
                        $user,
                        $roleIds,
                        [],
                        \App\Modules\User\Models\UserRoleAudit::SOURCE_USER_DELETED,
                        request()?->ip(),
                    );
            } catch (\Throwable $e) {
                // Never block user deletion on an audit failure — but
                // log it so an outage of the audit write path is
                // visible in operational logs instead of disappearing.
                try {
                    \Illuminate\Support\Facades\Log::error(
                        'User deleting hook: failed to record cascade role-detach audit',
                        ['target_user_id' => $user->id, 'error' => $e->getMessage()],
                    );
                } catch (\Throwable $ignored) {
                    // Logger itself failed — nothing useful to do here.
                }
            }
        });
    }

    public function followers()    { return $this->hasMany(Follow::class, 'creator_id'); }
    public function following()    { return $this->hasMany(Follow::class, 'follower_id'); }
    public function posts()        { return $this->hasMany(CreatorPost::class)->latest(); }
    public function publishedPosts() { return $this->hasMany(CreatorPost::class)->whereNotNull('published_at')->latest('published_at'); }
    public function pinnedPost()    { return $this->hasOne(CreatorPost::class)->whereNotNull('pinned_at')->whereNotNull('published_at')->latest('pinned_at'); }
    public function notifications() { return $this->hasMany(UserNotification::class)->latest('created_at'); }

    /**
     * Admin-managed account badges currently attached to this account.
     * Staff-only labelling (segment/filter/bulk-action the admin user
     * list); the user sees their own badges read-only on the dashboard.
     */
    public function accountBadges()
    {
        return $this->belongsToMany(
            \App\Modules\Admin\Models\AccountBadge::class,
            'account_badge_user',
            'user_id',
            'account_badge_id'
        )->withTimestamps()->withPivot('assigned_by')->orderBy('name');
    }

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

    /**
     * Whether this account has a verified WhatsApp (phone) number attached.
     * A number is only "real" once it has been confirmed via the OTP flow,
     * so an unverified phone identifier does not count. Single source of
     * truth for the WhatsApp connect step, dashboard nudge and the weekly
     * reminder command so they can never disagree on who still needs to add
     * a number.
     */
    public function hasWhatsappNumber(): bool
    {
        return $this->linkedIdentifiers()
            ->where('kind', 'phone')
            ->whereNotNull('verified_at')
            ->exists();
    }

    /**
     * The creator's verified WhatsApp (phone) number string, or null when none
     * is on file. Prefers the primary identifier (linkedIdentifiers() is already
     * ordered is_primary desc). Used by outbound WhatsApp alerts (Task #2765).
     */
    public function whatsappNumber(): ?string
    {
        $identifier = $this->linkedIdentifiers()
            ->where('kind', 'phone')
            ->whereNotNull('verified_at')
            ->first();

        $value = $identifier?->value;

        return ($value !== null && trim($value) !== '') ? $value : null;
    }

    /**
     * The verified WhatsApp number with all but the last four digits masked,
     * e.g. "+1 555 123 4567" → "+••••••4567". Returned to surfaces that show
     * which number is connected (mobile settings) without exposing it in full.
     * Null when no verified number is on file.
     */
    public function maskedWhatsappNumber(): ?string
    {
        $value = $this->whatsappNumber();
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $last   = substr($digits, -4);
        $hidden = max(0, strlen($digits) - strlen($last));
        $prefix = str_starts_with(trim($value), '+') ? '+' : '';

        return $prefix . str_repeat('•', $hidden) . $last;
    }

    /**
     * Whether the creator opted in to WhatsApp payment alerts (new subscriber,
     * tip, PPV/unlock, paid form). Account-level preference stored in the
     * `settings` JSON; defaults off so we never message someone unprompted.
     */
    public function wantsWhatsappPaymentAlerts(): bool
    {
        return (bool) (($this->settings['whatsapp_payment_alerts'] ?? false));
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

    /**
     * Resolve the best available avatar URL for this user in priority order:
     *
     * 1. Custom uploaded avatar stored in users.avatar (also covers Google/social
     *    photos stored there at OAuth sign-up, which are the user's real photos).
     * 2. A connected Google social account photo from SocialAccountConnection
     *    (for users who linked Google via Connected Accounts after sign-up).
     * 3. Gravatar for this email — the Gravatar `d=` parameter points at the
     *    bundled placeholder image so both the Gravatar tier and the placeholder
     *    tier are resolved in a single HTTP request: real Gravatar photo if one
     *    exists, otherwise the placeholder image.
     */
    public function resolveAvatarUrl(): string
    {
        // 1. Custom uploaded avatar or social-OAuth photo already stored on the
        //    user record (never a Gravatar URL, which is always built dynamically).
        if (!empty($this->avatar) && !str_contains((string) $this->avatar, 'gravatar.com')) {
            $avatar = (string) $this->avatar;

            // Legacy `/storage/<path>` values were canonicalized to direct CDN
            // URLs by `storage:canonicalize-legacy-paths` (production confirmed
            // clean July 2026), so runtime resolution is retired. If a stray
            // legacy value ever reappears, log it so it can be re-canonicalized
            // — the value is returned as-is and will hit the 404-logging shim.
            if (str_starts_with($avatar, '/storage/')) {
                \Illuminate\Support\Facades\Log::warning('resolveAvatarUrl: unexpected legacy /storage/ avatar value — re-run storage:canonicalize-legacy-paths', [
                    'user_id' => $this->id,
                    'avatar'  => $avatar,
                ]);
            }

            return $avatar;
        }

        // 2. Connected Google social account photo (ignore null/empty rows so
        //    an empty avatar_url never shadows the Gravatar/placeholder fallback).
        $googleConn = \App\Modules\User\Models\SocialAccountConnection::where('user_id', $this->id)
            ->where('platform', 'google')
            ->whereNotNull('avatar_url')
            ->where('avatar_url', '!=', '')
            ->orderByDesc('id')
            ->first();
        if ($googleConn && trim((string) $googleConn->avatar_url) !== '') {
            return (string) $googleConn->avatar_url;
        }

        // 3. Gravatar with the bundled placeholder as the fallback default so
        //    both tiers (real Gravatar photo / placeholder) resolve in one request.
        $hash        = md5(strtolower(trim((string) ($this->email ?? ''))));
        $placeholder = url('images/avatar-placeholder.svg');
        return 'https://www.gravatar.com/avatar/' . $hash
            . '?d=' . urlencode($placeholder) . '&s=160&r=g';
    }

    /**
     * Raw stored avatar value for creator-facing public surfaces: the
     * creator-profile-specific override when set, else the account avatar.
     * Callers emit it through PublicStorageUrl::resolve() like `avatar`.
     */
    public function creatorAvatarRaw(): ?string
    {
        $override = trim((string) ($this->creator_avatar ?? ''));
        return $override !== '' ? $override : $this->avatar;
    }

    /**
     * Effective creator-profile avatar URL: the creator-specific override
     * (resolved via PublicStorageUrl) when set, else the account avatar
     * resolution chain (upload → Google photo → Gravatar → placeholder).
     */
    public function resolveCreatorAvatarUrl(): string
    {
        $override = trim((string) ($this->creator_avatar ?? ''));
        if ($override !== '') {
            return (string) \App\Support\PublicStorageUrl::resolve($override);
        }
        return $this->resolveAvatarUrl();
    }

    public function publicHandle(): string
    {
        return $this->handle ?: ('user' . $this->id);
    }

    public function primaryBiolink(): ?Link
    {
        return Link::where('user_id', $this->id)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
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
     * (and holders of `user.workspaces.access_any`) always pass; members
     * consult their permission blob.
     */
    public function canInWorkspace(Workspace $workspace, string $permission): bool
    {
        if ($this->hasPermission('user.workspaces.access_any')) return true;
        if ((int) $workspace->owner_user_id === $this->id) return true;
        $membership = $this->membershipFor($workspace);
        if (!$membership) return false;
        return $membership->can($permission);
    }

    /**
     * Admin-granted asset-transfer capability. True when an admin has
     * explicitly granted it (transfer_capability_granted_at) OR when this
     * user's email matches a back-office Admin record (implicit grant).
     * Single authorization helper used by every transfer surface (web,
     * API, blade visibility) so the check can never drift.
     */
    public function canTransferAssets(): bool
    {
        if (!empty($this->transfer_capability_granted_at)) {
            return true;
        }
        $email = strtolower(trim((string) $this->email));
        if ($email === '') {
            return false;
        }
        try {
            return \App\Modules\Admin\Models\Admin::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->exists();
        } catch (\Throwable) {
            return false;
        }
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

    public function brandKits()
    {
        return $this->hasMany(\App\Modules\User\Models\BrandKit::class);
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
        // Users holding the plan-limits bypass permission have unlimited
        // storage, regardless of plan.
        if ($this->hasPermission('user.plan_limits.bypass')) {
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

    public function isOnFreePlan(): bool
    {
        return !$this->plan_id || ($this->plan && $this->plan->slug === 'free');
    }

    /**
     * True when this account currently sits on the lineup's default (free
     * Starter) plan. Flag-based so it keeps working if the free tier is ever
     * re-slugged from the admin lineup. Accounts with no plan_id are treated
     * as default (they fall back to the default plan everywhere).
     */
    public function onDefaultPlan(): bool
    {
        if (!$this->plan_id) return true;
        $default = Plan::defaultPlan();
        return $default && (int) $this->plan_id === (int) $default->id;
    }

    /**
     * Whether the Starter free window has lapsed (or is about to within the
     * given lead time). Drives the re-confirmation reminder + in-app banner.
     * Reminder-only — a lapsed window never restricts the account.
     */
    public function starterFreeWindowDueWithin(int $leadDays = 14): bool
    {
        if (!$this->starter_free_window_ends_at) return false;
        return now()->greaterThanOrEqualTo($this->starter_free_window_ends_at->copy()->subDays($leadDays));
    }

    /**
     * One-click "renew free for another year". Pushes the free window out by
     * 12 months from whichever is later (now, or the existing deadline so a
     * proactive renewal doesn't lose remaining days) and clears the
     * per-window reminder stamp so next year's nudge can fire again.
     */
    public function renewStarterFreeWindow(): void
    {
        $base = $this->starter_free_window_ends_at && $this->starter_free_window_ends_at->isFuture()
            ? $this->starter_free_window_ends_at->copy()
            : now();
        $this->forceFill([
            'starter_free_window_ends_at'      => $base->addYear(),
            'starter_renewal_reminder_sent_at' => null,
        ])->save();
    }

    /**
     * Roles attached to this user from the shared roles table (web guard
     * is the user pool; the admin guard is reserved for the back-office
     * Admin model).
     */
    public function roles()
    {
        return $this->belongsToMany(
            \App\Modules\Admin\Models\Role::class,
            'user_roles',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * Back-office Admin record (if any) that belongs to the same person
     * as this user. The link is by email (case-insensitive) — the two
     * auth pools (web `users` / admin `admins`) share no foreign key, so
     * a matching email is what marks "this user is also an admin".
     * Resolved lazily and cached for the lifetime of the request.
     */
    protected ?\App\Modules\Admin\Models\Admin $cachedAdminAccount = null;
    protected bool $adminAccountResolved = false;

    public function adminAccount(): ?\App\Modules\Admin\Models\Admin
    {
        if ($this->adminAccountResolved) {
            return $this->cachedAdminAccount;
        }
        $this->adminAccountResolved = true;

        // Resolution is delegated to the hardened bridge, which binds the
        // user/admin identities via an explicit, immutable `admins.user_id`
        // link established only under proof of mailbox ownership — never by
        // a bare, user-controlled email string.
        return $this->cachedAdminAccount =
            \App\Modules\Common\Services\AdminUserBridge::resolveAdminForUser($this);
    }

    /** True when this user has a matching admin record at all. */
    public function hasAdminAccount(): bool
    {
        return $this->adminAccount() !== null;
    }

    /** True when the matching admin record exists and is active (can switch in). */
    public function hasActiveAdminAccount(): bool
    {
        $admin = $this->adminAccount();
        return $admin !== null && $admin->status === 'active';
    }

    /** Drop the cached admin-account lookup (call after grant/revoke). */
    public function flushAdminAccountCache(): void
    {
        $this->cachedAdminAccount = null;
        $this->adminAccountResolved = false;
    }

    /**
     * Resolved set of permission slugs across every role attached to
     * this user. Cached on the instance for the lifetime of the request
     * so repeated checks don't re-query the role/permission tables.
     *
     * @var array<int,string>|null
     */
    protected ?array $cachedPermissionSlugs = null;

    /** Drop the in-memory permission cache (call after attach/detach). */
    public function flushPermissionCache(): void
    {
        $this->cachedPermissionSlugs = null;
    }

    /**
     * @return array<int,string>
     */
    public function resolvedPermissionSlugs(): array
    {
        if ($this->cachedPermissionSlugs !== null) {
            return $this->cachedPermissionSlugs;
        }
        $slugs = [];
        try {
            $this->loadMissing('roles.permissions');
            foreach ($this->roles as $role) {
                foreach ($role->permissions as $perm) {
                    if (!empty($perm->slug)) {
                        $slugs[(string) $perm->slug] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Defensive: if the schema isn't migrated yet (eg. tests
            // running before the user_roles migration), treat the user
            // as having no permissions rather than crashing.
            $slugs = [];
        }
        return $this->cachedPermissionSlugs = array_keys($slugs);
    }

    /** True iff this user holds the named permission via any of its roles. */
    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->resolvedPermissionSlugs(), true);
    }

    /**
     * True iff this user holds AT LEAST ONE of the listed permissions.
     *
     * @param array<int,string> $slugs
     */
    public function hasAnyPermission(array $slugs): bool
    {
        $set = $this->resolvedPermissionSlugs();
        foreach ($slugs as $s) {
            if (in_array($s, $set, true)) return true;
        }
        return false;
    }

    /**
     * True iff this user holds at least one platform role on the web guard.
     *
     * Web-guard roles (managed under /user/access) are the platform staff /
     * admin roles — a regular end-user has none. Used by the admin-only
     * maintenance lockdown so platform staff keep full access while everyone
     * else is gated.
     */
    public function hasAdminRole(): bool
    {
        try {
            $this->loadMissing('roles');
            foreach ($this->roles as $role) {
                if ((string) ($role->guard ?? '') === 'web') {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // Defensive: schema not migrated yet (e.g. tests before the
            // user_roles migration) — treat as a non-admin.
            return false;
        }
        return false;
    }

    /**
     * Query scope: users that hold the named permission via any role.
     * Useful when picking up "the platform-admin user pool" for tasks
     * such as ops-alert fan-out or platform AI billing fallback.
     */
    public function scopeWithPermission($query, string $slug)
    {
        return $query->whereHas('roles.permissions', fn ($q) => $q->where('slug', $slug));
    }

    public function getPlanFeature(string $key, $default = null)
    {
        // Holders of `user.plan_limits.bypass` bypass ALL plan gating
        // regardless of what any plan record stores: numeric limits
        // become effectively unlimited and boolean feature flags become
        // enabled. Non-scalar defaults (eg. arrays for upload_limits)
        // fall through to the explicit plan value if set, otherwise the
        // default — so per-context overrides can still be applied
        // explicitly even for bypass-holders.
        if ($this->hasPermission('user.plan_limits.bypass')) {
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
        return $this->effectivePlanFeatures()[$key] ?? $default;
    }

    /**
     * Plan features merged with any active subscription addons (quantity
     * aware — additive `*_extra` keys add `value × qty`). Memoized per
     * model instance so the hot capability path doesn't re-query the
     * user's subscription addons on every `getPlanFeature()` call. Falls
     * back to the bare plan features when there are no addons.
     */
    public function effectivePlanFeatures(): array
    {
        if ($this->effectivePlanFeaturesCache !== null) {
            return $this->effectivePlanFeaturesCache;
        }
        $base = ($this->plan && is_array($this->plan->features)) ? $this->plan->features : [];
        $addons = \App\Services\EffectivePlanFeatures::addonsForUser($this);
        $merged = empty($addons)
            ? $base
            : \App\Services\EffectivePlanFeatures::mergeFeatures($base, $addons);

        return $this->effectivePlanFeaturesCache = $merged;
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
     * -1 means unlimited; the `user.plan_limits.bypass` permission already
     * makes getPlanFeature return PHP_INT_MAX.
     */
    public function planUnderLimit(string $key, int $currentCount, int $default = 0): bool
    {
        $max = (int) $this->getPlanFeature($key, $default);
        if ($max === -1) return true;
        if ($this->hasPermission('user.plan_limits.bypass')) return true;
        return $currentCount < $max;
    }

    /**
     * True when the user's plan permits a given biolink block type.
     * `block_types_allowed` may be `'*'` (all), or an array of block-type
     * slugs. Empty / missing entry means "all allowed" (legacy behavior).
     */
    public function userCanUseBlockType(string $slug): bool
    {
        if ($this->hasPermission('user.plan_limits.bypass')) return true;
        $allowed = $this->getPlanFeature('block_types_allowed', '*');
        if ($allowed === '*' || $allowed === null || $allowed === '') return true;
        if (!is_array($allowed)) return true;
        // Resolve friendly allowlist-only synonyms (e.g. `link_button`,
        // `social_icons`, `tiktok`, `twitter`) to the real block types the
        // editor actually POSTs (`link`, `socials`, `tiktok_video`, …), then
        // match the requested type as-is. Real types are never collapsed, so
        // a plan that allows `link` but not `cta_button` keeps enforcing that.
        // Keeps "what pricing shows" == "what we enforce".
        $normalized = \App\Modules\User\Support\BlockTypeRegistry::canonicalizeAllowlist($allowed);
        return in_array($slug, $normalized, true);
    }

    /**
     * True when the user's plan permits a given per-link advanced setting.
     * Mapping is centralized so controllers/middleware stay consistent.
     */
    public function userCanUseLinkSetting(string $setting): bool
    {
        if ($this->hasPermission('user.plan_limits.bypass')) return true;
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
            ->public()
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
        // Holders of `user.plan_limits.bypass` get no constraint — the
        // sentinel "PHP_INT_MAX" returned by getPlanFeature for those
        // users must NOT become a literal min length.
        if ($this->hasPermission('user.plan_limits.bypass')) {
            return ['min' => 1, 'max' => 191];
        }
        $min = (int) $this->getPlanFeature('min_alias_length', 4);
        $max = (int) $this->getPlanFeature('max_alias_length', 50);
        if ($min < 1)    $min = 1;
        if ($max < 1)    $max = 1;
        if ($max > 191)  $max = 191; // matches DB column width
        if ($min > $max) $min = $max;
        return ['min' => $min, 'max' => $max];
    }

    /**
     * Maximum number of additional aliases for a link of the given type.
     * The primary alias does NOT count toward the limit (it's free with the
     * link). `-1` means unlimited; `0` means no extra aliases.
     *
     * The plan feature `max_aliases_per_link` may be stored as either:
     *  - a scalar int  — the legacy / global allowance applied to every type, or
     *  - a map         — `['default' => int, '<type>' => int, ...]` where a
     *                    per-type entry wins, otherwise the map's `default`
     *                    key, otherwise the catalogue default (0).
     *
     * When no $type is supplied the global / default value is returned.
     */
    public function getMaxAliasesPerLink(?string $type = null): int
    {
        $raw = $this->getPlanFeature('max_aliases_per_link', 0);

        // Bypass holders get PHP_INT_MAX (int) from getPlanFeature regardless
        // of the stored shape, so the is_int() branch below covers them.
        if (is_array($raw)) {
            if ($type !== null && isset($raw[$type]) && is_numeric($raw[$type])) {
                return (int) $raw[$type];
            }
            if (isset($raw['default']) && is_numeric($raw['default'])) {
                return (int) $raw['default'];
            }
            return 0;
        }

        return (int) $raw;
    }

    /**
     * How many days of analytics history this user's plan can look back over.
     * `-1` means unlimited (no clamp). Holders of `user.plan_limits.bypass`
     * (e.g. super admins) are never clamped. Any configured value below the
     * 30-day floor — or a missing/zero value — resolves to 30 so analytics
     * always show at least a month.
     */
    public function statsRetentionDays(): int
    {
        if ($this->hasPermission('user.plan_limits.bypass')) {
            return -1;
        }
        $days = (int) $this->getPlanFeature('stats_retention_days', 30);
        if ($days === -1) {
            return -1;
        }
        return $days < 30 ? 30 : $days;
    }
}
