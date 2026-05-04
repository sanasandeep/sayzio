<?php

if (!function_exists('workspace_owner')) {
    /**
     * Return the User model that owns the current workspace, or the
     * signed-in user when no workspace is bound (CLI, public flows, etc.).
     *
     * Use this in resource controllers in place of `$request->user()` when
     * the call is loading workspace-owned relations like `->forms()` or
     * `->links()` — a team member's own user has no forms/links of its own.
     */
    function workspace_owner(): ?\App\Modules\User\Models\User
    {
        if (app()->bound('workspace_owner')) {
            return app('workspace_owner');
        }
        return auth()->user();
    }
}

if (!function_exists('fa_icon_class')) {
    /**
     * Resolve a Font Awesome icon string to a complete class string with
     * the right style prefix (fas/fab/far/fal). The icon picker stores
     * the full prefix (e.g. "fab fa-spotify"), but legacy / hand-typed
     * values often arrive as just the slug ("fa-spotify" or "spotify").
     * Without this helper they get rendered with the solid prefix and
     * silently disappear, because brand glyphs only exist in the brands
     * font family.
     *
     * Rules:
     *   - "fab fa-x" / "fas fa-x" / "far fa-x" / "fal fa-x" → returned as-is
     *   - bare "fa-x"  → "fab fa-x" if x is a known brand slug, else "fas fa-x"
     *   - bare "x"     → same, but with the "fa-" prefix added too
     *   - empty / null → "" (caller should not render an <i>)
     */
    function fa_icon_class(?string $raw, string $fallback = 'fas fa-link'): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return $fallback;

        // Already prefixed with a style — trust it. Accepts FA5/6 short
        // form ("fas fa-x", "fab fa-x") and FA6 long form ("fa-solid fa-x",
        // "fa-brands fa-x", "fa-regular fa-x", "fa-light fa-x").
        if (preg_match('/^(fa[sbrl]|fa-(solid|brands|regular|light))\s+fa-/', $raw)) return $raw;

        // Strip a leading "fa-" so we can compare the bare slug to the
        // brand list. We re-add it on the way out.
        $slug = preg_replace('/^fa-/', '', $raw);

        // FA brand slugs the editor / catalog actually surface in this
        // app. Keep alphabetical so it's obvious where to add new ones.
        // Source: Font Awesome 6 free brands set, restricted to brands
        // we know are linked from biolink blocks (music, video, social,
        // commerce, dev). Adding a slug here is cheap; missing one only
        // means it falls back to a solid square, never a crash.
        static $brands = [
            'amazon', 'android', 'apple', 'app-store', 'app-store-ios', 'behance',
            'bluesky', 'bandcamp', 'cash-app', 'codepen', 'discord', 'dribbble',
            'dropbox', 'etsy', 'facebook', 'facebook-f', 'facebook-messenger',
            'github', 'gitlab', 'goodreads', 'google', 'google-play', 'instagram',
            'kickstarter', 'kick', 'last-fm', 'line', 'linkedin', 'linkedin-in',
            'mastodon', 'medium', 'meetup', 'mixcloud', 'napster', 'patreon',
            'paypal', 'periscope', 'pinterest', 'pinterest-p', 'playstation',
            'product-hunt', 'reddit', 'reddit-alien', 'rumble', 'shopify',
            'signal-messenger', 'skype', 'slack', 'snapchat', 'soundcloud',
            'spotify', 'square-bluesky', 'square-instagram', 'square-facebook',
            'square-pinterest', 'square-snapchat', 'square-threads',
            'square-twitter', 'square-x-twitter', 'square-youtube', 'stackoverflow',
            'steam', 'stripe', 'telegram', 'threads', 'tidal', 'tiktok',
            'tumblr', 'twitch', 'twitter', 'unity', 'venmo', 'viber', 'vimeo',
            'vimeo-v', 'vk', 'weixin', 'whatsapp', 'wechat', 'x-twitter', 'xbox',
            'yelp', 'youtube', 'youtube-square',
        ];

        $prefix = in_array($slug, $brands, true) ? 'fab' : 'fas';
        return $prefix . ' fa-' . $slug;
    }
}

if (!function_exists('workspace_owner_id')) {
    /**
     * Return the user id that "owns" the resources visible in the current
     * request — i.e. the active workspace's owner_user_id when one is bound,
     * or the signed-in user's id otherwise.
     *
     * Resource controllers use this in place of `auth()->id()` /
     * `$request->user()->id` so that team members acting inside an owner's
     * workspace see and operate on the owner's data, not their own.
     */
    function workspace_owner_id(): ?int
    {
        if (app()->bound('workspace_owner')) {
            return app('workspace_owner')->id;
        }
        return auth()->id();
    }
}
