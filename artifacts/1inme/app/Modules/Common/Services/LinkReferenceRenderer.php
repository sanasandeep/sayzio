<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;

/**
 * Renders `{{link:ID}}` reference tokens — inserted via the inbox reply
 * composer's link picker — into rich, clickable preview cards, while
 * plain-text URLs and ordinary text are escaped/linkified normally.
 *
 * A token only ever resolves against links owned by the account that is
 * sending the reply (the inbox thread's / conversation's owner), so a
 * copy-pasted or tampered token referencing someone else's link silently
 * falls back to plain text instead of leaking another user's link.
 *
 * Shared by:
 *  - the unified inbox thread view + composer (app UI, dark theme)
 *  - outbound reply emails (inline-styled HTML, no CSS vars)
 *  - the viewer-facing / owner-facing Direct Message widgets (compact card)
 *
 * Card links always point at the link's short URL (never the raw
 * destination), so a click is a normal short-link visit and is counted by
 * the existing redirect/click-tracking pipeline — no separate tracking
 * needed.
 */
class LinkReferenceRenderer
{
    public const TOKEN_PATTERN = '/\{\{\s*link:(\d+)\s*\}\}/i';

    /** Wrap a raw link title/reference for insertion into a reply body. */
    public static function token(int $linkId): string
    {
        return '{{link:' . $linkId . '}}';
    }

    /** @return int[] Distinct link ids referenced via tokens in $body. */
    public static function extractLinkIds(string $body): array
    {
        if (!preg_match_all(self::TOKEN_PATTERN, $body, $m)) {
            return [];
        }
        return array_values(array_unique(array_map('intval', $m[1])));
    }

    public static function hasTokens(string $body): bool
    {
        return (bool) preg_match(self::TOKEN_PATTERN, $body);
    }

    /**
     * @return array<int, Link> keyed by link id, scoped to $ownerUserId.
     */
    protected static function resolveLinks(string $body, int $ownerUserId): array
    {
        $ids = self::extractLinkIds($body);
        if (empty($ids)) {
            return [];
        }
        return Link::where('user_id', $ownerUserId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** Split body into alternating text / token segments. */
    protected static function segments(string $body): array
    {
        $parts = preg_split(self::TOKEN_PATTERN, $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        $segments = [];
        foreach ($parts as $i => $part) {
            if ($part === null || $part === '') continue;
            $segments[] = $i % 2 === 1
                ? ['type' => 'link', 'id' => (int) $part]
                : ['type' => 'text', 'value' => $part];
        }
        return $segments;
    }

    /** Escape + auto-linkify plain http(s) URLs in a text segment. */
    protected static function linkifyText(string $text, string $anchorStyle = ''): string
    {
        $escaped = e($text);
        $styleAttr = $anchorStyle !== '' ? ' style="' . $anchorStyle . '"' : '';
        $escaped = preg_replace_callback(
            '/(https?:\/\/[^\s<]+[^\s<.,:;!?\)\]])/i',
            fn ($m) => '<a href="' . $m[1] . '" target="_blank" rel="noopener noreferrer"' . $styleAttr . '>' . $m[1] . '</a>',
            $escaped,
        );
        return nl2br($escaped, false);
    }

    /**
     * Render for the app UI (unified inbox thread view / legacy DM thread).
     * Uses the app's CSS custom properties for theming.
     */
    public static function renderApp(string $body, int $ownerUserId): string
    {
        $links = self::resolveLinks($body, $ownerUserId);
        $html = '';
        foreach (self::segments($body) as $seg) {
            if ($seg['type'] === 'text') {
                $html .= self::linkifyText($seg['value'], 'color: #93c5fd; text-decoration: underline;');
                continue;
            }
            $link = $links[$seg['id']] ?? null;
            if (!$link) {
                // Unresolvable / foreign token — never leak it, drop silently.
                continue;
            }
            $html .= self::appCard($link);
        }
        return $html;
    }

    protected static function appCard(Link $link): string
    {
        $title = e($link->title ?: ($link->alias ?: 'Untitled link'));
        $typeLabel = e($link->type_label);
        $url = e($link->getShortUrl());
        return <<<HTML
<a href="{$url}" target="_blank" rel="noopener noreferrer"
   class="inbox-link-card"
   style="display:flex;align-items:center;gap:10px;margin:6px 0;padding:10px 12px;border-radius:12px;text-decoration:none;background:rgba(92,131,255,0.08);border:1px solid rgba(92,131,255,0.25);max-width:420px;">
    <span style="width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(92,131,255,0.18);color:#93c5fd;">
        <i class="fas fa-link"></i>
    </span>
    <span style="min-width:0;">
        <span style="display:block;font-size:13px;font-weight:600;color:#e5edff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{$title}</span>
        <span style="display:block;font-size:11px;color:#93a4c9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{$typeLabel} &middot; {$url}</span>
    </span>
</a>
HTML;
    }

    /**
     * Render for outbound HTML email. Inline styles only — email clients
     * strip <style> blocks and ignore CSS custom properties.
     */
    public static function renderEmail(string $body, int $ownerUserId): string
    {
        $links = self::resolveLinks($body, $ownerUserId);
        $html = '';
        foreach (self::segments($body) as $seg) {
            if ($seg['type'] === 'text') {
                $html .= self::linkifyText($seg['value'], 'color:#2342c7;');
                continue;
            }
            $link = $links[$seg['id']] ?? null;
            if (!$link) continue;
            $html .= self::emailCard($link);
        }
        return $html;
    }

    protected static function emailCard(Link $link): string
    {
        $title = e($link->title ?: ($link->alias ?: 'Untitled link'));
        $typeLabel = e($link->type_label);
        $url = e($link->getShortUrl());
        return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 0;border-collapse:collapse;border:1px solid #d7deee;border-radius:10px;max-width:420px;">
    <tr>
        <td style="padding:12px 14px;">
            <a href="{$url}" target="_blank" style="text-decoration:none;color:inherit;">
                <div style="font-size:13px;font-weight:600;color:#1f2a44;">{$title}</div>
                <div style="font-size:11px;color:#6b7690;margin-top:2px;">{$typeLabel} &middot; {$url}</div>
            </a>
        </td>
    </tr>
</table>
HTML;
    }

    /**
     * Render for the compact Direct Message widget bubble (owner + viewer
     * facing): escapes text, auto-linkifies plain URLs, and swaps
     * `{{link:ID}}` tokens for a rich card.
     */
    public static function renderDm(string $body, int $ownerUserId): string
    {
        $links = self::resolveLinks($body, $ownerUserId);
        $html = '';
        foreach (self::segments($body) as $seg) {
            if ($seg['type'] === 'text') {
                $html .= self::linkifyText($seg['value'], 'color:#93c5fd;text-decoration:underline;');
                continue;
            }
            $link = $links[$seg['id']] ?? null;
            if (!$link) continue;
            $html .= self::dmCard($link);
        }
        return $html;
    }

    protected static function dmCard(Link $link): string
    {
        $title = e($link->title ?: ($link->alias ?: 'Untitled link'));
        $typeLabel = e($link->type_label);
        $url = e($link->getShortUrl());
        return <<<HTML
<a href="{$url}" target="_blank" rel="noopener noreferrer"
   style="display:flex;align-items:center;gap:8px;margin-top:4px;padding:8px 10px;border-radius:10px;text-decoration:none;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);">
    <i class="fas fa-link" style="opacity:.7;"></i>
    <span style="min-width:0;">
        <span style="display:block;font-size:12px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;">{$title}</span>
        <span style="display:block;font-size:10px;opacity:.7;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;">{$typeLabel}</span>
    </span>
</a>
HTML;
    }

    /** Plain-text fallback (e.g. for character-limited/legacy sinks): replaces tokens with "Title — url". */
    public static function renderPlain(string $body, int $ownerUserId): string
    {
        $links = self::resolveLinks($body, $ownerUserId);
        $out = '';
        foreach (self::segments($body) as $seg) {
            if ($seg['type'] === 'text') {
                $out .= $seg['value'];
                continue;
            }
            $link = $links[$seg['id']] ?? null;
            if (!$link) continue;
            $out .= trim($link->title ?: ($link->alias ?: 'Link')) . ' — ' . $link->getShortUrl();
        }
        return $out;
    }
}
