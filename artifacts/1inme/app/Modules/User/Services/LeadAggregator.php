<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\EventInterest;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Lead;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates captured people across 8 capture surfaces into a single
 * reviewable "Leads" queue. A lead is "pending" as long as no
 * {@see Lead} state row exists for its (source_type, source_id) pair —
 * approving or dismissing writes that row so the item drops out of the
 * pending view without ever mutating the source record itself.
 *
 * Scaling note: every operation pushes filtering, "already handled"
 * exclusion, counting and pagination down into SQL. Nothing loads a whole
 * source table into memory. "Already handled" is excluded via a correlated
 * `NOT EXISTS` against the (sparse) `leads` table rather than plucking every
 * done id into PHP. Cross-source pagination fetches only the top
 * `page * perPage` rows per active source, merges those in memory, then
 * slices the requested page — so a business with tens of thousands of
 * RSVPs/orders/reviews only ever materialises one page's worth of candidates.
 *
 * Scoping note: Subscriber and Form(Submission) already carry real
 * workspace_id data (via BelongsToWorkspace) so those two sources are
 * scoped to the *current* workspace. RSVP / Store / Restaurant / Service
 * Booking / Review / Event-Interest have no workspace concept at all in
 * this codebase today (every existing controller for those features
 * scopes by `Link->user_id === workspace_owner_id()`, not by workspace) —
 * this aggregator mirrors that same owner-level scoping for them rather
 * than inventing a stricter rule the rest of the app doesn't enforce.
 */
class LeadAggregator
{
    public const SOURCE_RSVP             = 'rsvp';
    public const SOURCE_FORM             = 'form_submission';
    public const SOURCE_SUBSCRIBER       = 'subscriber';
    public const SOURCE_STORE_ORDER      = 'store_order';
    public const SOURCE_RESTAURANT_ORDER = 'restaurant_order';
    public const SOURCE_SERVICE_BOOKING  = 'service_booking';
    public const SOURCE_REVIEW           = 'review';
    public const SOURCE_EVENT_INTEREST   = 'event_interest';

    public const SOURCES = [
        self::SOURCE_RSVP, self::SOURCE_FORM, self::SOURCE_SUBSCRIBER,
        self::SOURCE_STORE_ORDER, self::SOURCE_RESTAURANT_ORDER,
        self::SOURCE_SERVICE_BOOKING, self::SOURCE_REVIEW, self::SOURCE_EVENT_INTEREST,
    ];

    /**
     * Sources whose pending count genuinely differs between an owner's
     * workspaces because the underlying records carry real workspace_id data
     * (via BelongsToWorkspace). Handling one of these only changes the count
     * for the workspace it happened in — so its cache invalidation can stay
     * scoped to the current workspace.
     *
     * Every other source (RSVP / Store / Restaurant / Service Booking /
     * Review / Event-Interest) is scoped only by owner (`Link->user_id`), so
     * its count is identical across ALL of the owner's workspaces and handling
     * one must invalidate every one of the owner's cached workspace badges.
     */
    public const WORKSPACE_SCOPED_SOURCES = [
        self::SOURCE_FORM, self::SOURCE_SUBSCRIBER,
    ];

    /** Whether a source's pending count varies per workspace (vs owner-wide). */
    public static function isWorkspaceScopedSource(string $source): bool
    {
        return in_array($source, self::WORKSPACE_SCOPED_SOURCES, true);
    }

    /** Memoised per-user form id set used by the form-submission source scope. */
    protected ?Collection $formIds = null;

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_RSVP             => 'Event RSVP',
            self::SOURCE_FORM             => 'Form Submission',
            self::SOURCE_SUBSCRIBER       => 'Subscriber',
            self::SOURCE_STORE_ORDER      => 'Store Order',
            self::SOURCE_RESTAURANT_ORDER => 'Restaurant Order',
            self::SOURCE_SERVICE_BOOKING  => 'Service Booking',
            self::SOURCE_REVIEW           => 'Review',
            self::SOURCE_EVENT_INTEREST   => 'Event Interest',
        ];
    }

    public function __construct(protected int $userId) {}

    /** Count of pending leads across all sources (sidebar badge). */
    public function pendingCount(): int
    {
        return array_sum($this->countsBySource());
    }

    /**
     * Short TTL (seconds) for the cached sidebar pending count. The badge
     * renders on every page that extends the user layout, so serving it from
     * a short-lived cache removes 8 COUNT queries per page view for a number
     * that changes rarely. Approving/dismissing a lead invalidates the key
     * (see {@see forgetPendingCount()}); this TTL is only the safety net that
     * clears any stale entry a cross-workspace write couldn't target.
     */
    public const PENDING_COUNT_TTL = 60;

    /**
     * Sidebar-friendly pending count served from a short-lived cache keyed
     * per owner + active workspace. Two sources (Subscriber / Form) are
     * workspace-scoped via BelongsToWorkspace, so the total genuinely differs
     * between an owner's workspaces — hence the workspace id in the key.
     */
    public function cachedPendingCount(): int
    {
        return (int) Cache::remember(
            self::pendingCountCacheKey($this->userId, self::currentWorkspaceId()),
            self::PENDING_COUNT_TTL,
            fn () => $this->pendingCount()
        );
    }

    /** Cache key for the sidebar pending count of a given owner + workspace. */
    public static function pendingCountCacheKey(int $userId, ?int $workspaceId = null): string
    {
        return 'leads:pending_count:' . $userId . ':' . ($workspaceId ?? 'none');
    }

    /** Drop the cached pending count so the badge re-counts on next render. */
    public static function forgetPendingCount(int $userId, ?int $workspaceId = null): void
    {
        Cache::forget(self::pendingCountCacheKey($userId, $workspaceId));
    }

    /**
     * Drop the cached pending count for EVERY workspace the owner has, plus
     * the workspace-less key. Used after handling an owner-scoped lead
     * (RSVP / order / review / …) whose count is identical across all of the
     * owner's workspaces — so a single workspace-targeted forget would leave a
     * stale badge in the owner's other workspaces until the TTL self-heals.
     *
     * The badge is always keyed by the workspace *owner*, so the only cache
     * entries that can exist for this owner are their own workspaces' ids
     * (a team workspace the owner merely belongs to is keyed by that
     * workspace's owner instead) — hence iterating ownedWorkspaces suffices.
     */
    public static function forgetPendingCountForAllWorkspaces(int $userId): void
    {
        self::forgetPendingCount($userId, null);

        Workspace::where('owner_user_id', $userId)
            ->pluck('id')
            ->each(fn ($id) => self::forgetPendingCount($userId, (int) $id));
    }

    /** Id of the workspace bound to the current request, or null (CLI/public). */
    public static function currentWorkspaceId(): ?int
    {
        return app()->bound('current_workspace') ? app('current_workspace')?->id : null;
    }

    public function paginate(array $filters, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $page   = max(1, $page);
        $search = trim((string) ($filters['q'] ?? ''));
        $sources = $this->activeSources($filters['source'] ?? null);

        // To surface the correct page of a global created_at-desc ordering
        // across N sources, the top (page * perPage) rows of each source are
        // a sufficient superset — merge those, sort, then slice.
        $limit = $page * $perPage;

        $total = 0;
        $rows  = collect();

        foreach ($sources as $source) {
            $base = $this->applySearch($this->sourceQuery($source), $source, $search);

            $total += (clone $base)->count();

            $models = (clone $base)->orderByDesc('created_at')->limit($limit)->get();
            foreach ($models as $model) {
                $rows->push($this->mapRow($source, $model));
            }
        }

        $sorted    = $rows->sortByDesc(fn ($i) => optional($i['created_at'])->getTimestamp() ?? 0)->values();
        $pageItems = $sorted->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems, $total, $perPage, $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /** Find a single pending item by source_type/source_id (for the approve/dismiss actions). */
    public function find(string $sourceType, int $sourceId): ?array
    {
        if (!in_array($sourceType, self::SOURCES, true)) {
            return null;
        }

        $model = $this->sourceQuery($sourceType)->whereKey($sourceId)->first();

        return $model ? $this->mapRow($sourceType, $model) : null;
    }

    /** Pending-lead count per source (used for the filter chips), one COUNT query each. */
    public function countsBySource(): array
    {
        $counts = array_fill_keys(self::SOURCES, 0);
        foreach (self::SOURCES as $source) {
            $counts[$source] = $this->sourceQuery($source)->count();
        }
        return $counts;
    }

    /** @return list<string> The sources a request touches (all, or a single valid filter). */
    protected function activeSources(?string $filter): array
    {
        if ($filter && in_array($filter, self::SOURCES, true)) {
            return [$filter];
        }
        return self::SOURCES;
    }

    /**
     * Base pending query for a source: owner/workspace scoping + a
     * correlated NOT EXISTS that drops anything already approved/dismissed,
     * all resolved in SQL. Eager loads are attached here but only fire on get().
     */
    protected function sourceQuery(string $source): Builder
    {
        $query = match ($source) {
            self::SOURCE_RSVP => Rsvp::query()
                ->whereIn('link_id', $this->linkIdsSubquery())
                ->with('link:id,alias,title'),

            self::SOURCE_FORM => FormSubmission::query()
                ->whereIn('form_id', $this->formIds())
                ->where('is_spam', false)
                ->completed()
                ->with('form:id,title'),

            self::SOURCE_SUBSCRIBER => Subscriber::query()
                ->where('user_id', $this->userId)
                ->where('is_spam', false),

            self::SOURCE_STORE_ORDER => StoreOrder::query()
                ->whereIn('link_id', $this->linkIdsSubquery()),

            self::SOURCE_RESTAURANT_ORDER => RestaurantOrder::query()
                ->whereIn('link_id', $this->linkIdsSubquery()),

            self::SOURCE_SERVICE_BOOKING => ServiceBookingRequest::query()
                ->whereIn('link_id', $this->linkIdsSubquery()),

            self::SOURCE_REVIEW => Review::query()
                ->where('user_id', $this->userId)
                ->where('is_spam', false),

            self::SOURCE_EVENT_INTEREST => EventInterest::query()
                ->whereIn('link_id', $this->linkIdsSubquery())
                ->where('status', EventInterest::INTERESTED)
                ->with('user:id,name,email'),
        };

        return $this->excludeDone($query, $source);
    }

    /** Drop rows that already have a Lead state row, via a correlated NOT EXISTS (no PHP pluck). */
    protected function excludeDone(Builder $query, string $source): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->whereNotExists(function ($sub) use ($source, $table) {
            $sub->select(DB::raw(1))
                ->from('leads')
                ->whereColumn('leads.source_id', "{$table}.id")
                ->where('leads.source_type', $source)
                ->where('leads.user_id', $this->userId);
        });
    }

    /**
     * Push the free-text search down into SQL against each source's real
     * columns (name/email/phone plus source-specific text). Form payloads
     * live in JSON, so those match on a `data::text` cast (Postgres).
     */
    protected function applySearch(Builder $query, string $source, string $search): Builder
    {
        $needle = trim($search);
        if ($needle === '') {
            return $query;
        }

        $like = '%' . addcslashes($needle, '%_\\') . '%';

        return $query->where(function (Builder $w) use ($source, $like) {
            switch ($source) {
                case self::SOURCE_RSVP:
                    $w->where('name', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like)
                        ->orWhere('phone', 'ilike', $like)
                        ->orWhere('response', 'ilike', $like);
                    break;

                case self::SOURCE_FORM:
                    $w->whereRaw('data::text ILIKE ?', [$like])
                        ->orWhereHas('form', fn (Builder $f) => $f->where('title', 'ilike', $like));
                    break;

                case self::SOURCE_SUBSCRIBER:
                    $w->where('name', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like)
                        ->orWhere('phone', 'ilike', $like)
                        ->orWhere('type', 'ilike', $like);
                    break;

                case self::SOURCE_STORE_ORDER:
                    $w->where('customer_name', 'ilike', $like)
                        ->orWhere('customer_contact', 'ilike', $like)
                        ->orWhere('status', 'ilike', $like);
                    break;

                case self::SOURCE_RESTAURANT_ORDER:
                    $w->where('customer_name', 'ilike', $like)
                        ->orWhere('status', 'ilike', $like);
                    break;

                case self::SOURCE_SERVICE_BOOKING:
                    $w->where('customer_name', 'ilike', $like)
                        ->orWhere('customer_email', 'ilike', $like)
                        ->orWhere('customer_phone', 'ilike', $like)
                        ->orWhere('status', 'ilike', $like);
                    break;

                case self::SOURCE_REVIEW:
                    $w->where('author_name', 'ilike', $like)
                        ->orWhere('author_email', 'ilike', $like);
                    break;

                case self::SOURCE_EVENT_INTEREST:
                    $w->where('guest_email', 'ilike', $like)
                        ->orWhereHas('user', fn (Builder $u) => $u
                            ->where('name', 'ilike', $like)
                            ->orWhere('email', 'ilike', $like));
                    break;
            }
        });
    }

    /** Turn a fetched source model into the normalised lead row shape. */
    protected function mapRow(string $source, Model $model): array
    {
        switch ($source) {
            case self::SOURCE_RSVP:
                return $this->row(
                    self::SOURCE_RSVP, $model->id, $model->name, $model->email, $model->phone,
                    trim(($model->response ? ucfirst($model->response) : '') . ($model->link?->alias ? ' · /' . $model->link->alias : '')) ?: null,
                    $model->created_at
                );

            case self::SOURCE_FORM:
                [$name, $email, $phone, $organization] = $this->extractIdentity((array) ($model->data ?? []));
                return $this->row(
                    self::SOURCE_FORM, $model->id, $name, $email, $phone,
                    $model->form?->title,
                    $model->created_at,
                    $organization
                );

            case self::SOURCE_SUBSCRIBER:
                return $this->row(
                    self::SOURCE_SUBSCRIBER, $model->id, $model->name, $model->email, $model->phone,
                    $model->type ? ucfirst(str_replace('_', ' ', $model->type)) : null,
                    $model->subscribed_at ?? $model->created_at
                );

            case self::SOURCE_STORE_ORDER:
                $contact = trim((string) $model->customer_contact);
                $email   = filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact : null;
                $phone   = ($contact !== '' && !$email) ? $contact : null;
                return $this->row(
                    self::SOURCE_STORE_ORDER, $model->id, $model->customer_name, $email, $phone,
                    'Order #' . $model->id . ' · ' . ($model->status_label ?? $model->status),
                    $model->created_at
                );

            case self::SOURCE_RESTAURANT_ORDER:
                return $this->row(
                    self::SOURCE_RESTAURANT_ORDER, $model->id, $model->customer_name, null, null,
                    'Order #' . $model->id . ($model->table_label ? ' · Table ' . $model->table_label : ''),
                    $model->created_at
                );

            case self::SOURCE_SERVICE_BOOKING:
                return $this->row(
                    self::SOURCE_SERVICE_BOOKING, $model->id, $model->customer_name, $model->customer_email, $model->customer_phone,
                    $model->status_label ?? $model->status,
                    $model->created_at
                );

            case self::SOURCE_REVIEW:
                return $this->row(
                    self::SOURCE_REVIEW, $model->id, $model->author_name, $model->author_email, null,
                    $model->rating ? $model->rating . '★ review' : 'Review',
                    $model->created_at
                );

            case self::SOURCE_EVENT_INTEREST:
                return $this->row(
                    self::SOURCE_EVENT_INTEREST, $model->id,
                    $model->user?->name, $model->user?->email ?: $model->guest_email, null,
                    'Interested in event',
                    $model->created_at
                );
        }

        return $this->row($source, $model->id, null, null, null, null, $model->created_at ?? null);
    }

    protected function row(string $type, int $id, ?string $name, ?string $email, ?string $phone, ?string $context, $createdAt, ?string $organization = null): array
    {
        return [
            'source_type'  => $type,
            'source_id'    => $id,
            'name'         => $name ?: null,
            'email'        => $email ?: null,
            'phone'        => $phone ?: null,
            'organization' => $organization ?: null,
            'context'      => $context,
            'created_at'   => $createdAt,
            'source_label' => self::sourceLabels()[$type] ?? $type,
        ];
    }

    /**
     * Owner's link ids as a correlated SQL subquery rather than a PHP-side
     * pluck. Passing this to whereIn keeps the whole scope server-side, so an
     * account with tens of thousands of links never materialises the id set in
     * PHP nor ships it back as a giant IN (...) list.
     */
    protected function linkIdsSubquery(): Builder
    {
        return Link::withoutGlobalScope('workspace')
            ->where('user_id', $this->userId)
            ->select('id');
    }

    protected function formIds(): Collection
    {
        return $this->formIds ??= Form::where('user_id', $this->userId)->pluck('id');
    }

    /**
     * Best-effort name/email/phone/organization extraction from a free-form
     * submission payload.
     *
     * @return array{0:?string,1:?string,2:?string,3:?string} [name, email, phone, organization]
     */
    protected function extractIdentity(array $data): array
    {
        $lower = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) continue;
            $lower[mb_strtolower((string) $k)] = $v;
        }

        $email = null;
        foreach (['email', 'e-mail', 'email address', 'your email'] as $k) {
            if (!empty($lower[$k])) { $email = trim((string) $lower[$k]); break; }
        }
        if (!$email) {
            foreach ($lower as $v) {
                if (is_string($v) && filter_var(trim($v), FILTER_VALIDATE_EMAIL)) { $email = trim($v); break; }
            }
        }

        $phone = null;
        foreach (['phone', 'phone number', 'mobile', 'whatsapp', 'contact number'] as $k) {
            if (!empty($lower[$k])) { $phone = trim((string) $lower[$k]); break; }
        }

        $name = null;
        foreach (['name', 'full name', 'your name'] as $k) {
            if (!empty($lower[$k])) { $name = trim((string) $lower[$k]); break; }
        }
        if (!$name) {
            $first  = $lower['first name'] ?? $lower['first_name'] ?? null;
            $last   = $lower['last name'] ?? $lower['last_name'] ?? null;
            $joined = trim(($first ?? '') . ' ' . ($last ?? ''));
            if ($joined !== '') $name = $joined;
        }

        $organization = null;
        foreach (['company', 'organization', 'organisation', 'company name', 'business', 'business name', 'employer'] as $k) {
            if (!empty($lower[$k])) { $organization = trim((string) $lower[$k]); break; }
        }

        return [$name, $email, $phone, $organization];
    }
}
