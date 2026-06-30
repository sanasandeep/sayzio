<?php

namespace App\Modules\User\Models;

use App\Modules\Common\Models\AiCompanionConversation;
use Illuminate\Database\Eloquent\Model;
// Link lives in this same namespace; included for IDE clarity.
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * AI Companion — an AiPersonaAgent bound to a placement (biolink /
 * embed / inbox) with its own visual config, domain allow-list, and
 * usage caps. The Persona supplies the brain; the Companion supplies
 * the surface and the meter.
 */
class AiCompanion extends Model
{
    protected $table = 'ai_companions';

    public const PLACEMENT_BIOLINK = 'biolink';
    public const PLACEMENT_EMBED   = 'embed';
    public const PLACEMENT_INBOX   = 'inbox';
    public const PLACEMENT_PAGE    = 'page';

    public const PLACEMENTS = [
        self::PLACEMENT_BIOLINK => 'Biolink chatbot',
        self::PLACEMENT_EMBED   => 'External website embed',
        self::PLACEMENT_INBOX   => 'Inbox auto-reply bot',
        self::PLACEMENT_PAGE    => 'Full-page AI chat',
    ];

    protected $fillable = [
        'user_id', 'persona_id', 'public_id', 'name', 'placement',
        'config', 'allowed_domains',
        'free_turns_per_month', 'hard_cap_per_month',
        'is_disabled', 'disabled_reason', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'config'               => 'array',
            'allowed_domains'      => 'array',
            'free_turns_per_month' => 'integer',
            'hard_cap_per_month'   => 'integer',
            'is_disabled'          => 'boolean',
            'last_used_at'         => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Bust the biolink-editor AI Companion dropdown cache on any write so a
        // newly created / edited / deleted companion shows up immediately when
        // the owner opens the editor. See BiolinkBlockController::editor().
        static::saved(fn (self $c) => static::forgetEditorCompanionsCache($c));
        static::deleted(fn (self $c) => static::forgetEditorCompanionsCache($c));
    }

    protected static function forgetEditorCompanionsCache(self $companion): void
    {
        $uid = $companion->user_id;
        if (!$uid) return;
        \Illuminate\Support\Facades\Cache::forget("editor:companions:{$uid}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(AiPersonaAgent::class, 'persona_id');
    }

    public function links(): BelongsToMany
    {
        return $this->belongsToMany(Link::class, 'ai_companion_links', 'companion_id', 'link_id')
            ->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiCompanionConversation::class, 'companion_id');
    }

    /**
     * Generate a URL-safe public id. Random rather than sluggable on
     * purpose — placement is invisible to visitors and we don't want
     * brand names enumerable.
     */
    public static function newPublicId(): string
    {
        do {
            $id = 'cmp_' . Str::lower(Str::random(20));
        } while (self::where('public_id', $id)->exists());
        return $id;
    }

    /** Default visual config — overridden by user choices in `config`. */
    public static function defaultConfig(): array
    {
        return [
            'theme'              => 'auto',          // auto|light|dark
            'accent'             => '#7c3aed',
            'launcher_icon'      => 'fa-comments',
            'launcher_label'     => 'Chat',
            'position'           => 'bottom-right',  // bottom-right|bottom-left
            'greeting_bubble'    => null,            // pop-up text shown by closed launcher
            'placeholder'        => 'Ask me anything…',
            'show_branding'      => true,            // "Powered by Sayzio" (hide gated by remove_branding)
            'custom_branding_text' => null,          // replacement footer text (gated by custom_branding)
            'custom_branding_url'  => null,          // replacement footer link (gated by custom_branding)
            'avatar_url'         => null,            // custom agent avatar (gated by either branding feature)
            'auto_open_after_ms' => 0,               // 0 = never auto open
            'inline'             => false,           // biolink only — render inline instead of floating
            'auto_send_inbox'    => false,           // inbox only — auto-send draft instead of staging
        ];
    }

    public function effectiveConfig(): array
    {
        return array_merge(self::defaultConfig(), (array) ($this->config ?? []));
    }

    /**
     * Resolve the owner's branding/avatar plan entitlements. These gate
     * three creator capabilities on every AI agent surface:
     *   - can_hide_branding   ("remove_branding") — hide "Powered by Sayzio".
     *   - can_custom_branding ("custom_branding")  — replace it with own text+URL.
     *   - can_avatar          (either of the above) — set a custom agent avatar.
     * Resolved from the live plan so a downgrade strips the capability even
     * if stale values linger in config.
     *
     * @return array{can_hide_branding:bool,can_custom_branding:bool,can_avatar:bool}
     */
    public function brandingCapabilities(): array
    {
        $user      = $this->user;
        $canHide   = $user ? (bool) $user->planFeatureEnabled('remove_branding') : false;
        $canCustom = $user ? (bool) $user->planFeatureEnabled('custom_branding') : false;

        return [
            'can_hide_branding'   => $canHide,
            'can_custom_branding' => $canCustom,
            'can_avatar'          => $canHide || $canCustom,
        ];
    }

    /**
     * The branding/avatar values to actually render on a public surface,
     * gated by the owner's current plan. This is the single read-time
     * enforcement point: even if config still holds custom branding or an
     * avatar from a previous plan, an ungated owner falls back to the
     * default "Powered by Sayzio" footer and the default robot avatar.
     *
     * @return array{show_branding:bool,brand_text:?string,brand_url:?string,avatar_url:?string}
     */
    public function brandingConfig(): array
    {
        $cfg  = $this->effectiveConfig();
        $caps = $this->brandingCapabilities();

        $showBranding = $caps['can_hide_branding'] ? (bool) ($cfg['show_branding'] ?? true) : true;
        $brandText    = $caps['can_custom_branding'] ? (trim((string) ($cfg['custom_branding_text'] ?? '')) ?: null) : null;
        $brandUrl     = $caps['can_custom_branding'] ? (trim((string) ($cfg['custom_branding_url'] ?? '')) ?: null) : null;
        $avatarUrl    = $caps['can_avatar'] ? (trim((string) ($cfg['avatar_url'] ?? '')) ?: null) : null;

        return [
            'show_branding' => $showBranding,
            'brand_text'    => $brandText,
            'brand_url'     => $brandUrl,
            'avatar_url'    => $avatarUrl,
        ];
    }

    /**
     * Returns true when `$origin` (a scheme://host[:port]) is allowed
     * to call the public endpoint for this companion. Same-origin
     * Sayzio requests bypass this check at the controller layer.
     */
    public function originAllowed(?string $origin): bool
    {
        $origin = trim((string) $origin);
        if ($origin === '') return false;
        $host = parse_url($origin, PHP_URL_HOST) ?: '';
        if ($host === '') return false;
        $list = array_filter(array_map('strtolower', (array) ($this->allowed_domains ?? [])));
        if (!$list) return false;
        $host = strtolower($host);
        foreach ($list as $allowed) {
            $allowed = trim($allowed);
            if ($allowed === '') continue;
            if ($allowed === $host) return true;
            // Treat a leading dot as "this domain and any subdomain".
            if (str_starts_with($allowed, '.') && str_ends_with($host, $allowed)) return true;
        }
        return false;
    }
}
