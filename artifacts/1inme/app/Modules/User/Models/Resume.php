<?php

namespace App\Modules\User\Models;

use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumeTemplateRegistry;
use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    protected $fillable = [
        'user_id', 'template_id', 'color_theme_id', 'sections',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
        ];
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
