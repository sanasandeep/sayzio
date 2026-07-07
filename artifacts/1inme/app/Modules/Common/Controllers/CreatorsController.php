<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\ViewerDmUserBlock;
use App\Modules\Common\Services\AgeGate;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorsController extends Controller
{
    /**
     * Cache key for the default directory page every anonymous visitor
     * hits (no search/tag/tier, trending sort, page 1, no adult flags).
     * Stores PLAIN ATTRIBUTE ARRAYS rehydrated on read — never serialized
     * Eloquent models, which the file cache turns into
     * __PHP_Incomplete_Class. With production DB_PERSISTENT=false each
     * query costs a ~3s SSL reconnect, so the warm path must run zero
     * queries. 5-minute TTL keeps the directory reasonably fresh.
     */
    public const DEFAULT_CACHE_KEY = 'creators:index:default:v1';

    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $sort = $request->query('sort', 'trending');
        // Discovery filters (Task #1211).
        // - tag: pick a niche tag to filter the directory.
        // - tier: 'free' | 'paid' restricts by whether the creator has at
        //         least one active free or paid subscription tier.
        $tag  = trim((string) $request->query('tag', ''));
        $tier = $request->query('tier', '');

        // Adult-content directory filter (Task #1208).
        //   - Default: hide 18+ profiles entirely.
        //   - `show_adult=1` opt-in surfaces them, but only when the
        //     visitor has either passed the per-device age gate cookie
        //     OR is signed in with their own 18+ affirmation.
        //   - `only_adult=1` is a stricter view used by adult-content
        //     creators looking for peers (still respects the gate).
        $viewer = ViewerSession::user() ?? auth()->user();
        $ageGated = AgeGate::passed($request, $viewer);
        $showAdult = $ageGated && (string) $request->query('show_adult', '0') === '1';
        $onlyAdult = $ageGated && (string) $request->query('only_adult', '0') === '1';

        // Discoverable users that have at least one published biolink.
        $publishedBiolinkUserIds = Link::whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->select('user_id')->distinct();

        $query = User::query()
            ->where('discoverable', true)
            ->whereIn('id', $publishedBiolinkUserIds);

        if ($onlyAdult) {
            $query->where('adult_content_enabled', true)
                  ->whereNull('adult_flag_suspended_at');
        } elseif (!$showAdult) {
            // Hide adult-flagged profiles. We treat suspended-flag rows
            // as visible (the moderator decision lifts the public 18+
            // tag) so they don't fall off the directory entirely.
            $query->where(function ($w) {
                $w->where('adult_content_enabled', false)
                  ->orWhereNotNull('adult_flag_suspended_at');
            });
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like, $q) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('handle', 'ilike', $like)
                  ->orWhere('bio', 'ilike', $like)
                  // Niche tag JSON contains match — searching for "music"
                  // also surfaces creators tagged with #music.
                  ->orWhereJsonContains('niche_tags', mb_strtolower($q));
            });
        }

        // Niche tag pill filter (Task #1211). Stored on users.niche_tags
        // as a JSON array — Postgres + MySQL both index it via the JSON
        // contains operator.
        if ($tag !== '') {
            $query->whereJsonContains('niche_tags', mb_strtolower($tag));
        }

        // Free vs paid filter — joined to subscription_tiers so the row
        // has at least one active free / paid tier respectively.
        if ($tier === 'free') {
            $query->whereExists(function ($w) {
                $w->select(DB::raw(1))
                  ->from('subscription_tiers')
                  ->whereColumn('subscription_tiers.user_id', 'users.id')
                  ->where('is_active', true)->where('is_free', true);
            });
        } elseif ($tier === 'paid') {
            $query->whereExists(function ($w) {
                $w->select(DB::raw(1))
                  ->from('subscription_tiers')
                  ->whereColumn('subscription_tiers.user_id', 'users.id')
                  ->where('is_active', true)->where('is_free', false);
            });
        }

        switch ($sort) {
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'most_followed':
                $query->orderByDesc('followers_count');
                break;
            case 'most_active':
                // Posts published in the last 7 days.
                $activeSub = DB::table('creator_posts')
                    ->select('user_id', DB::raw('COUNT(*) as posts'))
                    ->whereNotNull('published_at')
                    ->where('published_at', '>=', now()->subDays(7))
                    ->groupBy('user_id');
                $query->leftJoinSub($activeSub, 'a', 'a.user_id', '=', 'users.id')
                      ->orderByDesc(DB::raw('COALESCE(a.posts, 0)'))
                      ->orderByDesc('users.followers_count')
                      ->select('users.*');
                break;
            case 'trending':
            default:
                // Trending = followers gained in the last 7 days.
                $trendingSub = DB::table('follows')
                    ->select('creator_id', DB::raw('COUNT(*) as gained'))
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('creator_id');
                $query->leftJoinSub($trendingSub, 't', 't.creator_id', '=', 'users.id')
                      ->orderByDesc(DB::raw('COALESCE(t.gained, 0)'))
                      ->orderByDesc('users.followers_count')
                      ->select('users.*');
                break;
        }

        // Trending carousel (Task #1211): up to 8 highest-velocity
        // creators. Cached for 5 minutes to keep the directory fast.
        $trendingCarousel = $this->trendingCarousel($publishedBiolinkUserIds, $showAdult, $onlyAdult);

        // Tag cloud — top 24 niche tags by frequency, shown as pill
        // filters above the grid.
        $popularTags = $this->popularTags(24);

        // Warm cached path for the default view (covers ~all public
        // traffic). Signed-in visitors reuse the same cached payload —
        // the directory contents are identical; only the tiny
        // viewer-specific overlays (which creators I follow, which cards
        // I can message) are computed live below, each a single cheap
        // indexed query. Everything filter-specific falls through to
        // live queries below.
        $isDefaultView = $q === '' && $tag === '' && $tier === ''
            && ($sort === 'trending' || $sort === null || $sort === '')
            && !$showAdult && !$onlyAdult
            && (int) $request->query('page', 1) <= 1;

        $payload = null;
        if ($isDefaultView) {
            try {
                $payload = \Illuminate\Support\Facades\Cache::remember(
                    self::DEFAULT_CACHE_KEY,
                    300,
                    fn () => $this->buildDefaultIndexPayload()
                );
            } catch (\Throwable $e) {
                $payload = null;
            }
        }

        if ($payload !== null) {
            $creators = new \Illuminate\Pagination\LengthAwarePaginator(
                User::hydrate($payload['creators']),
                (int) $payload['total'],
                24,
                1,
                ['path' => route('creators.index'), 'pageName' => 'page']
            );
            $buzzSnippets = $payload['buzz'];
            $primaryBiolinks = $payload['primary'];

            // Viewer-specific overlays on the shared cached payload —
            // each a single cheap indexed query, never the multi-second
            // directory rebuild.
            $myFollowingIds = [];
            $messageableBiolinks = $payload['messageable'];
            if ($viewer || auth()->check()) {
                $creatorIds = array_map(
                    fn (array $attrs) => (int) $attrs['id'],
                    $payload['creators']
                );
                if (auth()->check()) {
                    $myFollowingIds = Follow::where('follower_id', auth()->id())
                        ->whereIn('creator_id', $creatorIds)
                        ->pluck('creator_id')->all();
                }
                $messageableBiolinks = $this->filterMessageableForViewer(
                    $messageableBiolinks,
                    $viewer
                );
            }
        } else {
            $creators = $query->paginate(24)->withQueryString();

            $myFollowingIds = [];
            if (auth()->check()) {
                $myFollowingIds = Follow::where('follower_id', auth()->id())
                    ->whereIn('creator_id', $creators->pluck('id'))
                    ->pluck('creator_id')->all();
            }

            // One batched query replaces the old per-creator
            // primaryBiolink() lookups (blade cards + DM eligibility +
            // visitor-count privacy all reuse it).
            $primary = $this->primaryBiolinks($creators->getCollection());
            $buzzSnippets = $this->buildBuzzSnippets($creators->pluck('id')->all(), $primary);
            $messageableBiolinks = $this->buildMessageableBiolinks($primary, $viewer);
            $primaryBiolinks = collect($primary)->map(fn (array $b) => [
                'id' => $b['id'], 'alias' => $b['alias'],
            ])->all();
        }

        return view('common.creators-directory', compact(
            'creators', 'q', 'sort', 'tag', 'tier', 'myFollowingIds', 'buzzSnippets', 'messageableBiolinks',
            'showAdult', 'onlyAdult', 'ageGated', 'trendingCarousel', 'popularTags', 'primaryBiolinks'
        ));
    }

    /**
     * Build the cacheable payload for the default anonymous directory view
     * (no search/tag/tier, trending sort, page 1, no adult flags) as plain
     * attribute arrays. Reconstructs the exact query index() builds under
     * those defaults, so the request path and the scheduled marketing-cache
     * warmer (\App\Modules\Common\Support\MarketingPageCache) always cache
     * the same payload.
     */
    public function buildDefaultIndexPayload(): array
    {
        $publishedBiolinkUserIds = Link::whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->select('user_id')->distinct();

        // Trending = followers gained in the last 7 days (default sort).
        $trendingSub = DB::table('follows')
            ->select('creator_id', DB::raw('COUNT(*) as gained'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('creator_id');

        $query = User::query()
            ->where('discoverable', true)
            ->whereIn('id', $publishedBiolinkUserIds)
            // Default view hides adult-flagged profiles (suspended-flag rows
            // stay visible — the moderator decision lifts the public 18+ tag).
            ->where(function ($w) {
                $w->where('adult_content_enabled', false)
                  ->orWhereNotNull('adult_flag_suspended_at');
            })
            ->leftJoinSub($trendingSub, 't', 't.creator_id', '=', 'users.id')
            ->orderByDesc(DB::raw('COALESCE(t.gained, 0)'))
            ->orderByDesc('users.followers_count')
            ->select('users.*');

        $paginator = $query->paginate(24);
        $models = $paginator->getCollection();
        $primary = $this->primaryBiolinks($models);

        return [
            'creators'    => $models->map(fn (User $u) => $u->getAttributes())->all(),
            'total'       => $paginator->total(),
            'buzz'        => $this->buildBuzzSnippets($models->pluck('id')->all(), $primary),
            'messageable' => $this->buildMessageableBiolinks($primary),
            // Blade only needs the alias for card hrefs.
            'primary'     => collect($primary)->map(fn (array $b) => [
                'id' => $b['id'], 'alias' => $b['alias'],
            ])->all(),
        ];
    }

    /**
     * One batched lookup of each creator's "primary" biolink (their
     * most-clicked active biolink-family link — same semantics as
     * User::primaryBiolink()). Returns
     * [user_id => ['id' =>, 'alias' =>, 'settings' =>]].
     */
    private function primaryBiolinks($creators): array
    {
        $ids = collect($creators)->pluck('id')->map(fn ($i) => (int) $i)->all();
        if (empty($ids)) return [];

        $rows = Link::whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->whereIn('user_id', $ids)
            ->orderByDesc('total_clicks')
            ->get(['id', 'user_id', 'alias', 'settings']);

        $out = [];
        foreach ($rows as $l) {
            $uid = (int) $l->user_id;
            if (isset($out[$uid])) continue;
            $out[$uid] = [
                'id'       => (int) $l->id,
                'alias'    => (string) $l->alias,
                'settings' => $l->settings,
            ];
        }
        return $out;
    }

    /**
     * Build the small "Trending now" carousel above the grid (Task #1211).
     * Top 8 creators by 7-day follower gain. Cached for 5 minutes.
     */
    protected function trendingCarousel($publishedBiolinkUserIds, bool $showAdult, bool $onlyAdult): array
    {
        // v2: the v1 key cached serialized User models, which the file
        // cache deserializes as __PHP_Incomplete_Class (500s the page).
        // We now cache PLAIN ATTRIBUTE ARRAYS and rehydrate on read; the
        // joined `gained` column survives as a plain attribute.
        $cacheKey = self::trendingCarouselCacheKey($showAdult, $onlyAdult);
        try {
            $rows = \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                300,
                fn () => $this->buildTrendingCarouselRows($showAdult, $onlyAdult)
            );
        } catch (\Throwable $e) {
            $rows = [];
        }

        return User::hydrate(is_array($rows) ? $rows : [])->all();
    }

    /**
     * Cache key for the trending-carousel variant. v2: the v1 key cached
     * serialized User models (__PHP_Incomplete_Class on the file cache).
     * v3: variants are consolidated to the three query shapes that
     * actually exist — when `only_adult` is set the query ignores
     * `show_adult`, so (1,1) and (0,1) collapse to one 'only' key. All
     * three variants are pre-built by the scheduled marketing-cache
     * warmer so signed-in / age-gated visitors never cold-rebuild over
     * the cross-region RDS.
     */
    public static function trendingCarouselCacheKey(bool $showAdult, bool $onlyAdult): string
    {
        $variant = $onlyAdult ? 'only' : ($showAdult ? 'adult' : 'default');

        return 'creators:trending:v3:' . $variant;
    }

    /**
     * The (showAdult, onlyAdult) argument pairs covering every distinct
     * trending-carousel variant — used by the scheduled warmer.
     *
     * @return array<string,array{0:bool,1:bool}>
     */
    public static function trendingCarouselVariants(): array
    {
        return [
            'default' => [false, false],
            'adult'   => [true, false],
            'only'    => [false, true],
        ];
    }

    /**
     * Build the trending-carousel rows (top 8 creators by 7-day follower
     * gain) as plain attribute arrays. Shared by the request path and the
     * scheduled marketing-cache warmer.
     */
    public function buildTrendingCarouselRows(bool $showAdult, bool $onlyAdult): array
    {
        $publishedBiolinkUserIds = Link::whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->select('user_id')->distinct();

        $sub = DB::table('follows')
            ->select('creator_id', DB::raw('COUNT(*) as gained'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('creator_id');

        $q = User::query()
            ->where('discoverable', true)
            ->whereIn('id', $publishedBiolinkUserIds)
            ->joinSub($sub, 't', 't.creator_id', '=', 'users.id')
            ->orderByDesc('t.gained')
            ->select('users.*', 't.gained');
        if ($onlyAdult) {
            $q->where('adult_content_enabled', true)->whereNull('adult_flag_suspended_at');
        } elseif (!$showAdult) {
            $q->where(function ($w) {
                $w->where('adult_content_enabled', false)->orWhereNotNull('adult_flag_suspended_at');
            });
        }
        return $q->limit(8)->get()->map(fn (User $u) => $u->getAttributes())->all();
    }

    /**
     * Top niche tags from the directory pool. Aggregated in PHP because
     * niche_tags is JSON and DBs vary in their array unnest support.
     */
    protected function popularTags(int $limit = 24): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            self::POPULAR_TAGS_CACHE_KEY,
            600,
            fn () => $this->buildPopularTagCounts($limit)
        );
    }

    /** Cache key for the directory's popular niche-tag cloud. */
    public const POPULAR_TAGS_CACHE_KEY = 'creators:popular_tags';

    /**
     * Build the popular niche-tag counts from the DB. Shared by the
     * request path ({@see popularTags()}) and the scheduled marketing-cache
     * warmer.
     */
    public function buildPopularTagCounts(int $limit = 24): array
    {
        $rows = User::query()
            ->where('discoverable', true)
            ->whereNotNull('niche_tags')
            ->limit(2000)
            ->pluck('niche_tags')
            ->all();
        $counts = [];
        foreach ($rows as $tags) {
            if (!is_array($tags)) continue;
            foreach ($tags as $t) {
                $t = mb_strtolower(trim((string) $t));
                if ($t === '') continue;
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /**
     * Suggest similar creators for the public profile (Task #1211).
     * Ranks candidates by shared niche tags first, then by follower
     * count, excluding the creator themselves and any creator the
     * viewer has blocked.
     */
    public static function relatedCreators(User $creator, ?User $viewer = null, int $limit = 6): \Illuminate\Support\Collection
    {
        $tags = is_array($creator->niche_tags) ? array_values(array_filter($creator->niche_tags)) : [];
        $blocked = $viewer ? \App\Modules\User\Models\UserBlock::blockedIdsFor($viewer->id) : [];

        $q = User::query()
            ->where('discoverable', true)
            ->where('profile_published', true)
            ->where('id', '!=', $creator->id)
            ->whereNotNull('handle')
            ->whereIn('id', Link::whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->where('is_active', true)->select('user_id'));
        if (!empty($blocked)) $q->whereNotIn('id', $blocked);

        if (!empty($tags)) {
            $q->where(function ($w) use ($tags) {
                foreach ($tags as $t) {
                    $w->orWhereJsonContains('niche_tags', mb_strtolower($t));
                }
            });
        }
        return $q->orderByDesc('followers_count')->limit($limit * 2)->get()->take($limit);
    }

    /**
     * Returns [creator_id => link_id] for creators whose default biolink
     * has the Direct Message block enabled and is reachable for the
     * current viewer (not account-blocked). Used to decide which cards
     * show the "Message" button on the directory.
     */
    private function buildMessageableBiolinks(array $primaryBiolinks, $viewer = null): array
    {
        $primaryBiolinkIds = []; // creator_id => link_id
        foreach ($primaryBiolinks as $creatorId => $bio) {
            $primaryBiolinkIds[(int) $creatorId] = (int) $bio['id'];
        }
        if (empty($primaryBiolinkIds)) return [];

        $dmEnabledLinkIds = BiolinkBlock::whereIn('link_id', array_values($primaryBiolinkIds))
            ->where('type', 'direct_message')
            ->where('is_active', true)
            ->pluck('link_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $blockedOwnerIds = [];
        $viewer = $viewer ?? ViewerSession::user();
        if ($viewer) {
            $blockedOwnerIds = ViewerDmUserBlock::where('viewer_user_id', $viewer->id)
                ->whereIn('owner_user_id', array_keys($primaryBiolinkIds))
                ->pluck('owner_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $out = [];
        foreach ($primaryBiolinkIds as $creatorId => $linkId) {
            if (!in_array($linkId, $dmEnabledLinkIds, true)) continue;
            if (in_array($creatorId, $blockedOwnerIds, true)) continue;
            // Cannot message yourself.
            if ($viewer && (int) $viewer->id === (int) $creatorId) continue;
            $out[$creatorId] = $linkId;
        }
        return $out;
    }

    /**
     * Apply the viewer-specific exclusions to the cached anonymous
     * messageable map (creator_id => link_id). A signed-in viewer only
     * ever REMOVES entries from the anonymous set: creators they've
     * DM-blocked and themselves. One cheap indexed query.
     */
    private function filterMessageableForViewer(array $messageable, $viewer): array
    {
        if (!$viewer || empty($messageable)) return $messageable;

        $blockedOwnerIds = ViewerDmUserBlock::where('viewer_user_id', $viewer->id)
            ->whereIn('owner_user_id', array_keys($messageable))
            ->pluck('owner_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $out = [];
        foreach ($messageable as $creatorId => $linkId) {
            if ((int) $viewer->id === (int) $creatorId) continue;
            if (in_array((int) $creatorId, $blockedOwnerIds, true)) continue;
            $out[$creatorId] = $linkId;
        }
        return $out;
    }

    /**
     * Batch-load eligible "lightweight social proof" Buzz campaigns for the
     * given creators and return [creator_id => ['icon' => ..., 'text' => ...]].
     */
    private function buildBuzzSnippets(array $creatorIds, array $primaryBiolinks = []): array
    {
        if (empty($creatorIds)) return [];

        $allowedTypes = SocialProof::DIRECTORY_BADGE_TYPES;

        // Per-creator visitor-count gating (task #1180): the public Creators
        // directory surfaces buzz snippets — including live "X people are
        // viewing this page" badges — for every creator, bypassing the
        // per-biolink `hide_public_visitor_counts` privacy toggle. Batch-load
        // each creator's primary biolink privacy flag and, when hidden,
        // strip the live-counter notification types from their allowed list
        // so the auto-picker falls through to a non-leaky badge (or none).
        // Mirrors the same data_get path used in social-proof.blade.php.
        $hiddenCreatorIds = $this->creatorsHidingVisitorCounts($creatorIds, $primaryBiolinks);
        $liveCounterTypes = ['visitor_count', 'conversion_count'];
        $allowedTypesPerCreator = [];
        foreach ($creatorIds as $cid) {
            $allowedTypesPerCreator[(int) $cid] = in_array((int) $cid, $hiddenCreatorIds, true)
                ? array_values(array_diff($allowedTypes, $liveCounterTypes))
                : $allowedTypes;
        }

        // Single batched query — pull every active campaign for the visible page
        // of creators. We filter to lightweight notification types in PHP since
        // each campaign holds its notifications in a JSON column.
        $proofs = SocialProof::whereIn('user_id', $creatorIds)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'type', 'notifications', 'directory_badge_notification_id', 'updated_at']);

        // First pass: honour explicit creator picks. A campaign with
        // `directory_badge_notification_id` set wins over the auto-pick for
        // that user, regardless of which campaign was most recently updated.
        $byUser = [];
        foreach ($proofs as $p) {
            if (isset($byUser[$p->user_id])) continue;
            if (empty($p->directory_badge_notification_id)) continue;
            $allowed = $allowedTypesPerCreator[(int) $p->user_id] ?? $allowedTypes;
            $snippet = $this->snippetFromProof($p, $allowed, $p->directory_badge_notification_id);
            if ($snippet) $byUser[$p->user_id] = $snippet;
        }

        // Second pass: legacy auto-pick (most recently updated active campaign,
        // first eligible notification) for any creator without an explicit pick.
        foreach ($proofs as $p) {
            if (isset($byUser[$p->user_id])) continue;
            $allowed = $allowedTypesPerCreator[(int) $p->user_id] ?? $allowedTypes;
            $snippet = $this->snippetFromProof($p, $allowed);
            if ($snippet) $byUser[$p->user_id] = $snippet;
        }
        return $byUser;
    }

    /**
     * Return the subset of $creatorIds whose primary biolink has the
     * `biolink.privacy.hide_public_visitor_counts` flag enabled (or is
     * unset, since the privacy-first default is "hide"). Used to gate
     * live-visitor signals on public surfaces. Mirrors the data_get
     * path used in resources/views/common/blocks/social-proof.blade.php.
     */
    private function creatorsHidingVisitorCounts(array $creatorIds, array $primaryBiolinks = []): array
    {
        if (empty($creatorIds)) return [];

        // Reuse the already-batched primary-biolink rows (their settings
        // came along in the same query) instead of re-querying.
        $hidden = [];
        foreach ($creatorIds as $cid) {
            $cid = (int) $cid;
            $bio = $primaryBiolinks[$cid] ?? null;
            if (!$bio) {
                // No active biolink → privacy-first default: hide.
                $hidden[] = $cid;
                continue;
            }
            $explicit = data_get($bio['settings'] ?? null, 'biolink.privacy.hide_public_visitor_counts', null);
            $isHidden = $explicit === null ? true : (bool) $explicit;
            if ($isHidden) $hidden[] = $cid;
        }
        return $hidden;
    }

    private function snippetFromProof(SocialProof $proof, array $allowedTypes, ?string $preferredId = null): ?array
    {
        $notifications = is_array($proof->notifications) ? $proof->notifications : [];
        // Pick the first active eligible notification (sorted by sort_order),
        // unless a specific id is preferred — in which case it must exist,
        // be active, and be of an eligible type, otherwise we bail (so the
        // outer fallback can try a different campaign).
        $eligible = [];
        foreach ($notifications as $n) {
            if (!is_array($n)) continue;
            if (empty($n['is_active'])) continue;
            $type = $n['type'] ?? null;
            if (!in_array($type, $allowedTypes, true)) continue;
            $eligible[] = $n;
        }
        if (empty($eligible)) return null;

        if ($preferredId !== null && $preferredId !== '') {
            $picked = null;
            foreach ($eligible as $n) {
                if (($n['id'] ?? null) === $preferredId) { $picked = $n; break; }
            }
            if (!$picked) return null;
            $n = $picked;
        } else {
            usort($eligible, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0)));
            $n = $eligible[0];
        }
        $s = is_array($n['settings'] ?? null) ? $n['settings'] : [];

        return match ($n['type']) {
            'visitor_count' => (function () use ($s) {
                $min = max(0, (int)($s['min'] ?? 5));
                $max = max($min, (int)($s['max'] ?? $min + 10));
                $count = $max === $min ? $min : $min + ((int) floor(time() / 30) % ($max - $min + 1));
                $text = (string)($s['text'] ?? '{count} people are viewing this page');
                return ['icon' => 'fa-eye', 'text' => str_replace('{count}', (string)$count, $text)];
            })(),
            'conversion_count' => [
                'icon' => 'fa-bolt',
                'text' => str_replace('{count}', (string)(int)($s['count'] ?? 0), (string)($s['text'] ?? '{count} recent conversions')),
            ],
            'social_followers' => [
                'icon' => 'fa-user-plus',
                'text' => number_format((int)($s['count'] ?? 0)) . ' ' . trim((string)($s['handle'] ?? ucfirst((string)($s['network'] ?? 'social')))) . ' followers',
            ],
            'trust_badge' => [
                'icon' => 'fa-star',
                'text' => rtrim(number_format((float)($s['rating'] ?? 0), 1) . ' ★ · ' . number_format((int)($s['reviews'] ?? 0)) . ' reviews ' . trim((string)($s['label'] ?? '')), ' '),
            ],
            'recent_activity' => (function () use ($s) {
                $tpl = (string)($s['title_template'] ?? 'Recent activity');
                $text = strtr($tpl, ['{name}' => 'Someone', '{location}' => 'nearby', '{action}' => 'joined']);
                return ['icon' => 'fa-bell', 'text' => $text !== '' ? $text : 'Recent activity'];
            })(),
            default => null,
        };
    }
}
