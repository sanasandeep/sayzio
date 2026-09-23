<?php

namespace App\Modules\User\Models;

use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumeTemplateRegistry;
use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    protected $fillable = [
        'user_id', 'template_id', 'color_theme_id', 'sections', 'page_background', 'share_button',
        'is_public', 'visibility', 'password',
        'expires_at', 'share_revision',
        'allow_indexing', 'view_count', 'meta_description',
        'is_public_pdf',
        // Multi-version support — each row is a named version. Exactly
        // one row per user has is_default=true.
        'name', 'slug', 'is_default',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'sections'        => 'array',
            'page_background' => 'array',
            // The share button + QR, kept here rather than on a link for the
            // same reason as the background: a resume has two public URLs
            // and only one of them has a Link in scope.
            'share_button'    => 'array',
            'is_public'       => 'boolean',
            'allow_indexing'  => 'boolean',
            'view_count'      => 'integer',
            'is_public_pdf'   => 'boolean',
            'expires_at'      => 'datetime',
            'share_revision'  => 'integer',
            'is_default'      => 'boolean',
        ];
    }

    /**
     * Reserved slug used as a stable fallback when a row hasn't been
     * given an explicit slug yet. Also the slug applied to the default
     * version migrated from the pre-versioning era.
     */
    public const DEFAULT_SLUG = 'default';

    /**
     * Allowed visibility tiers — mirrors Link.visibility so the public
     * page can reuse the same gating logic (registered / followers /
     * subscribers / password) without inventing parallel concepts.
     */
    public const VISIBILITIES = ['public', 'registered', 'followers', 'subscribers', 'password'];

    /** True when the resume can be reached at /{handle}/resume at all. */
    public function isPublished(): bool
    {
        return (bool) $this->is_public;
    }

    /** True when the visibility tier requires a password unlock. */
    public function requiresPassword(): bool
    {
        return $this->visibility === 'password' && filled($this->password);
    }

    /**
     * True when the share has an expiration date that has already
     * passed. Owners are unaffected — enforcement lives in the public
     * controller and only applies to non-owner traffic.
     */
    public function isShareExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Session key used to remember a successful password unlock for
     * this resume. Embeds `share_revision` so bumping that counter
     * (a "revoke share" action) invalidates every previously-unlocked
     * session without changing the public URL.
     */
    public function unlockSessionKey(): string
    {
        return sprintf('resume_unlocked_%d_v%d', $this->id, (int) $this->share_revision);
    }

    /**
     * Public-page URL for this resume, or null when the user has no handle.
     *
     * The default version always resolves at `/{handle}/resume` so old
     * shared links keep working; non-default versions get a stable
     * `/v/{slug}` suffix so each can be sent on its own.
     */
    public function publicUrl(): ?string
    {
        $u = $this->user ?? $this->user()->first();
        if (!$u) return null;
        $base = '/' . $u->publicHandle() . '/resume';
        if (!$this->is_default) {
            $slug = $this->slug ?: self::DEFAULT_SLUG;
            $base .= '/v/' . $slug;
        }
        return url($base);
    }

    /**
     * Effective slug — falls back to the reserved DEFAULT_SLUG when a
     * row was created before slugs existed. Always safe for URL use.
     */
    public function effectiveSlug(): string
    {
        return $this->slug ?: self::DEFAULT_SLUG;
    }

    /**
     * Friendly display label — falls back to a generic placeholder so
     * the version switcher never renders an empty pill.
     */
    public function displayName(): string
    {
        $name = trim((string) $this->name);
        return $name !== '' ? $name : 'Untitled version';
    }

    /**
     * Default shape for the JSON `sections` blob. Reads merge into this
     * so callers can rely on every key being present.
     *
     * @return array<string,mixed>
     */
    public static function defaultSections(): array
    {
        return [
            'header' => [
                'name'     => '',
                'headline' => '',
                'location' => '',
                'email'    => '',
                'phone'    => '',
                'website'  => '',
                // ID of the UserFile that holds the header photo (null if
                // none uploaded). The serving URL is resolved at present
                // time and is owner-only — see ResumeController::present().
                'photo_user_file_id' => null,
            ],
            'summary' => '',
            // List of additional user-defined sections. Each entry:
            //   ['key' => 'volunteering', 'title' => 'Volunteering']
            // Their items live in resume_section_items with
            // section_type = 'custom' and data.custom_section_key = key.
            'custom_sections' => [],
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ResumeSectionItem::class)
            ->orderBy('section_type')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function views()
    {
        return $this->hasMany(ResumeView::class)->orderByDesc('viewed_at');
    }

    /** Items of a particular section, ordered. */
    public function itemsOfType(string $type)
    {
        return $this->items()->where('section_type', $type);
    }

    /**
     * Items grouped by section, with hidden ones left out.
     *
     * The one thing the three public renderers -- the page, the PDF and
     * the ATS checker -- must agree on. They each used to call
     * `$resume->items->groupBy('section_type')` for themselves, which is
     * exactly the shape that lets a fourth renderer be added later and
     * quietly show what the other three hide.
     *
     * The builder deliberately does NOT call this: a hidden item stays
     * fully editable, which is the point of hiding it rather than
     * deleting it.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ResumeSectionItem>>
     */
    public function publicItemsByType()
    {
        return $this->items
            ->reject(fn (ResumeSectionItem $i) => (bool) $i->is_hidden)
            ->groupBy('section_type');
    }

    /**
     * Merged view of `sections` JSON (always returns every default key
     * even when the row stored a partial blob).
     */
    public function getMergedSections(): array
    {
        return array_replace_recursive(self::defaultSections(), $this->sections ?? []);
    }

    /**
     * Resolved template metadata (always returns SOMETHING — falls back
     * to the default template when the stored id is missing/invalid).
     */
    public function templateMeta(): array
    {
        return ResumeTemplateRegistry::find($this->template_id)
            ?? ResumeTemplateRegistry::find(ResumeTemplateRegistry::defaultId());
    }

    public function colorThemeMeta(): array
    {
        return ResumeColorThemeRegistry::find($this->color_theme_id)
            ?? ResumeColorThemeRegistry::find(ResumeColorThemeRegistry::defaultId());
    }
}
