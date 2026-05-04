<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-block analytics drill-down aggregator.
 *
 * Builds the payload that powers the block drill-down panel on both the
 * web biolink analytics page (modal) and the mobile analytics screen
 * (expanded section). Centralised here so the web `User\LinkController`
 * and the mobile `Api\LinkController` return the exact same shape.
 *
 * Payload includes:
 *   - by_day:          per-day clicks within [from, to]
 *   - by_referrer:     top referrer hosts
 *   - by_device:       mobile/desktop/tablet split
 *   - by_visitor_type: anonymous / registered / follower / subscriber
 *
 * Visitor-type buckets are mutually exclusive in priority order:
 *   subscriber > follower > registered > anonymous.
 *
 * "Subscriber" matches via subscribers.email = users.email, since the
 * `subscribers` table doesn't store viewer_user_id directly but the
 * email is unique per creator.
 */
class BlockAnalyticsAggregator
{
    /**
     * Run all aggregations for one block.
     *
     * @return array{
     *   block: array{id:int, type:?string, title:?string, destination_url:?string, is_active:bool},
     *   window: array{from:string, to:string},
     *   total_clicks: int,
     *   unique_clicks: int,
     *   by_day: array<int,array{day:string, clicks:int}>,
     *   by_referrer: array<int,array{referrer_host:?string, clicks:int}>,
     *   by_device: array<int,array{device_type:?string, clicks:int}>,
     *   by_visitor_type: array<int,array{visitor_type:string, clicks:int}>,
     * }
     */
    public static function aggregate(Link $link, int $blockId, Carbon $from, Carbon $to): array
    {
        $block = BiolinkBlock::where('link_id', $link->id)->findOrFail($blockId);

        // --- Build mutually exclusive viewer ID buckets ---
        $followerIds = Schema::hasTable('follows')
            ? DB::table('follows')->where('creator_id', $link->user_id)->pluck('follower_id')->all()
            : [];

        // Subscribers are stored with the creator's user_id + the
        // subscriber's email; resolve the viewer user_ids by matching
        // email back to the users table. Cheaper than a per-row join.
        $subscriberIds = [];
        if (Schema::hasTable('subscribers')) {
            $subscriberIds = DB::table('subscribers')
                ->join('users', 'users.email', '=', 'subscribers.email')
                ->where('subscribers.user_id', $link->user_id)
                ->whereIn('subscribers.status', ['active', 'subscribed'])
                ->pluck('users.id')
                ->all();
        }
        // Mutually exclusive bucket priority: subscriber > follower >
        // registered > anonymous. Subscriber is the "warmest" cohort
        // (paying / opted-in) so a viewer who is BOTH a subscriber and a
        // follower must be counted as a subscriber, not a follower.
        $subscriberSet = array_values(array_unique(array_map('intval', $subscriberIds)));
        $followerOnly  = array_values(array_diff(
            array_unique(array_map('intval', $followerIds)),
            $subscriberSet
        ));

        $base = DB::table('link_clicks')
            ->where('link_id', $link->id)
            ->where('block_id', $blockId)
            ->where('is_bot', false)
            ->whereBetween('clicked_at', [$from, $to]);

        $totalClicks  = (clone $base)->count();
        $uniqueClicks = (clone $base)->distinct('ip_address')->count('ip_address');

        // by_day — fill missing days with zero so the chart line is continuous.
        $rawDaily = (clone $base)
            ->selectRaw("TO_CHAR(DATE_TRUNC('day', clicked_at), 'YYYY-MM-DD') as day, COUNT(*) as clicks")
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');

        $byDay = [];
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();
        // Cap series length so an absurdly wide range can't blow up the response.
        $maxDays = 366;
        $i = 0;
        while ($cursor->lte($end) && $i < $maxDays) {
            $key = $cursor->toDateString();
            $byDay[] = [
                'day'    => $key,
                'clicks' => (int) ($rawDaily[$key]->clicks ?? 0),
            ];
            $cursor->addDay();
            $i++;
        }

        $byReferrer = (clone $base)
            ->selectRaw('referrer_host, COUNT(*) as clicks')
            ->groupBy('referrer_host')
            ->orderByDesc('clicks')
            ->limit(15)
            ->get()
            ->map(fn ($r) => ['referrer_host' => $r->referrer_host, 'clicks' => (int) $r->clicks])
            ->all();

        $byDevice = (clone $base)
            ->selectRaw('device_type, COUNT(*) as clicks')
            ->groupBy('device_type')
            ->orderByDesc('clicks')
            ->get()
            ->map(fn ($r) => ['device_type' => $r->device_type, 'clicks' => (int) $r->clicks])
            ->all();

        // Visitor-type bucket counts (mutually exclusive,
        // priority subscriber > follower > registered > anonymous).
        $anonymous = (clone $base)->whereNull('viewer_user_id')->count();
        $subscribers = empty($subscriberSet) ? 0
            : (clone $base)->whereIn('viewer_user_id', $subscriberSet)->count();
        $followers = empty($followerOnly) ? 0
            : (clone $base)->whereIn('viewer_user_id', $followerOnly)->count();
        $identified = (clone $base)->whereNotNull('viewer_user_id')->count();
        $registered = max(0, $identified - $subscribers - $followers);

        $byVisitorType = [
            ['visitor_type' => 'anonymous',  'clicks' => (int) $anonymous],
            ['visitor_type' => 'registered', 'clicks' => (int) $registered],
            ['visitor_type' => 'follower',   'clicks' => (int) $followers],
            ['visitor_type' => 'subscriber', 'clicks' => (int) $subscribers],
        ];

        return [
            'block'           => self::blockMeta($block, $blockId, $link),
            'window'          => [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ],
            'total_clicks'    => (int) $totalClicks,
            'unique_clicks'   => (int) $uniqueClicks,
            'by_day'          => $byDay,
            'by_referrer'     => $byReferrer,
            'by_device'       => $byDevice,
            'by_visitor_type' => $byVisitorType,
        ];
    }

    /**
     * Lightweight per-block summary used to render the clickable list of
     * blocks in the analytics view (mobile + web). Returned alongside the
     * existing analytics payload so the client can populate the drill-down
     * entry points without an extra round trip.
     *
     * @return array<int,array{block_id:int, type:?string, title:?string, destination_url:?string, clicks:int, unique_clicks:int}>
     */
    public static function blockSummary(Link $link, Carbon $from, Carbon $to): array
    {
        // Start from the actual block inventory so EVERY block on the
        // biolink shows up as a drill-down entry — even ones with zero
        // clicks in the selected window. The drill-down requirement is
        // "every block clickable", not "every clicked block clickable".
        $blocks = BiolinkBlock::where('link_id', $link->id)
            ->orderBy('sort_order')
            ->get();

        if ($blocks->isEmpty()) return [];

        $clickRows = DB::table('link_clicks')
            ->where('link_id', $link->id)
            ->where('is_bot', false)
            ->whereIn('block_id', $blocks->pluck('id')->all())
            ->whereBetween('clicked_at', [$from, $to])
            ->selectRaw('block_id, COUNT(*) as clicks, COUNT(DISTINCT ip_address) as unique_clicks')
            ->groupBy('block_id')
            ->get()
            ->keyBy('block_id');

        return $blocks
            ->map(function ($blk) use ($clickRows, $link) {
                $r = $clickRows->get($blk->id);
                $meta = self::blockMeta($blk, (int) $blk->id, $link);
                return [
                    'block_id'        => (int) $blk->id,
                    'type'            => $meta['type'],
                    'title'           => $meta['title'],
                    'destination_url' => $meta['destination_url'],
                    'clicks'          => (int) ($r->clicks ?? 0),
                    'unique_clicks'   => (int) ($r->unique_clicks ?? 0),
                ];
            })
            ->sortByDesc('clicks')
            ->values()
            ->all();
    }

    /**
     * Best-effort title/url for a block — drills into a few well-known
     * settings keys (title, heading, text, label, url, link, …) so we can
     * show a recognisable label without dragging in the full web blockMeta
     * helper. Falls back to the block type label.
     */
    private static function blockMeta(?BiolinkBlock $blk, int $blockId, Link $link, ?string $fallbackType = null): array
    {
        if (!$blk) {
            $type = $fallbackType ?: null;
            $label = $type && isset(BiolinkBlock::TYPES[$type]['label'])
                ? BiolinkBlock::TYPES[$type]['label']
                : ($type ? ucfirst(str_replace('_', ' ', $type)) : 'Removed block');
            return [
                'id'              => $blockId,
                'type'            => $type,
                'title'           => $label,
                'destination_url' => null,
                'is_active'       => false,
            ];
        }

        $s = is_array($blk->settings) ? $blk->settings : (json_decode((string) ($blk->settings ?? '{}'), true) ?: []);
        $titleKeys = ['title', 'heading', 'text', 'label', 'name', 'caption', 'question', 'button_text', 'description'];
        $urlKeys   = ['url', 'link', 'destination_url', 'href', 'embed_url', 'video_url'];

        $find = function (array $keys) use ($s) {
            $walk = function ($data, int $depth = 0) use (&$walk, $keys) {
                if ($depth > 3 || !is_array($data)) return null;
                foreach ($keys as $k) {
                    if (!empty($data[$k]) && is_string($data[$k])) {
                        $v = trim(strip_tags($data[$k]));
                        if ($v !== '') return $v;
                    }
                }
                foreach ($data as $v) {
                    if (is_array($v)) {
                        $r = $walk($v, $depth + 1);
                        if ($r) return $r;
                    }
                }
                return null;
            };
            return $walk($s);
        };

        $title = $find($titleKeys);
        if ($title) {
            $title = mb_substr($title, 0, 80);
        } else {
            $typeLabel = BiolinkBlock::TYPES[$blk->type]['label'] ?? ucfirst(str_replace('_', ' ', (string) $blk->type));
            $title = $typeLabel;
        }

        return [
            'id'              => $blk->id,
            'type'            => $blk->type,
            'title'           => $title,
            'destination_url' => $find($urlKeys),
            'is_active'       => (bool) $blk->is_active,
        ];
    }
}
