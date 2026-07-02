<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Follow;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use Illuminate\Support\Collection;

/**
 * Dialer suggestions: pre-query "who do you want to reach?" groups that fill
 * the universal finder's empty state (before the user types anything).
 *
 * Groups returned (each non-empty):
 *   favorites     — speed-dial favorites (via DialerData)
 *   recents       — most-recent call history (via DialerData, top N)
 *   new_followers — accounts that recently started following the user
 *   following     — accounts the user recently started following
 *   new_leads     — newest subscribers + completed form-submission leads
 *
 * The contract ({total, groups[]}) is identical to DialerSearch::universal()
 * so the web and mobile renderers need no changes to display suggestions.
 *
 * All people-type entries (followers / following) go through the same
 * reachability gate as DialerSearch::peopleItems() — suspended/blocked
 * accounts are never surfaced.
 */
class DialerSuggestions
{
    private const LIMIT = 8;

    /**
     * Build and return grouped suggestions for $user.
     *
     * @return array{total:int,groups:array<int,array{key:string,label:string,items:array<int,array<string,mixed>>}>}
     */
    public static function forUser(User $user): array
    {
        $groups = [];
        $groups[] = self::group('favorites', 'Favorites', self::favoritesItems($user));
        $groups[] = self::group('recents', 'Recents', self::recentsItems($user));
        $groups[] = self::group('new_followers', 'New followers', self::newFollowersItems($user));
        $groups[] = self::group('following', 'Following', self::followingItems($user));
        $groups[] = self::group('new_leads', 'New leads', self::newLeadsItems($user));

        $groups = array_values(array_filter($groups, fn ($g) => count($g['items']) > 0));
        $total  = array_sum(array_map(fn ($g) => count($g['items']), $groups));

        return ['total' => $total, 'groups' => $groups];
    }

    // ── Group builders ────────────────────────────────────────────────

    private static function favoritesItems(User $user): array
    {
        $favs = DialerData::favorites($user->id);
        $items = [];
        foreach (array_slice($favs, 0, self::LIMIT) as $f) {
            $number = $f['number'];
            $profileUrl = $number
                ? route('user.dialer.profile', array_filter(['number' => $number, 'contact' => $f['contact_id']], fn ($v) => !is_null($v)))
                : null;
            $items[] = [
                'type'           => 'favorite',
                'category'       => 'favorites',
                'id'             => $f['id'],
                'title'          => (string) ($f['label'] ?: $number ?: '—'),
                'subtitle'       => (string) ($number ?: ''),
                'type_label'     => 'Favorite',
                'initials'       => $f['initials'],
                'badge'          => $f['biolink'] ? 'Sayzio' : null,
                'verified'       => false,
                'verified_label' => null,
                'action'         => [
                    'kind'       => 'profile',
                    'number'     => $number,
                    'contact_id' => $f['contact_id'],
                    'url'        => $profileUrl,
                ],
            ];
        }
        return $items;
    }

    private static function recentsItems(User $user): array
    {
        $recents = DialerData::groupedRecents($user->id, self::LIMIT);
        $items   = [];
        $idx = 0;
        foreach ($recents as $r) {
            $number     = $r['number'] ?: null;
            $contactId  = $r['contact_id'] ?? null;
            $profileUrl = $number
                ? route('user.dialer.profile', array_filter(['number' => $number, 'contact' => $contactId], fn ($v) => !is_null($v)))
                : null;
            $rowId = $contactId ? (int) $contactId : -($idx + 1);
            $idx++;
            $items[] = [
                'type'           => 'recent',
                'category'       => 'recents',
                'id'             => $rowId,
                'title'          => (string) ($r['name'] ?: $number ?: '—'),
                'subtitle'       => (string) ($r['last_human'] ?: ''),
                'type_label'     => 'Recent',
                'initials'       => $r['initials'],
                'badge'          => $r['biolink'] ? 'Sayzio' : null,
                'verified'       => false,
                'verified_label' => null,
                'action'         => [
                    'kind'       => 'profile',
                    'number'     => $number,
                    'contact_id' => $contactId,
                    'url'        => $profileUrl,
                ],
            ];
        }
        return $items;
    }

    /**
     * Accounts that recently started following the authenticated user
     * (ordered newest follow first). Visibility-gated: suspended/blocked
     * accounts are excluded (same gate as DialerSearch::peopleItems).
     */
    private static function newFollowersItems(User $user): array
    {
        $follows = Follow::withoutGlobalScope('workspace')
            ->where('creator_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::LIMIT * 2)
            ->with('follower')
            ->get();

        /** @var Collection<int,User> $users */
        $users = $follows->pluck('follower')->filter()->values();
        return self::usersToItems($user, $users, 'new_followers', 'New follower');
    }

    /**
     * Accounts the authenticated user has recently followed (ordered newest
     * follow first). Visibility-gated as above.
     */
    private static function followingItems(User $user): array
    {
        $follows = Follow::withoutGlobalScope('workspace')
            ->where('follower_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::LIMIT * 2)
            ->with('creator')
            ->get();

        /** @var Collection<int,User> $users */
        $users = $follows->pluck('creator')->filter()->values();
        return self::usersToItems($user, $users, 'following', 'Following');
    }

    /**
     * Newest leads: active subscribers + completed form submissions, merged by
     * created_at (newest first, capped at LIMIT). Only the authenticated user's
     * own records are returned — no cross-account access.
     *
     * Leads are marked with type='lead'; a phone number in the action lets the
     * mobile/web renderer route straight to the dialer profile.
     */
    private static function newLeadsItems(User $user): array
    {
        // Only actionable subscribers: at least one of name/email/phone set
        // (filtered in SQL so dead rows never consume list slots).
        $subs = Subscriber::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) { $q->where('is_spam', false)->orWhereNull('is_spam'); })
            ->where(function ($q) {
                $q->where(fn ($w) => $w->whereNotNull('name')->where('name', '!=', ''))
                    ->orWhere(fn ($w) => $w->whereNotNull('email')->where('email', '!=', ''))
                    ->orWhere(fn ($w) => $w->whereNotNull('phone')->where('phone', '!=', ''));
            })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        // Form-submission contact info lives inside the JSON payload, so
        // over-fetch and drop non-actionable rows BEFORE the merged take()
        // so blank submissions can't starve out older actionable leads.
        $submissions = FormSubmission::withoutGlobalScope('workspace')
            ->whereHas('form', fn ($q) => $q->withoutGlobalScope('workspace')->where('user_id', $user->id))
            ->completed()
            ->where(function ($q) { $q->where('is_spam', false)->orWhereNull('is_spam'); })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT * 4)
            ->get()
            ->map(function ($fs) {
                $data = $fs->data ?? [];
                return [
                    'src'   => 'submission',
                    'model' => $fs,
                    'at'    => $fs->created_at,
                    'name'  => self::extractField($data, ['name', 'full_name', 'your_name', 'your name']),
                    'email' => self::extractField($data, ['email', 'email_address', 'your_email', 'your email']),
                    'phone' => self::extractField($data, ['phone', 'phone_number', 'mobile', 'tel', 'your_phone', 'your phone']),
                ];
            })
            ->filter(fn ($r) => $r['name'] || $r['email'] || $r['phone'])
            ->take(self::LIMIT);

        $combined = collect()
            ->merge($subs->map(fn ($s) => ['src' => 'subscriber', 'model' => $s, 'at' => $s->created_at]))
            ->merge($submissions)
            ->sortByDesc('at')
            ->take(self::LIMIT);

        $items = [];
        foreach ($combined as $row) {
            if ($row['src'] === 'subscriber') {
                $s = $row['model'];
                if (! $s->name && ! $s->email && ! $s->phone) {
                    continue;
                }
                $name = $s->name ?: $s->email ?: $s->phone ?: 'Subscriber';
                $leadPhone      = $s->phone ?: null;
                $leadProfileUrl = $leadPhone
                    ? route('user.dialer.profile', ['number' => $leadPhone])
                    : null;
                $items[] = [
                    'type'           => 'lead',
                    'category'       => 'new_leads',
                    'id'             => $s->id,
                    'title'          => (string) $name,
                    'subtitle'       => (string) ($s->email ?: $s->phone ?: ''),
                    'type_label'     => 'Subscriber',
                    'initials'       => self::initials((string) $name),
                    'badge'          => null,
                    'verified'       => false,
                    'verified_label' => null,
                    'action'         => [
                        'kind'       => $leadPhone ? 'profile' : 'none',
                        'number'     => $leadPhone,
                        'contact_id' => null,
                        'url'        => $leadProfileUrl,
                    ],
                ];
            } else {
                $fs    = $row['model'];
                $name  = $row['name'];
                $email = $row['email'];
                $phone = $row['phone'];
                if (! $name && ! $email && ! $phone) {
                    continue;
                }
                $display = $name ?: $email ?: $phone ?: 'Form lead';
                $formPhone      = $phone ?: null;
                $formProfileUrl = $formPhone
                    ? route('user.dialer.profile', ['number' => $formPhone])
                    : null;
                $items[] = [
                    'type'           => 'lead',
                    'category'       => 'new_leads',
                    'id'             => $fs->id,
                    'title'          => (string) $display,
                    'subtitle'       => (string) ($email ?: $phone ?: ''),
                    'type_label'     => 'Form lead',
                    'initials'       => self::initials((string) $display),
                    'badge'          => null,
                    'verified'       => false,
                    'verified_label' => null,
                    'action'         => [
                        'kind'       => $formPhone ? 'profile' : 'none',
                        'number'     => $formPhone,
                        'contact_id' => null,
                        'url'        => $formProfileUrl,
                    ],
                ];
            }
        }

        return $items;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Apply the same suspended/blocked reachability gate used by
     * DialerSearch::peopleItems() to a batch of User models, then transform
     * each to a normalized suggestion item.
     */
    private static function usersToItems(User $viewer, Collection $users, string $category, string $typeLabel): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $ids = $users->pluck('id')->all();

        $blockedByIds = UserBlock::where('blocked_user_id', $viewer->id)
            ->whereIn('blocker_user_id', $ids)
            ->pluck('blocker_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $reachable = User::whereIn('id', $ids)
            ->where(function ($w) use ($viewer) {
                $w->where('status', 'active')
                  ->orWhereNull('status')
                  ->orWhere('id', $viewer->id);
            })
            ->when(!empty($blockedByIds), fn ($q) => $q->whereNotIn('id', $blockedByIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        return $users
            ->filter(fn ($u) => isset($reachable[$u->id]))
            ->take(self::LIMIT)
            ->map(function (User $u) use ($viewer, $category, $typeLabel) {
                $bio = $u->primaryBiolink();
                return [
                    'type'           => 'person',
                    'category'       => $category,
                    'id'             => $u->id,
                    'title'          => $u->name ?: $u->publicHandle(),
                    'subtitle'       => '@' . $u->publicHandle(),
                    'type_label'     => $typeLabel,
                    'initials'       => $u->getInitials(),
                    'badge'          => (int) $u->id === (int) $viewer->id ? 'You' : null,
                    'verified'       => false,
                    'verified_label' => null,
                    'action'         => [
                        'kind'    => 'person',
                        'url'     => $bio ? url('/' . $bio->alias) : null,
                        'handle'  => $u->publicHandle(),
                        'user_id' => $u->id,
                    ],
                ];
            })->values()->all();
    }

    /**
     * Build two-letter initials from a display name.
     */
    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        $out = mb_strtoupper($a . $b);
        return $out !== '' ? $out : '?';
    }

    /**
     * Scan a form-submission data array for a field whose key contains one of
     * the given needle strings (case-insensitive). Returns the first non-empty
     * string value found, or null.
     *
     * @param array<string,mixed> $data
     * @param array<int,string>   $needles
     */
    private static function extractField(array $data, array $needles): ?string
    {
        foreach ($data as $k => $v) {
            $kl = mb_strtolower((string) $k);
            foreach ($needles as $needle) {
                if ($kl === $needle || str_contains($kl, $needle)) {
                    $str = is_string($v) ? trim($v) : '';
                    if ($str !== '') {
                        return $str;
                    }
                }
            }
        }
        return null;
    }

    private static function group(string $key, string $label, array $items): array
    {
        return ['key' => $key, 'label' => $label, 'items' => $items];
    }
}
