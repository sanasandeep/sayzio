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
        $publishedBiolinkUserIds = Link::where('type', 'biolink')
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

        $creators = $query->paginate(24)->withQueryString();

        // Trending carousel (Task #1211): up to 8 highest-velocity
        // creators. Cached for 5 minutes to keep the directory fast.
        $trendingCarousel = $this->trendingCarousel($publishedBiolinkUserIds, $showAdult, $onlyAdult);

        // Tag cloud — top 24 niche tags by frequency, shown as pill
        // filters above the grid.
        $popularTags = $this->popularTags(24);

        $myFollowingIds = [];
        if (auth()->check()) {
            $myFollowingIds = Follow::where('follower_id', auth()->id())
                ->whereIn('creator_id', $creators->pluck('id'))
                ->pluck('creator_id')->all();
        }

        $buzzSnippets = $this->buildBuzzSnippets($creators->pluck('id')->all());
        $messageableBiolinks = $this->buildMessageableBiolinks($creators);

        return view('common.creators-directory', compact(
            'creators', 'q', 'sort', 'tag', 'tier', 'myFollowingIds', 'buzzSnippets', 'messageableBiolinks',
            'showAdult', 'onlyAdult', 'ageGated', 'trendingCarousel', 'popularTags'
        ));
    }

    /**
     * Build the small "Trending now" carousel above the grid (Task #1211).
     * Top 8 creators by 7-day follower gain. Cached for 5 minutes.
     */
    protected function trendingCarousel($publishedBiolinkUserIds, bool $showAdult, bool $onlyAdult): array
    {
        $cacheKey = "creators:trending:" . ($showAdult ? '1' : '0') . ':' . ($onlyAdult ? '1' : '0');
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($publishedBiolinkUserIds, $showAdult, $onlyAdult) {
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
            return $q->limit(8)->get()->all();
        });
    }

    /**
     * Top niche tags from the directory pool. Aggregated in PHP because
     * niche_tags is JSON and DBs vary in their array unnest support.
     */
    protected function popularTags(int $limit = 24): array
    {
        return \Illuminate\Support\Facades\Cache::remember('creators:popular_tags', 600, function () use ($limit) {
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
        });
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
            ->whereIn('id', Link::where('type', 'biolink')->where('is_active', true)->select('user_id'));
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
    private function buildMessageableBiolinks($creators): array
    {
        $primaryBiolinkIds = []; // creator_id => link_id
        foreach ($creators as $c) {
            $bio = $c->primaryBiolink();
            if ($bio) {
                $primaryBiolinkIds[(int) $c->id] = (int) $bio->id;
            }
        }
        if (empty($primaryBiolinkIds)) return [];

        $dmEnabledLinkIds = BiolinkBlock::whereIn('link_id', array_values($primaryBiolinkIds))
            ->where('type', 'direct_message')
            ->where('is_active', true)
            ->pluck('link_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $blockedOwnerIds = [];
        $viewer = ViewerSession::user();
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
     * Batch-load eligible "lightweight social proof" Buzz campaigns for the
     * given creators and return [creator_id => ['icon' => ..., 'text' => ...]].
     */
    private function buildBuzzSnippets(array $creatorIds): array
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
        $hiddenCreatorIds = $this->creatorsHidingVisitorCounts($creatorIds);
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
    private function creatorsHidingVisitorCounts(array $creatorIds): array
    {
        if (empty($creatorIds)) return [];

        // Pick the same "primary biolink" each creator's directory snippet
        // is implicitly tied to: their most-clicked active biolink.
        $primary = Link::where('type', 'biolink')
            ->where('is_active', true)
            ->whereIn('user_id', $creatorIds)
            ->orderByDesc('total_clicks')
            ->get(['user_id', 'settings'])
            ->groupBy('user_id');

        $hidden = [];
        foreach ($creatorIds as $cid) {
            $cid = (int) $cid;
            $bios = $primary->get($cid);
            if (!$bios || $bios->isEmpty()) {
                // No active biolink → privacy-first default: hide.
                $hidden[] = $cid;
                continue;
            }
            $bio = $bios->first();
            $explicit = data_get($bio->settings, 'biolink.privacy.hide_public_visitor_counts', null);
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
