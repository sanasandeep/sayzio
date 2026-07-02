<?php

namespace App\Modules\Common\Support;

/**
 * Single source of truth for the reserved words/prefixes that the public
 * `/{alias}` catch-all routes must NOT capture.
 *
 * Both the page route (`redirect.handle`) and its web-app manifest route
 * (`redirect.manifest`) derive their `where()` regex from {@see pattern()},
 * so the two route lists can never silently drift apart — the bug that
 * caused the original 405 over-match (see AliasCatchAllReservedPrefixTest).
 *
 * Every token is anchored with a trailing `(?:/|$)` in the compiled regex,
 * so a token only reserves an *exact* segment or a prefixed path and never
 * a same-prefix alias (e.g. `f` reserves `/f/...` but not `/foobar`).
 */
final class ReservedAlias
{
    /**
     * Reserved single segments / path prefixes. Order is irrelevant; keep
     * additions here and both catch-all routes update automatically.
     *
     * @var list<string>
     */
    public const WORDS = [
        'user', 'admin', 'qr', 'storage', 'sanctum', 'api', 'f', 'webhooks',
        'login', 'register', 'features', 'how-it-works', 'about', 'contact',
        'faqs', 'terms', 'refunds', 'privacy', 'gdpr', 'cookies', 'discovery',
        'creators-feed', 'workspace-team', 'buzz', 'ai-chatbot', 'ai-agent',
        'ai-widget', 'ai-voice-assistant', 'whatsapp-agent', 'docs', 'newsletter', 'pricing',
        'coins', 'blogs', 'legal', 'watermark',
        'signed-media', 'stats', 'moderation', 'u', 'p', 'c', 'm',
        'sustainability', 'checkout', 'analytics', 'audience', 'integrations',
        'compare', 'for', 'demos', 'dialer-contacts',
    ];

    /**
     * Build the `where('alias', ...)` regex for a catch-all route.
     *
     * @param string $suffix Trailing pattern after the negative lookahead.
     *                       `[^/]+$` for a single-segment alias page,
     *                       `.*$` for a path like `{alias}/manifest.json`.
     */
    public static function pattern(string $suffix): string
    {
        return '^(?!(?:' . implode('|', self::WORDS) . ')(?:/|$))' . $suffix;
    }
}
