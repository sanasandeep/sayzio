<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;

final class CreatorProfileVisibility
{
    public static function editorSection(string $field): string
    {
        return match (true) {
            str_starts_with($field, 'sections'), str_starts_with($field, 'highlights_') => 'visibility',
            (bool) preg_match('/^(handle|bio|tagline|location|niche_tags|special_dates|organizer)/', $field) => 'about',
            (bool) preg_match('/^(socials|featured_links|showcase_items|showcase_show_link_stats|cta_)/', $field) => 'links',
            (bool) preg_match('/^(profile_published|profile_destination|redirect_link_id|primary_biolink_id|watermark|country_|muted_|dmca_|digest_|dm_)/', $field) => 'settings',
            default => 'design',
        };
    }

    /** Apply unsaved visibility only to an authenticated owner's in-memory preview. */
    public static function preview(User $creator, Request $request, bool $isOwner): void
    {
        if (!$isOwner || !$request->boolean('cp_preview')) return;
        $sections = $creator->profileSectionVisibility();
        foreach ((array) $request->query('cp_sections', []) as $key => $value) {
            if (array_key_exists($key, User::PROFILE_DEFAULT_VISIBILITY) && in_array($value, ['0','1',0,1], true)) $sections[$key] = (bool) $value;
        }
        $creator->profile_section_visibility = $sections;
        $showcase = (array) ($creator->profile_showcase ?? []);
        foreach ((array) $request->query('cp_highlights', []) as $key => $value) {
            if (in_array($key, ['show_followers','show_links','show_member_since','show_verified'], true) && in_array($value, ['0','1',0,1], true)) $showcase['highlights'][$key] = (bool) $value;
        }
        if ($request->has('cp_link_stats')) $showcase['show_link_stats'] = $request->boolean('cp_link_stats');
        $creator->profile_showcase = $showcase;
    }
}
