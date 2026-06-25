<?php

namespace App\Modules\Common\Services;

use App\Modules\Admin\Models\AppSetting;

/**
 * Thin wrapper around AppSetting for blog-wide configuration so the
 * key namespace + defaults live in one place.
 */
class BlogSettings
{
    public const APPROVAL_AUTO       = 'auto';
    public const APPROVAL_RETURNING  = 'returning';
    public const APPROVAL_MANUAL     = 'manual';
    public const APPROVAL_CLOSED     = 'closed';

    /**
     * @return array{
     *   approval_mode:string,
     *   allow_guest_viewer_comments:bool,
     *   require_email:bool,
     *   spam_filter:bool,
     *   comments_per_page:int,
     *   default_og_image:?string,
     *   hero_eyebrow:string,
     *   hero_heading:string,
     *   hero_subheading:string,
     *   hero_cta_label:?string,
     *   hero_cta_url:?string,
     *   reply_role_slugs:array<int,string>
     * }
     */
    public static function all(): array
    {
        $raw = AppSetting::get('blog_settings', []);
        $raw = is_array($raw) ? $raw : [];
        return [
            'approval_mode'                => (string) ($raw['approval_mode'] ?? self::APPROVAL_MANUAL),
            'allow_guest_viewer_comments'  => (bool)   ($raw['allow_guest_viewer_comments'] ?? true),
            'require_email'                => (bool)   ($raw['require_email'] ?? true),
            'spam_filter'                  => (bool)   ($raw['spam_filter'] ?? true),
            'comments_per_page'            => (int)    ($raw['comments_per_page'] ?? 25),
            'default_og_image'             => $raw['default_og_image'] ?? null,
            'hero_eyebrow'                 => (string) ($raw['hero_eyebrow'] ?? 'The 1INME Blog'),
            'hero_heading'                 => (string) ($raw['hero_heading'] ?? 'Stories, tips & product news.'),
            'hero_subheading'              => (string) ($raw['hero_subheading'] ?? 'Practical playbooks for creators, marketers and small teams who live in their Link in Bio.'),
            'hero_cta_label'               => $raw['hero_cta_label'] ?? null,
            'hero_cta_url'                 => $raw['hero_cta_url'] ?? null,
            'reply_role_slugs'             => array_values(array_filter((array) ($raw['reply_role_slugs'] ?? ['super-admin', 'staff']))),
            'cta_on_pages'                 => array_values(array_filter((array) ($raw['cta_on_pages'] ?? ['features', 'about', 'how-it-works']))),
        ];
    }

    public static function save(array $values): void
    {
        AppSetting::put('blog_settings', $values);
    }
}
