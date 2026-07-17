<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\ContactWorkspaceShare;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Universal Dialer finder. Extends the old contacts+numbers search into a
 * single grouped search across everything the Dialer user can reach:
 *
 *   - Contacts   : the user's own address book (names / orgs / phones + T9)
 *   - People     : Sayzio accounts the user owns (self) or follows, plus
 *                  accounts linked from their contacts — by display name/handle
 *   - My links   : the user's own links/biolinks — alias/back-half + SEO meta
 *                  (title / description / keywords) + verification badge status
 *   - Followed   : links/biolinks of accounts the user follows, honoring the
 *                  same visibility gating as the public renderer
 *   - Workspaces : workspaces the user can access — by name
 *
 * The contract (grouped, normalized items) is the single source of truth shared
 * by the web dialer, the REST API and the mobile app so the three surfaces can
 * never drift. T9 smart-dial is preserved (a digit sequence still matches both
 * phone numbers and keypad-spelled names / aliases).
 *
 * Task #3497 (contact privacy): every group here is intentionally built from
 * name/handle/alias/title metadata only — it never echoes a stranger's raw
 * phone number, email, exact location or socials. The Contacts group only
 * ever surfaces the *searcher's own* saved contact data (always visible to
 * them by definition), and the People group's `subtitle` is just `@handle`.
 * There is nothing here for {@see ContactPrivacy} to strip. The moment any
 * group starts rendering a candidate's phone/email/location/socials, it MUST
 * route that candidate through `ContactPrivacy::applyToPayload()` first
 * (mirroring {@see DialerIdentity::payload()}) unless the viewer is looking
 * themself up or the candidate is already in the viewer's own contact book.
 */
class DialerSearch
{
    /** Per-group hard cap so a broad query can never balloon the payload. */
    private const GROUP_LIMIT = 12;

    /** Absolute ceiling for $perGroup to prevent runaway payloads. */
    private const MAX_PER_GROUP = 60;

    /**
     * Run the universal search and return the grouped result contract.
     *
     * Pagination is page-based (0-indexed). Passing $page=1 with $perGroup=12
     * skips the first 12 results per group and returns the next 12. Each group
     * in the response carries a `has_more` flag so the caller knows whether a
     * subsequent page exists without fetching it.
     *
     * @param array{filter?:string,tag?:string,scope?:string} $filters
     * @param int $page     0-indexed page (default 0 = first page)
     * @param int $perGroup results per group per page (default GROUP_LIMIT)
     * @return array{q:string,filter:?string,total:int,page:int,per_group:int,groups:array<int,array{key:string,label:string,has_more:bool,items:array<int,array<string,mixed>>}>}
     */
    public static function universal(User $user, string $q, array $filters = [], int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        $page     = max(0, $page);
        $perGroup = max(1, min($perGroup, self::MAX_PER_GROUP));

        $q = trim($q);
        $filter = isset($filters['filter']) ? (string) $filters['filter'] : null;
        $onlyVerified = $filter === 'verified';

        // A "verified" keyword search (with no other terms) means: show me
        // everything that carries a verification badge, so treat it as a
        // verified-only filter over an empty term.
        if ($q !== '' && $filter === null && in_array(mb_strtolower($q), ['verified', 'verified badge', 'badge'], true)) {
            $onlyVerified = true;
            $q = '';
        }

        $groups = [];

        if ($q !== '' || $onlyVerified) {
            $groups[] = self::groupWithPage('contacts', 'Contacts', self::contactItems($user, $q, $filters, $onlyVerified, $page, $perGroup), $page, $perGroup);
            $groups[] = self::groupWithPage('people', 'People', self::peopleItems($user, $q, $onlyVerified, $page, $perGroup), $page, $perGroup);
            $groups[] = self::groupWithPage('social', 'Social', self::socialItems($user, $q, $onlyVerified, $page, $perGroup), $page, $perGroup);
            $groups[] = self::groupWithPage('my_links', 'My links', self::myLinkItems($user, $q, $onlyVerified, $page, $perGroup), $page, $perGroup);
            $groups[] = self::groupWithPage('followed', 'Followed', self::followedLinkItems($user, $q, $onlyVerified, $page, $perGroup), $page, $perGroup);
            $groups[] = self::groupWithPage('workspaces', 'Workspaces', self::workspaceItems($user, $q, $onlyVerified, $page, $perGroup), $page, $perGroup);
            $groups[] = self::groupWithPage('events', 'Events', self::eventItems($user, $q, $filters, $onlyVerified, $page, $perGroup), $page, $perGroup);
        }

        // Keep a group when it has items on this page OR more results remain
        // (has_more), so paginating clients never lose a group mid-stream.
        $groups = array_values(array_filter($groups, fn ($g) => count($g['items']) > 0 || $g['has_more']));
        $total = array_sum(array_map(fn ($g) => count($g['items']), $groups));

        return [
            'q'         => $q,
            'filter'    => $onlyVerified ? 'verified' : null,
            'total'     => $total,
            'page'      => $page,
            'per_group' => $perGroup,
            'groups'    => $groups,
        ];
    }

    /**
     * Advanced contacts search: matches many more contact fields than the
     * keypad quick-search and supports filter chips. Returns Contact models
     * (with phones + biolinkUser eager loaded) for callers that render the
     * full contact row.
     *
     * @param array{tag?:string,has_biolink?:bool,has_email?:bool,has_phone?:bool} $filters
     * @return Collection<int,Contact>
     */
    public static function contactsAdvanced(int $userId, string $q, array $filters = [], ?int $forWorkspaceId = null): Collection
    {
        $q = trim($q);
        $needle = '%' . $q . '%';
        $phoneNeedle = '%' . ContactPhone::normalize($q) . '%';

        // The Dialer "Contacts" group is an account-wide address-book finder,
        // mirroring the other dialer groups (My links / People / Followed /
        // Subscribers) which all resolve account-level records. `Contact` uses
        // BelongsToWorkspace, so on the web surface (under `workspace.scope`)
        // the global scope would narrow this to the searcher's ACTIVE workspace
        // only — hiding contacts they saved while a different workspace was
        // bound. The Sanctum/mobile surface binds no workspace and already
        // returns the searcher's full address book. Opt out of the workspace
        // scope so the web dialer matches API/mobile; the user_id predicate
        // still scopes this to the searcher, so it never widens WHO is visible.
        //
        // When $forWorkspaceId is provided (team workspace is active and the
        // searcher is a member), also surface contacts that other members of
        // that workspace have explicitly shared, so team members can reach
        // shared contacts from the dialer just like their own.
        $query = Contact::withoutGlobalScope('workspace')
            ->where(function ($w) use ($userId, $forWorkspaceId) {
                $w->where('user_id', $userId);
                if ($forWorkspaceId) {
                    $w->orWhereHas('workspaceShares', fn ($sq) =>
                        $sq->where('workspace_id', $forWorkspaceId)
                    );
                }
            })
            ->with(['phones', 'emails', 'biolinkUser']);

        if ($q !== '') {
            // T9 smart-dial (keypad-spelled names) is folded into the same SQL
            // WHERE, so a digit query no longer loads up to 300 extra contacts
            // into PHP to filter with DialerT9::matches().
            $seq = DialerT9::isDigitSequence($q) ? (string) preg_replace('/\D+/', '', $q) : '';
            $query->where(function ($w) use ($needle, $phoneNeedle, $seq) {
                $w->where('display_name', 'ilike', $needle)
                  ->orWhere('given_name', 'ilike', $needle)
                  ->orWhere('family_name', 'ilike', $needle)
                  ->orWhere('organization', 'ilike', $needle)
                  ->orWhere('job_title', 'ilike', $needle)
                  ->orWhere('website', 'ilike', $needle)
                  ->orWhere('notes', 'ilike', $needle)
                  ->orWhereRaw('tags::text ilike ?', [$needle])
                  ->orWhereRaw('socials::text ilike ?', [$needle])
                  ->orWhereHas('emails', fn ($q2) => $q2->where('value', 'ilike', $needle))
                  ->orWhereHas('phones', function ($q2) use ($needle, $phoneNeedle) {
                      $q2->where('value', 'ilike', $needle)
                         ->orWhere('value_e164', 'ilike', $phoneNeedle);
                  });
                if (strlen($seq) >= 2) {
                    $w->orWhereRaw(DialerT9::sqlEncode(DialerT9::CONTACT_NAME_SQL) . ' LIKE ?', ['%' . $seq . '%']);
                }
            });
        }

        if (!empty($filters['has_biolink'])) {
            $query->whereNotNull('biolink_user_id');
        }
        if (!empty($filters['has_email'])) {
            $query->whereHas('emails');
        }
        if (!empty($filters['has_phone'])) {
            $query->whereHas('phones');
        }
        if (!empty($filters['tag'])) {
            $query->whereRaw('tags::text ilike ?', ['%' . $filters['tag'] . '%']);
        }

        $rows = $query->orderBy('display_name')->limit(100)->get();

        return $rows;
    }

    // ── Category builders ────────────────────────────────────────────────

    /**
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    private static function contactItems(User $user, string $q, array $filters, bool $onlyVerified, int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        // Contacts carry no verification badge, so a verified-only filter with
        // no search term yields nothing here.
        if ($onlyVerified && $q === '') {
            return ['items' => [], 'has_more' => false];
        }

        $advFilters = [
            'tag'         => $filters['tag'] ?? null,
            'has_biolink' => !empty($filters['has_biolink']),
        ];

        // When a non-personal team workspace is active and the searcher is a
        // member, extend the search to contacts shared with that workspace by
        // other members. The workspace scope is intentionally bypassed via
        // withoutGlobalScope (see contactsAdvanced), so we detect the bound
        // workspace from the container instead of relying on the global scope.
        $wsId = null;
        if (app()->bound('current_workspace')) {
            $ws = app('current_workspace');
            // Any member of a non-personal team workspace can see shared
            // contacts in the dialer — the workspace binding already implies
            // membership; personal workspaces only have one member so
            // workspace-sharing is meaningless there.
            if ($ws && !$ws->is_personal) {
                $wsId = (int) $ws->id;
            }
        }

        $rows = self::contactsAdvanced($user->id, $q, $advFilters, $wsId);

        $slice = $rows->skip($page * $perGroup)->take($perGroup + 1);
        $has_more = $slice->count() > $perGroup;
        $items = $slice->take($perGroup)->map(function (Contact $c) {
            $first = $c->phones->first();
            $e164 = $first?->value_e164 ?: $first?->value;
            $isBiolink = (bool) $c->biolink_user_id;

            $action = $first
                ? ['kind' => 'profile', 'url' => route('user.dialer.profile', ['number' => $e164, 'contact' => $c->id]), 'number' => $e164, 'contact_id' => $c->id]
                : ['kind' => 'contact', 'url' => route('user.contacts.show', $c), 'number' => null, 'contact_id' => $c->id];

            return [
                'type'           => 'contact',
                'category'       => 'contacts',
                'id'             => $c->id,
                'title'          => $c->nameForDisplay(),
                'subtitle'      => $first?->value ?? ($c->organization ?: ''),
                'type_label'     => 'Contact',
                'initials'       => $c->initials(),
                'badge'          => $isBiolink ? 'Sayzio' : null,
                'verified'       => false,
                'verified_label' => null,
                'action'         => $action,
            ];
        })->values()->all();

        return ['items' => $items, 'has_more' => $has_more];
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    private static function peopleItems(User $user, string $q, bool $onlyVerified, int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        // Reachable people = self + accounts the user follows + accounts linked
        // from the user's own contacts (owned + followed only — never a global
        // user directory). Even within that set, never surface an account the
        // searcher can't reach right now (suspended/deactivated, or blocking).
        $ids = self::reachableUserIds($user);

        if ($ids->isEmpty()) {
            return ['items' => [], 'has_more' => false];
        }

        $query = User::whereIn('id', $ids);
        if ($q !== '') {
            $needle = '%' . $q . '%';
            // T9 smart-dial: a keypad digit sequence resolves keypad-spelled
            // names. Matched entirely in SQL (against the T9 encoding of the
            // name) so the work never scales with the size of the reachable
            // set — no more loading up to 200 candidate users into PHP and
            // looping with DialerT9::matches() on every keystroke.
            $seq = DialerT9::isDigitSequence($q) ? (string) preg_replace('/\D+/', '', $q) : '';
            $query->where(function ($w) use ($needle, $seq) {
                $w->where('name', 'ilike', $needle)
                  ->orWhere('handle', 'ilike', $needle);
                if (strlen($seq) >= 2) {
                    $w->orWhereRaw(DialerT9::sqlEncode('name') . ' LIKE ?', ['%' . $seq . '%']);
                }
            });
        }
        $fetchLimit = min(($page + 1) * $perGroup * 2 + 2, 200);
        $candidates = $query->orderBy('name')->limit($fetchLimit)->get();

        // Which of these accounts carry a verification badge (a verified link)?
        // A person's verified link may live in ANY of their workspaces. On the
        // web surface the `workspace.scope` middleware binds the searcher's
        // active workspace and the BelongsToWorkspace global scope would narrow
        // this to the active workspace only — so a person whose only verified
        // link is in a non-active workspace would wrongly show as UNverified
        // (and be excluded by the `verified` filter chip), while the
        // API/Sanctum surface (no workspace binding) shows the badge correctly.
        // Opt out of the workspace filter so web matches API/mobile; the
        // user_id predicate still scopes this to the candidate accounts.
        $verifiedIds = $candidates->isEmpty() ? collect() : Link::withoutGlobalScope('workspace')
            ->whereIn('user_id', $candidates->pluck('id'))
            ->where('is_verified', true)
            ->pluck('user_id')->unique();

        if ($onlyVerified) {
            $candidates = $candidates->filter(fn ($u) => $verifiedIds->contains($u->id))->values();
        }

        $slice = $candidates->skip($page * $perGroup)->take($perGroup + 1);
        $has_more = $slice->count() > $perGroup;
        $items = $slice->take($perGroup)->map(function (User $u) use ($user, $verifiedIds) {
            $bio = $u->primaryBiolink();
            $isVerified = $verifiedIds->contains($u->id);
            $isSelf = $u->id === $user->id;

            return [
                'type'           => 'person',
                'category'       => 'people',
                'id'             => $u->id,
                'title'          => $u->name ?: $u->publicHandle(),
                'subtitle'       => '@' . $u->publicHandle(),
                'type_label'     => $isSelf ? 'You' : 'Person',
                'initials'       => $u->getInitials(),
                'badge'          => $isSelf ? 'You' : null,
                'verified'       => $isVerified,
                'verified_label' => $isVerified ? 'Verified' : null,
                'action'         => [
                    'kind'    => 'person',
                    'url'     => $bio ? url('/' . $bio->alias) : null,
                    'handle'  => $u->publicHandle(),
                    'user_id' => $u->id,
                ],
            ];
        })->values()->all();

        return ['items' => $items, 'has_more' => $has_more];
    }

    /**
     * Task #3588: connected social accounts that a reachable person (self,
     * followed, or contact-linked) has explicitly opted into "Searchable in
     * public". Mirrors the reachability gate in {@see peopleItems()} — the
     * shared {@see reachableUserIds()} helper keeps the two from drifting.
     *
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    private static function socialItems(User $user, string $q, bool $onlyVerified, int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        if ($q === '') {
            return ['items' => [], 'has_more' => false];
        }

        $ids = self::reachableUserIds($user);
        if ($ids->isEmpty()) {
            return ['items' => [], 'has_more' => false];
        }

        $needle = '%' . $q . '%';
        $fetchLimit = min(($page + 1) * $perGroup * 2 + 2, 200);
        $rows = SocialAccountConnection::whereIn('user_id', $ids)
            ->searchable()
            ->where(function ($w) use ($needle, $q) {
                $w->where('handle', 'ilike', $needle)
                  ->orWhere('display_name', 'ilike', $needle)
                  ->orWhere('platform', 'ilike', $needle);
                if (isset(SocialAccountConnection::PLATFORM_META[strtolower($q)])) {
                    $w->orWhere('platform', strtolower($q));
                }
            })
            ->with('user')
            ->orderBy('platform')
            ->limit($fetchLimit)
            ->get();

        if ($onlyVerified) {
            $verifiedIds = Link::withoutGlobalScope('workspace')
                ->whereIn('user_id', $rows->pluck('user_id')->unique())
                ->where('is_verified', true)
                ->pluck('user_id')->unique();
            $rows = $rows->filter(fn ($c) => $verifiedIds->contains($c->user_id));
        }

        $slice = $rows->skip($page * $perGroup)->take($perGroup + 1);
        $has_more = $slice->count() > $perGroup;
        $items = $slice->take($perGroup)->map(function (SocialAccountConnection $c) use ($user) {
            $owner = $c->user;
            $isSelf = $owner && $owner->id === $user->id;

            return [
                'type'           => 'social',
                'category'       => 'social',
                'id'             => $c->id,
                'title'          => '@' . $c->handle,
                'subtitle'       => SocialAccountConnection::platformLabel($c->platform) . ($owner ? ' · ' . ($owner->name ?: $owner->publicHandle()) : ''),
                'type_label'     => SocialAccountConnection::platformLabel($c->platform),
                'initials'       => self::initialsOf($c->handle),
                'badge'          => $isSelf ? 'You' : null,
                'verified'       => false,
                'verified_label' => null,
                'action'         => [
                    'kind'     => 'social',
                    'url'      => $c->resolvedProfileUrl() ?: null,
                    'handle'   => $c->handle,
                    'platform' => $c->platform,
                    'user_id'  => $c->user_id,
                ],
            ];
        })->values()->all();

        return ['items' => $items, 'has_more' => $has_more];
    }

    /**
     * Shared reachable-user-id set (self + followed + contact-linked, minus
     * suspended/deactivated/blocking accounts). Extracted from
     * {@see peopleItems()} so the Social group can't drift from it.
     *
     * @return Collection<int,int>
     */
    private static function reachableUserIds(User $user): Collection
    {
        // Follows and contacts are account-level relationships, not
        // workspace-scoped. Both models use BelongsToWorkspace, so on the web
        // surface (which runs under `workspace.scope`) their global scope would
        // narrow these ID-set queries to the searcher's ACTIVE workspace only —
        // dropping follows/contacts created in another workspace and leaving the
        // reachable set (People / Social groups) silently empty. The Sanctum/
        // mobile surface binds no workspace and returns them all; opt out of the
        // workspace scope here so web matches API/mobile. The follower_id/user_id
        // predicates still scope this to the searcher, so it never widens reach.
        $ids = collect([$user->id]);
        $ids = $ids->merge(
            Follow::withoutGlobalScope('workspace')->where('follower_id', $user->id)->pluck('creator_id')
        );
        $ids = $ids->merge(
            Contact::withoutGlobalScope('workspace')
                ->where('user_id', $user->id)->whereNotNull('biolink_user_id')->pluck('biolink_user_id')
        );
        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $ids;
        }

        $blockedByIds = UserBlock::where('blocked_user_id', $user->id)
            ->whereIn('blocker_user_id', $ids)
            ->pluck('blocker_user_id')->map(fn ($id) => (int) $id)->all();

        return User::whereIn('id', $ids)
            ->where(function ($w) use ($user) {
                $w->where('status', 'active')
                  ->orWhereNull('status')
                  ->orWhere('id', $user->id);
            })
            ->when(!empty($blockedByIds), fn ($qq) => $qq->whereNotIn('id', $blockedByIds))
            ->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    private static function myLinkItems(User $user, string $q, bool $onlyVerified, int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        // "My links" means every link the searcher OWNS, regardless of which
        // workspace it lives in. On the web surface the `workspace.scope`
        // middleware binds the searcher's active workspace and the
        // BelongsToWorkspace global scope would narrow this to the active
        // workspace only — hiding the same user's links in their OTHER
        // workspaces (the API/Sanctum surface binds no workspace and already
        // returns them all). Opt out of the workspace filter so web matches
        // API/mobile; ownership is still enforced by the user_id predicate, so
        // this never exposes another account's links.
        $query = Link::withoutGlobalScope('workspace')->where('user_id', $user->id);
        self::applyLinkTextMatch($query, $q, $user->id);
        if ($onlyVerified) {
            $query->where('is_verified', true);
        }
        $fetchLimit = min(($page + 1) * $perGroup * 2 + 2, 200);
        $links = $query->orderByDesc('total_clicks')->limit($fetchLimit)->get();

        $slice = $links->skip($page * $perGroup)->take($perGroup + 1);
        $has_more = $slice->count() > $perGroup;
        $items = $slice->take($perGroup)
            ->map(fn (Link $l) => self::linkItem($l, 'my_links', true))
            ->values()->all();

        return ['items' => $items, 'has_more' => $has_more];
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    private static function followedLinkItems(User $user, string $q, bool $onlyVerified, int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        // Follow is account-level but uses BelongsToWorkspace; opt out of the
        // workspace global scope so the web surface (under `workspace.scope`)
        // resolves EVERY creator the searcher follows, not just those followed
        // while the active workspace was bound — matching the Sanctum/mobile
        // surface (no workspace binding). follower_id still scopes to the searcher.
        $creatorIds = Follow::withoutGlobalScope('workspace')
            ->where('follower_id', $user->id)->pluck('creator_id')->unique();
        if ($creatorIds->isEmpty()) {
            return ['items' => [], 'has_more' => false];
        }

        // Even for a creator the searcher follows, never surface links from an
        // account the searcher can't reach right now: one that has since been
        // suspended/deactivated (status != active), or one that has blocked the
        // searcher. This mirrors the reachability gate on the People group
        // (peopleItems); canViewLink() below only enforces the per-link
        // visibility tiers, not account-level reachability.
        $blockedByIds = UserBlock::where('blocked_user_id', $user->id)
            ->whereIn('blocker_user_id', $creatorIds)
            ->pluck('blocker_user_id')->map(fn ($id) => (int) $id)->all();

        $creatorIds = User::whereIn('id', $creatorIds)
            ->where(function ($w) {
                // Treat a null status as active (mirrors the login guard
                // `($user->status ?? 'active') !== 'active'`).
                $w->where('status', 'active')
                  ->orWhereNull('status');
            })
            ->when(!empty($blockedByIds), fn ($qq) => $qq->whereNotIn('id', $blockedByIds))
            ->pluck('id')->map(fn ($id) => (int) $id)->unique();

        if ($creatorIds->isEmpty()) {
            return ['items' => [], 'has_more' => false];
        }

        // Followed creators' links live in THEIR workspaces, not the searcher's.
        // On the web surface the `workspace.scope` middleware binds the searcher's
        // active workspace and the BelongsToWorkspace global scope would filter
        // this entire cross-account query to empty. Opt out of the workspace
        // filter here; visibility is still enforced by canViewLink() below.
        $query = Link::withoutGlobalScope('workspace')
            ->whereIn('user_id', $creatorIds)
            ->where('is_active', true);
        self::applyLinkTextMatch($query, $q, null);
        if ($onlyVerified) {
            $query->where('is_verified', true);
        }
        $fetchLimit = min(($page + 1) * $perGroup * 4 + 4, 300);
        $links = $query->orderByDesc('total_clicks')->limit($fetchLimit)->get();

        // Batch-resolve visibility instead of an N+1 follow/subscriber check per
        // link. Every creator here is already followed by the viewer, so
        // `followers` visibility always passes; only `subscribers` needs a real
        // membership check, which we pre-fetch once for the whole result set.
        $subscribedCreatorIds = self::subscribedCreatorIds($user, $links);

        $visible = $links->filter(fn (Link $l) => self::canViewLink($user, $l, $subscribedCreatorIds));

        $slice = $visible->skip($page * $perGroup)->take($perGroup + 1);
        $has_more = $slice->count() > $perGroup;
        $items = $slice->take($perGroup)->map(fn (Link $l) => self::linkItem($l, 'followed', false))->values()->all();

        return ['items' => $items, 'has_more' => $has_more];
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    private static function workspaceItems(User $user, string $q, bool $onlyVerified, int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        // Workspaces carry no verification badge.
        if ($onlyVerified) {
            return ['items' => [], 'has_more' => false];
        }
        if ($q === '') {
            return ['items' => [], 'has_more' => false];
        }

        $needle = mb_strtolower($q);
        $all = $user->accessibleWorkspaces()
            ->filter(fn (Workspace $w) => str_contains(mb_strtolower((string) $w->name), $needle));

        $slice = $all->skip($page * $perGroup)->take($perGroup + 1);
        $has_more = $slice->count() > $perGroup;
        $items = $slice->take($perGroup);

        $itemsArray = $items->map(function (Workspace $w) use ($user) {
            $owned = (int) $w->owner_user_id === (int) $user->id;
            return [
                'type'           => 'workspace',
                'category'       => 'workspaces',
                'id'             => $w->id,
                'title'          => $w->name,
                'subtitle'       => $owned ? 'Owner' : 'Member',
                'type_label'     => 'Workspace',
                'initials'       => self::initialsOf((string) $w->name),
                'badge'          => $w->is_personal ? 'Personal' : null,
                'verified'       => false,
                'verified_label' => null,
                'action'         => [
                    'kind'         => 'workspace',
                    'url'          => null,
                    'switch_url'   => route('user.workspaces.switch', $w),
                    'workspace_id' => $w->id,
                ],
            ];
        })->values()->all();

        return ['items' => $itemsArray, 'has_more' => $has_more];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Task #3593: `ics` (event) links matching the query text OR a hashtag
     * filter chip. Reuses {@see canViewLink} so a private/followers-only
     * event never leaks to a searcher who can't already see it — the same
     * visibility gate the other groups enforce.
     *
     * @param array{tag?:string} $filters
     * @return array{items:array<int,array<string,mixed>>,has_more:bool}
     */
    private static function eventItems(User $user, string $q, array $filters, bool $onlyVerified, int $page = 0, int $perGroup = self::GROUP_LIMIT): array
    {
        if ($onlyVerified) {
            return ['items' => [], 'has_more' => false];
        }
        $tag = isset($filters['tag']) ? mb_strtolower(ltrim(trim((string) $filters['tag']), '#')) : '';
        if ($q === '' && $tag === '') {
            return ['items' => [], 'has_more' => false];
        }

        $query = Link::withoutGlobalScope('workspace')
            ->where('type', 'ics')
            ->where('is_active', true)
            ->whereHas('icsData');

        if ($q !== '') {
            $needle = '%' . $q . '%';
            $query->where(function ($w) use ($needle) {
                $w->where('alias', 'ilike', $needle)
                  ->orWhere('title', 'ilike', $needle)
                  ->orWhereHas('icsData', function ($ic) use ($needle) {
                      $ic->where('location', 'ilike', $needle)
                         ->orWhere('description', 'ilike', $needle)
                         ->orWhereRaw('hashtags::text ilike ?', [$needle]);
                  });
            });
        }
        if ($tag !== '') {
            $query->whereHas('icsData', function ($ic) use ($tag) {
                $ic->whereRaw('hashtags::text ilike ?', ['%"' . $tag . '"%']);
            });
        }

        $fetchLimit = min(($page + 1) * $perGroup * 3 + 3, 200);
        $links = $query->with('icsData')->orderByDesc('total_clicks')->limit($fetchLimit)->get();

        $subscribedCreatorIds = self::subscribedCreatorIds($user, $links);
        $visible = $links->filter(fn (Link $l) => self::canViewLink($user, $l, $subscribedCreatorIds));

        $slice = $visible->skip($page * $perGroup)->take($perGroup + 1);
        $has_more = $slice->count() > $perGroup;
        $items = $slice->take($perGroup)->map(fn (Link $l) => self::eventItem($l))->values()->all();

        return ['items' => $items, 'has_more' => $has_more];
    }

    /** Build a normalized event item from an `ics` link. */
    private static function eventItem(Link $l): array
    {
        $ics = $l->icsData;
        $alias = $l->alias;
        $public = $alias ? url('/' . $alias) : null;
        $when = $ics && $ics->start_date ? $ics->start_date->format('M j, Y') : null;
        $hashtags = $ics ? $ics->hashtagList() : [];

        $subtitleParts = array_filter([$when, $ics->location ?? null]);

        return [
            'type'           => 'event',
            'category'       => 'events',
            'id'             => $l->id,
            'title'          => $l->title ?: ($alias ?: 'Event'),
            'subtitle'       => implode(' · ', $subtitleParts) ?: ($public ?? ''),
            'type_label'     => 'Event',
            'initials'       => self::initialsOf((string) ($l->title ?: $alias ?: 'EV')),
            'badge'          => $hashtags ? ('#' . $hashtags[0]) : null,
            'verified'       => (bool) $l->is_verified,
            'verified_label' => $l->is_verified ? ($l->verified_name ?: 'Verified') : null,
            'action'         => [
                'kind'    => 'event',
                'url'     => $public,
                'link_id' => $l->id,
            ],
        ];
    }

    /**
     * Apply the shared link text match (alias / back-half aliases / SEO
     * title / description / keywords / verified name) to a Link query. When
     * $ownerId is given, alias-alias matches are scoped to that owner.
     */
    private static function applyLinkTextMatch($query, string $q, ?int $ownerId): void
    {
        if ($q === '') {
            return;
        }
        $needle = '%' . $q . '%';

        $query->where(function ($w) use ($needle, $q) {
            $w->where('alias', 'ilike', $needle)
              ->orWhere('title', 'ilike', $needle)
              ->orWhere('seo_title', 'ilike', $needle)
              ->orWhere('seo_description', 'ilike', $needle)
              ->orWhere('verified_name', 'ilike', $needle)
              // biolink SEO keywords live in settings.biolink.meta.keywords
              ->orWhereRaw("(settings #>> '{biolink,meta,keywords}') ilike ?", [$needle])
              // additional back-half aliases (LinkAlias rows)
              ->orWhereIn('id', LinkAlias::where('alias', 'ilike', $needle)->select('link_id'));

            // T9: keypad-spelled aliases (digits typed on the number pad).
            if (DialerT9::isDigitSequence($q)) {
                $seq = preg_replace('/\D+/', '', $q);
                if (strlen($seq) >= 2) {
                    // Postgres has no T9 encode in SQL; the alias ilike above
                    // already covers literal digits, so we skip a heavy scan.
                }
            }
        });
    }

    /** Build a normalized link item for the given category. */
    private static function linkItem(Link $l, string $category, bool $owned): array
    {
        $alias = $l->alias;
        $public = $alias ? url('/' . $alias) : null;
        $verified = (bool) $l->is_verified;
        $typeLabel = (string) ($l->type_label ?? 'Link');

        $subtitleParts = array_filter([
            $l->title ?: null,
            $alias ? '/' . $alias : null,
        ]);

        return [
            'type'           => 'link',
            'category'       => $category,
            'id'             => $l->id,
            'title'          => $l->title ?: ($alias ?: $typeLabel),
            'subtitle'       => implode(' · ', $subtitleParts) ?: ($public ?? ''),
            'type_label'     => $typeLabel,
            'initials'       => self::initialsOf((string) ($l->title ?: $alias ?: 'LN')),
            'badge'          => $verified ? ($l->verified_name ?: 'Verified') : null,
            'verified'       => $verified,
            'verified_label' => $verified ? ($l->verified_name ?: 'Verified') : null,
            'action'         => [
                'kind'     => 'link',
                'url'      => $public,
                'link_id'  => $l->id,
                'edit_url' => $owned ? route('user.links.index') . '?edit=' . $l->id : null,
            ],
        ];
    }

    /**
     * Pre-fetch, in a single query, which of the given links' creators the
     * viewer actively subscribes to. Lets followedLinkItems() gate
     * `subscribers`-visibility links without an N+1 lookup per link. Returns an
     * empty set when no link in the batch actually needs a subscriber check.
     *
     * @param Collection<int,Link> $links
     * @return Collection<int,int>
     */
    private static function subscribedCreatorIds(User $viewer, Collection $links): Collection
    {
        $needsCheck = $links->contains(fn (Link $l) =>
            ($l->visibility ?? 'public') === 'subscribers'
            && (int) $l->user_id !== (int) $viewer->id
        );
        if (!$needsCheck) {
            return collect();
        }

        // Subscriber uses BelongsToWorkspace, but a subscription is an
        // account-level relationship between the viewer's email and a creator;
        // opt out of the workspace scope so the web surface (under
        // `workspace.scope`) doesn't miss a real subscription created outside
        // the active workspace and wrongly hide a subscribers-only link. The
        // user_id + email predicates still scope this precisely.
        return Subscriber::withoutGlobalScope('workspace')
            ->whereIn('user_id', $links->pluck('user_id')->unique()->all())
            ->where('status', 'active')
            ->where('email', $viewer->email)
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * Mirror of RedirectController::enforceVisibility for read-side gating.
     * The Dialer user is always authenticated, so "registered" always passes
     * and "followers" passes for the accounts they follow; only "subscribers"
     * needs a live subscription check.
     *
     * When $subscribedCreatorIds is supplied (the batch path used by
     * followedLinkItems, where every creator is already followed by the
     * viewer), the per-link follow/subscriber queries are skipped in favor of
     * the pre-resolved sets.
     *
     * @param ?Collection<int,int> $subscribedCreatorIds
     */
    private static function canViewLink(User $viewer, Link $link, ?Collection $subscribedCreatorIds = null): bool
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') {
            return true;
        }
        if ((int) $link->user_id === (int) $viewer->id) {
            return true;
        }
        // Only biolink family + a few gated types carry a real visibility gate.
        $gatedTypes = ['url', 'file', 'ics', 'vcf', 'reviews', 'paid_page', 'brand_kit'];
        if (!$link->isBiolinkFamily() && !in_array($link->type, $gatedTypes, true)) {
            return true;
        }
        if ($vis === 'registered') {
            return true; // viewer is authenticated
        }
        if ($vis === 'followers') {
            // Batch path: this method is only called for links whose creators
            // the viewer already follows, so `followers` visibility passes.
            if ($subscribedCreatorIds !== null) {
                return true;
            }
            return Follow::withoutGlobalScope('workspace')
                ->where('follower_id', $viewer->id)
                ->where('creator_id', $link->user_id)->exists();
        }
        if ($vis === 'subscribers') {
            if ($subscribedCreatorIds !== null) {
                return $subscribedCreatorIds->contains((int) $link->user_id);
            }
            return Subscriber::withoutGlobalScope('workspace')
                ->where('user_id', $link->user_id)
                ->where('status', 'active')
                ->where('email', $viewer->email)
                ->exists();
        }
        return true;
    }

    private static function initialsOf(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        $out = mb_strtoupper($a . $b);
        return $out !== '' ? $out : '?';
    }

    /** @return array{key:string,label:string,items:array<int,array<string,mixed>>} */
    private static function group(string $key, string $label, array $items): array
    {
        return ['key' => $key, 'label' => $label, 'has_more' => false, 'items' => $items];
    }

    /**
     * Build a group from a paginated builder result `{items, has_more}`.
     *
     * @param array{items:array<int,array<string,mixed>>,has_more:bool} $result
     * @return array{key:string,label:string,has_more:bool,items:array<int,array<string,mixed>>}
     */
    private static function groupWithPage(string $key, string $label, array $result): array
    {
        return [
            'key'      => $key,
            'label'    => $label,
            'has_more' => $result['has_more'],
            'items'    => $result['items'],
        ];
    }
}
