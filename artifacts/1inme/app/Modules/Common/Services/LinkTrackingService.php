<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Services\BotDetector;
use App\Modules\Common\Services\VisitorRateLimiter;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Modules\Common\Services\ChannelClassifier;
use App\Modules\Common\Support\PendingClick;
use Illuminate\Support\Str;

class LinkTrackingService
{
    public function track(Link $link, Request $request, ?string $usedAlias = null, ?string $source = null): ?PendingClick
    {
        $userAgent = $this->resolveUserAgent($request);
        $detector = app(BotDetector::class);
        $isBot = $detector->isBot($userAgent);

        // Creators can choose to drop specific bot families (e.g.
        // "GPTBot (OpenAI)", "AhrefsBot") from being recorded at all.
        // We classify the UA once and bail BEFORE persisting so the row
        // never lands in link_clicks — these bots become invisible to
        // every downstream surface (totals, breakdowns, exports, badges).
        if ($isBot) {
            $blockedFamilies = $this->blockedFamiliesForLink($link);
            if (!empty($blockedFamilies)
                && in_array($detector->classifyFamily($userAgent), $blockedFamilies, true)) {
                return null;
            }
        }

        // Per-biolink rate limiting (per-IP and per-fingerprint sliding
        // windows). Throttled rows are still recorded with both is_bot
        // and is_throttled set so they appear in the "Blocked X bot
        // attempts this week" badge but never in human totals.
        $isThrottled = false;
        if (!$isBot) {
            $isThrottled = app(VisitorRateLimiter::class)
                ->shouldThrottle($link, $request, $userAgent);
            if ($isThrottled) {
                $isBot = true;
            }
        }

        // Per-biolink privacy: when the owner has flipped off referrer
        // logging, we drop the Referer header before persistence. Storing
        // null (rather than the actual URL) means the value never lands
        // in analytics, exports, or webhook payloads downstream.
        $logReferrer = $this->shouldLogReferrer($link);

        // Hot-path ends here. Everything above MUST run synchronously because it
        // gates the redirect (blocked-family bots leave no trace; throttled hits
        // are flagged). The slow/contended work — geo lookup, the link_clicks
        // insert, the hot-row counter writes, uniqueness detection, and event
        // dispatch — is deferred to PersistLinkClicksJob via the request-scoped
        // ClickWriteBuffer, which flushes once after the visitor's redirect
        // response has already been sent (app terminating callback). Bots are
        // still recorded (so the "blocked attempts" badge works) but the job
        // excludes them from human counters, preserving the old semantics.
        return app(ClickWriteBuffer::class)->push([
            'event_id' => (string) Str::uuid(),
            'link_id' => $link->id,
            'alias' => $usedAlias ?: $link->alias,
            'viewer_user_id' => \App\Modules\Common\Services\ViewerSession::id() ?? auth()->id(),
            'ip_address' => $request->ip(),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOS($userAgent),
            'device_type' => $this->detectDeviceType($userAgent),
            'referrer' => $logReferrer ? $request->header('referer') : null,
            'source' => $source,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'channel' => ChannelClassifier::classify($userAgent),
            'is_bot' => $isBot,
            'is_throttled' => $isThrottled,
            'language' => $this->detectLanguage($request),
            'utm_params' => $this->extractUtmParams($request),
            'clicked_at' => now()->toDateTimeString(),
            'block_counter_pending' => false,
        ]);
    }

    /**
     * Per-biolink privacy: when the owner has flipped off referrer logging,
     * we drop the Referer header before persisting any LinkClick row. The
     * default (no privacy block, or the flag not set) preserves the
     * historical behaviour of recording the header.
     */
    protected function shouldLogReferrer(Link $link): bool
    {
        // Privacy controls (task #1114) live under
        // settings.biolink.privacy and only apply to biolink-type links.
        // Short links and other types keep their historical behaviour of
        // recording the Referer header.
        if (!$link->isBiolinkFamily()) {
            return true;
        }
        $explicit = data_get($link->settings, 'biolink.privacy.disable_referrer_logging', null);
        // Privacy-first default for biolinks: when the owner hasn't
        // saved the Privacy panel yet, drop the Referer header so a
        // brand-new biolink doesn't quietly log it before review.
        if ($explicit === null) {
            return false;
        }
        return !((bool) $explicit);
    }

    protected function detectBrowser(?string $ua): ?string
    {
        if (!$ua) return null;

        $browsers = [
            'Edge' => '/Edg\//i',
            'Chrome' => '/Chrome\//i',
            'Firefox' => '/Firefox\//i',
            'Safari' => '/Safari\//i',
            'Opera' => '/OPR\//i',
            'IE' => '/MSIE|Trident/i',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $ua)) return $name;
        }

        return 'Other';
    }

    protected function detectOS(?string $ua): ?string
    {
        if (!$ua) return null;

        $systems = [
            'Windows' => '/Windows/i',
            'macOS' => '/Macintosh/i',
            'Linux' => '/Linux/i',
            'Android' => '/Android/i',
            'iOS' => '/iPhone|iPad/i',
        ];

        foreach ($systems as $name => $pattern) {
            if (preg_match($pattern, $ua)) return $name;
        }

        return 'Other';
    }

    protected function detectDeviceType(?string $ua): ?string
    {
        if (!$ua) return null;

        if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) return 'mobile';
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) return 'tablet';

        return 'desktop';
    }

    protected function detectLanguage(Request $request): ?string
    {
        $lang = $request->header('Accept-Language');
        if (!$lang) return null;

        return substr($lang, 0, 2);
    }

    public function trackBlockClick(Link $link, BiolinkBlock $block, string $destinationUrl, Request $request, ?string $usedAlias = null, ?string $source = null, ?string $blockItemId = null): ?PendingClick
    {
        $userAgent = $this->resolveUserAgent($request);
        $detector = app(BotDetector::class);
        $isBot = $detector->isBot($userAgent);

        // Honour the link owner's blocked-family list before doing any
        // work — same contract as track(): blocked bots leave no trace.
        if ($isBot) {
            $blockedFamilies = $this->blockedFamiliesForLink($link);
            if (!empty($blockedFamilies)
                && in_array($detector->classifyFamily($userAgent), $blockedFamilies, true)) {
                return null;
            }
        }

        // Per-biolink rate limiting also applies to in-page block taps
        // so a flood-clicker can't mass-tap a single CTA either.
        $isThrottled = false;
        if (!$isBot) {
            $isThrottled = app(VisitorRateLimiter::class)
                ->shouldThrottle($link, $request, $userAgent);
            if ($isThrottled) {
                $isBot = true;
            }
        }

        // Task #1094 — enforce per-block scarcity *atomically* before we
        // create any analytics record or commit ourselves to a redirect.
        // Bots are exempt (they never count toward the cap, so they
        // shouldn't be able to exhaust it either) but real visitors must
        // win a single conditional UPDATE before we proceed.
        if (!$isBot) {
            // Time-based expiry: cheap, deterministic, non-racy once past.
            if ($block->end_date && $block->end_date->isPast()) {
                return null;
            }

            // Click-cap reservation: a single SQL statement that only
            // succeeds when click_count is still below max_clicks. If
            // two requests race at click_count = max_clicks - 1, exactly
            // one UPDATE will affect a row; the other gets 0 and is
            // refused, so the cap can never be overshot regardless of
            // isolation level or worker concurrency.
            if ($block->max_clicks !== null && $block->max_clicks > 0) {
                $affected = BiolinkBlock::where('id', $block->id)
                    ->whereColumn('click_count', '<', 'max_clicks')
                    ->update(['click_count' => DB::raw('click_count + 1')]);
                if ($affected === 0) {
                    return null;
                }
                $reserved = true;
            } else {
                $reserved = false;
            }
        } else {
            $reserved = false;
        }

        // Hot-path ends here. The atomic cap reservation above already bumped
        // click_count synchronously for capped blocks that won the race (it has
        // to, to enforce scarcity before we commit to the redirect). Everything
        // else — geo lookup, the link_clicks insert, link counters, uniqueness,
        // the uncapped per-block counter bump, and event dispatch — is deferred
        // to PersistLinkClicksJob via the request-scoped ClickWriteBuffer.
        //
        // block_counter_pending tells the job to bump click_count itself, but
        // ONLY for real (non-bot) uncapped blocks: capped blocks were already
        // bumped by the reservation above, and bots never count toward a block.
        return app(ClickWriteBuffer::class)->push([
            'event_id' => (string) Str::uuid(),
            'link_id' => $link->id,
            'alias' => $usedAlias ?: $link->alias,
            'viewer_user_id' => \App\Modules\Common\Services\ViewerSession::id() ?? auth()->id(),
            'block_id' => $block->id,
            'block_type' => $block->type,
            'block_item_id' => $blockItemId !== null ? substr($blockItemId, 0, 32) : null,
            'destination_url' => substr($destinationUrl, 0, 2048),
            'ip_address' => $request->ip(),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOS($userAgent),
            'device_type' => $this->detectDeviceType($userAgent),
            'referrer' => $this->shouldLogReferrer($link) ? $request->header('referer') : null,
            'source' => $source,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'channel' => ChannelClassifier::classify($userAgent),
            'is_bot' => $isBot,
            'is_throttled' => $isThrottled,
            'language' => $this->detectLanguage($request),
            'utm_params' => $this->extractUtmParams($request),
            'clicked_at' => now()->toDateTimeString(),
            'block_counter_pending' => (!$isBot && !$reserved),
        ]);
    }

    /**
     * Fetch the link owner's blocked bot families. We pull a single
     * column directly via the query builder (avoiding the full User
     * hydration) because this runs on every redirect. Returns an empty
     * array when the column is null/missing — the caller short-circuits
     * the classify+match work in that case so the common path stays cheap.
     *
     * @return array<int, string>
     */
    protected function blockedFamiliesForLink(Link $link): array
    {
        if ($link->user_id === null) {
            return [];
        }

        $raw = DB::table('users')->where('id', (int) $link->user_id)->value('blocked_bot_families');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        }
        if (is_array($raw)) {
            return array_values(array_filter($raw, 'is_string'));
        }
        return [];
    }

    protected function resolveUserAgent(Request $request): ?string
    {
        $ua = $request->userAgent();
        if ($ua) return $ua;

        $client = $request->header('X-1INME-Client');
        return $client ?: null;
    }

    protected function extractUtmParams(Request $request): ?array
    {
        $params = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            if ($val = $request->query($key)) {
                $params[$key] = $val;
            }
        }

        return empty($params) ? null : $params;
    }
}
