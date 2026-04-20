<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
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

        // Discoverable users that have at least one published biolink.
        $publishedBiolinkUserIds = Link::where('type', 'biolink')
            ->where('is_active', true)
            ->select('user_id')->distinct();

        $query = User::query()
            ->where('discoverable', true)
            ->whereIn('id', $publishedBiolinkUserIds);

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('handle', 'ilike', $like)
                  ->orWhere('bio', 'ilike', $like);
            });
        }

        switch ($sort) {
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'most_followed':
                $query->orderByDesc('followers_count');
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

        $myFollowingIds = [];
        if (auth()->check()) {
            $myFollowingIds = Follow::where('follower_id', auth()->id())
                ->whereIn('creator_id', $creators->pluck('id'))
                ->pluck('creator_id')->all();
        }

        $buzzSnippets = $this->buildBuzzSnippets($creators->pluck('id')->all());

        return view('common.creators-directory', compact('creators', 'q', 'sort', 'myFollowingIds', 'buzzSnippets'));
    }

    /**
     * Batch-load eligible "lightweight social proof" Buzz campaigns for the
     * given creators and return [creator_id => ['icon' => ..., 'text' => ...]].
     */
    private function buildBuzzSnippets(array $creatorIds): array
    {
        if (empty($creatorIds)) return [];

        $allowedTypes = ['recent_activity', 'visitor_count', 'conversion_count', 'social_followers', 'trust_badge'];

        // Single batched query — pull every active campaign for the visible page
        // of creators. We filter to lightweight notification types in PHP since
        // each campaign holds its notifications in a JSON column.
        $proofs = SocialProof::whereIn('user_id', $creatorIds)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'type', 'notifications', 'updated_at']);

        $byUser = [];
        foreach ($proofs as $p) {
            if (isset($byUser[$p->user_id])) continue; // first match wins — deterministic
            $snippet = $this->snippetFromProof($p, $allowedTypes);
            if ($snippet) $byUser[$p->user_id] = $snippet;
        }
        return $byUser;
    }

    private function snippetFromProof(SocialProof $proof, array $allowedTypes): ?array
    {
        $notifications = is_array($proof->notifications) ? $proof->notifications : [];
        // Pick the first active eligible notification (sorted by sort_order).
        $eligible = [];
        foreach ($notifications as $n) {
            if (!is_array($n)) continue;
            if (empty($n['is_active'])) continue;
            $type = $n['type'] ?? null;
            if (!in_array($type, $allowedTypes, true)) continue;
            $eligible[] = $n;
        }
        if (empty($eligible)) return null;
        usort($eligible, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0)));
        $n = $eligible[0];
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
