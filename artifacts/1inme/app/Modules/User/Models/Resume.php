<?php

namespace App\Modules\User\Models;

use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumeTemplateRegistry;
use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    protected $fillable = [
        'user_id', 'template_id', 'color_theme_id', 'sections',
        'is_public', 'visibility', 'password',
        'expires_at', 'share_revision',
        'allow_indexing', 'view_count', 'meta_description',
        'is_public_pdf',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'sections'        => 'array',
            'is_public'       => 'boolean',
            'allow_indexing'  => 'boolean',
            'view_count'      => 'integer',
            'is_public_pdf'   => 'boolean',
            'expires_at'      => 'datetime',
            'share_revision'  => 'integer',
        ];
    }

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

    /** Public-page URL for this resume, or null when the user has no handle. */
    public function publicUrl(): ?string
    {
        $u = $this->user ?? $this->user()->first();
        if (!$u) return null;
        return url('/' . $u->publicHandle() . '/resume');
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
