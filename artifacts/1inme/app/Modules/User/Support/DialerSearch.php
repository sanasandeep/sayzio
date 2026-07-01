<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
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
 */
class DialerSearch
{
    /** Per-group hard cap so a broad query can never balloon the payload. */
    private const GROUP_LIMIT = 12;

    /**
     * Run the universal search and return the grouped result contract.
     *
     * @param array{filter?:string,tag?:string,scope?:string} $filters
     * @return array{q:string,filter:?string,total:int,groups:array<int,array{key:string,label:string,items:array<int,array<string,mixed>>}>}
     */
    public static function universal(User $user, string $q, array $filters = []): array
    {
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
            $groups[] = self::group('contacts', 'Contacts', self::contactItems($user, $q, $filters, $onlyVerified));
            $groups[] = self::group('people', 'People', self::peopleItems($user, $q, $onlyVerified));
            $groups[] = self::group('my_links', 'My links', self::myLinkItems($user, $q, $onlyVerified));
            $groups[] = self::group('followed', 'Followed', self::followedLinkItems($user, $q, $onlyVerified));
            $groups[] = self::group('workspaces', 'Workspaces', self::workspaceItems($user, $q, $onlyVerified));
        }

        $groups = array_values(array_filter($groups, fn ($g) => count($g['items']) > 0));
        $total = array_sum(array_map(fn ($g) => count($g['items']), $groups));

        return [
            'q'      => $q,
            'filter' => $onlyVerified ? 'verified' : null,
            'total'  => $total,
            'groups' => $groups,
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
    public static function contactsAdvanced(int $userId, string $q, array $filters = []): Collection
    {
        $q = trim($q);
        $needle = '%' . $q . '%';
        $phoneNeedle = '%' . ContactPhone::normalize($q) . '%';

        $query = Contact::where('user_id', $userId)->with(['phones', 'emails', 'biolinkUser']);

        if ($q !== '') {
            $query->where(function ($w) use ($needle, $phoneNeedle) {
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

        // T9 augmentation for digit queries (keypad-spelled names).
        if ($q !== '' && DialerT9::isDigitSequence($q)) {
            $seq = preg_replace('/\D+/', '', $q);
            if (strlen($seq) >= 2 && $rows->count() < 100) {
                $haveIds = $rows->pluck('id')->all();
                $extra = Contact::where('user_id', $userId)
                    ->with(['phones', 'emails', 'biolinkUser'])
                    ->whereNotIn('id', $haveIds ?: [0])
                    ->orderBy('display_name')
                    ->limit(300)->get()
                    ->filter(fn ($c) => DialerT9::matches($c->nameForDisplay(), $seq))
                    ->take(100 - $rows->count());
                $rows = $rows->concat($extra)->values();
            }
        }

        return $rows;
    }

    // ── Category builders ────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    private static function contactItems(User $user, string $q, array $filters, bool $onlyVerified): array
    {
        // Contacts carry no verification badge, so a verified-only filter with
        // no search term yields nothing here.
        if ($onlyVerified && $q === '') {
            return [];
        }

        $advFilters = [
            'tag'         => $filters['tag'] ?? null,
            'has_biolink' => !empty($filters['has_biolink']),
        ];

        $rows = self::contactsAdvanced($user->id, $q, $advFilters)->take(self::GROUP_LIMIT);

        return $rows->map(function (Contact $c) {
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
    }

    /** @return array<int,array<string,mixed>> */
    private static function peopleItems(User $user, string $q, bool $onlyVerified): array
    {
        // Reachable people = self + accounts the user follows + accounts linked
        // from the user's own contacts (owned + followed only — never a global
        // user directory).
        $ids = collect([$user->id]);
        $ids = $ids->merge(Follow::where('follower_id', $user->id)->pluck('creator_id'));
        $ids = $ids->merge(
            Contact::where('user_id', $user->id)->whereNotNull('biolink_user_id')->pluck('biolink_user_id')
        );
        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        // Even within the reachable set, never surface an account the searcher
        // can't reach right now: one that has since been suspended/deactivated
        // (status != active — self is always exempt), or one that has blocked
        // the searcher. Resolve the visible id set up front so BOTH the primary
        // query and the T9 fallback query below share the same gate.
        $blockedByIds = UserBlock::where('blocked_user_id', $user->id)
            ->whereIn('blocker_user_id', $ids)
            ->pluck('blocker_user_id')->map(fn ($id) => (int) $id)->all();

        $ids = User::whereIn('id', $ids)
            ->where(function ($w) use ($user) {
                // Treat a null status as active (mirrors the login guard
                // `($user->status ?? 'active') !== 'active'`); self is exempt so
                // the searcher can always find their own account.
                $w->where('status', 'active')
                  ->orWhereNull('status')
                  ->orWhere('id', $user->id);
            })
            ->when(!empty($blockedByIds), fn ($qq) => $qq->whereNotIn('id', $blockedByIds))
            ->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $query = User::whereIn('id', $ids);
        if ($q !== '') {
            $needle = '%' . $q . '%';
            $query->where(function ($w) use ($needle, $q) {
                $w->where('name', 'ilike', $needle)
                  ->orWhere('handle', 'ilike', $needle);
                if (DialerT9::isDigitSequence($q)) {
                    // Allow keypad-spelled names to resolve people too.
                    $seq = preg_replace('/\D+/', '', $q);
                    if (strlen($seq) >= 2) {
                        // handled post-fetch below
                    }
                }
            });
        }
        $candidates = $query->orderBy('name')->limit(60)->get();

        // T9 post-filter for digit queries (spell a name on the keypad).
        if ($q !== '' && DialerT9::isDigitSequence($q)) {
            $seq = preg_replace('/\D+/', '', $q);
            if (strlen($seq) >= 2) {
                $extra = User::whereIn('id', $ids)
                    ->whereNotIn('id', $candidates->pluck('id')->all() ?: [0])
                    ->orderBy('name')->limit(200)->get()
                    ->filter(fn ($u) => DialerT9::matches((string) $u->name, $seq));
                $candidates = $candidates->concat($extra)->values();
            }
        }

        // Which of these accounts carry a verification badge (a verified link)?
        $verifiedIds = $candidates->isEmpty() ? collect() : Link::whereIn('user_id', $candidates->pluck('id'))
            ->where('is_verified', true)
            ->pluck('user_id')->unique();

        if ($onlyVerified) {
            $candidates = $candidates->filter(fn ($u) => $verifiedIds->contains($u->id))->values();
        }

        return $candidates->take(self::GROUP_LIMIT)->map(function (User $u) use ($user, $verifiedIds) {
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
    }

    /** @return array<int,array<string,mixed>> */
    private static function myLinkItems(User $user, string $q, bool $onlyVerified): array
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
        $links = $query->orderByDesc('total_clicks')->limit(self::GROUP_LIMIT * 2)->get();

        return $links->take(self::GROUP_LIMIT)
            ->map(fn (Link $l) => self::linkItem($l, 'my_links', true))
            ->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    private static function followedLinkItems(User $user, string $q, bool $onlyVerified): array
    {
        $creatorIds = Follow::where('follower_id', $user->id)->pluck('creator_id')->unique();
        if ($creatorIds->isEmpty()) {
            return [];
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
            return [];
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
        $links = $query->orderByDesc('total_clicks')->limit(self::GROUP_LIMIT * 4)->get();

        // Batch-resolve visibility instead of an N+1 follow/subscriber check per
        // link. Every creator here is already followed by the viewer, so
        // `followers` visibility always passes; only `subscribers` needs a real
        // membership check, which we pre-fetch once for the whole result set.
        $subscribedCreatorIds = self::subscribedCreatorIds($user, $links);

        $visible = $links
            ->filter(fn (Link $l) => self::canViewLink($user, $l, $subscribedCreatorIds))
            ->take(self::GROUP_LIMIT);

        return $visible->map(fn (Link $l) => self::linkItem($l, 'followed', false))->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    private static function workspaceItems(User $user, string $q, bool $onlyVerified): array
    {
        // Workspaces carry no verification badge.
        if ($onlyVerified) {
            return [];
        }
        if ($q === '') {
            return [];
        }

        $needle = mb_strtolower($q);
        $items = $user->accessibleWorkspaces()
            ->filter(fn (Workspace $w) => str_contains(mb_strtolower((string) $w->name), $needle))
            ->take(self::GROUP_LIMIT);

        return $items->map(function (Workspace $w) use ($user) {
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
    }

    // ── Helpers ──────────────────────────────────────────────────────────

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

        return Subscriber::whereIn('user_id', $links->pluck('user_id')->unique()->all())
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
            return Follow::where('follower_id', $viewer->id)
                ->where('creator_id', $link->user_id)->exists();
        }
        if ($vis === 'subscribers') {
            if ($subscribedCreatorIds !== null) {
                return $subscribedCreatorIds->contains((int) $link->user_id);
            }
            return Subscriber::where('user_id', $link->user_id)
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
        return ['key' => $key, 'label' => $label, 'items' => $items];
    }
}
