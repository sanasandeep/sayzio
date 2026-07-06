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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Aggregates captured people across 8 capture surfaces into a single
 * reviewable "Leads" queue. A lead is "pending" as long as no
 * {@see Lead} state row exists for its (source_type, source_id) pair —
 * approving or dismissing writes that row so the item drops out of the
 * pending view without ever mutating the source record itself.
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
        return $this->collectAll(null, '')->count();
    }

    public function paginate(array $filters, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $sourceFilter = $filters['source'] ?? null;
        $search       = trim((string) ($filters['q'] ?? ''));

        $all = $this->collectAll($sourceFilter, $search)
            ->sortByDesc(fn ($i) => optional($i['created_at'])->getTimestamp() ?? 0)
            ->values();

        $total     = $all->count();
        $pageItems = $all->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems, $total, $perPage, $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /** Find a single pending item by source_type/source_id (for the approve/dismiss actions). */
    public function find(string $sourceType, int $sourceId): ?array
    {
        return $this->collectAll($sourceType, '')
            ->first(fn ($i) => $i['source_id'] === $sourceId);
    }

    /** Pending-lead count per source, in one pass (used for the filter chips). */
    public function countsBySource(): array
    {
        $counts = array_fill_keys(self::SOURCES, 0);
        foreach ($this->collectAll(null, '') as $item) {
            $counts[$item['source_type']] = ($counts[$item['source_type']] ?? 0) + 1;
        }
        return $counts;
    }

    protected function collectAll(?string $sourceFilter, string $search): Collection
    {
        $linkIds = Link::withoutGlobalScope('workspace')
            ->where('user_id', $this->userId)
            ->pluck('id');

        $done = $this->doneKeys();

        $items = collect();

        if (!$sourceFilter || $sourceFilter === self::SOURCE_RSVP) {
            $items = $items->concat($this->rsvps($linkIds, $done));
        }
        if (!$sourceFilter || $sourceFilter === self::SOURCE_FORM) {
            $items = $items->concat($this->formSubmissions($done));
        }
        if (!$sourceFilter || $sourceFilter === self::SOURCE_SUBSCRIBER) {
            $items = $items->concat($this->subscribers($done));
        }
        if (!$sourceFilter || $sourceFilter === self::SOURCE_STORE_ORDER) {
            $items = $items->concat($this->storeOrders($linkIds, $done));
        }
        if (!$sourceFilter || $sourceFilter === self::SOURCE_RESTAURANT_ORDER) {
            $items = $items->concat($this->restaurantOrders($linkIds, $done));
        }
        if (!$sourceFilter || $sourceFilter === self::SOURCE_SERVICE_BOOKING) {
            $items = $items->concat($this->serviceBookings($linkIds, $done));
        }
        if (!$sourceFilter || $sourceFilter === self::SOURCE_REVIEW) {
            $items = $items->concat($this->reviews($done));
        }
        if (!$sourceFilter || $sourceFilter === self::SOURCE_EVENT_INTEREST) {
            $items = $items->concat($this->eventInterests($linkIds, $done));
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = $items->filter(function ($i) use ($needle) {
                foreach ([$i['name'], $i['email'], $i['phone'], $i['context']] as $f) {
                    if ($f && str_contains(mb_strtolower((string) $f), $needle)) return true;
                }
                return false;
            });
        }

        return $items->values();
    }

    /** @return array<string,true> keyed "{source_type}:{source_id}" for already-acted-on leads. */
    protected function doneKeys(): array
    {
        return Lead::withoutGlobalScope('workspace')
            ->where('user_id', $this->userId)
            ->get(['source_type', 'source_id'])
            ->reduce(function (array $acc, Lead $l) {
                $acc[$l->source_type . ':' . $l->source_id] = true;
                return $acc;
            }, []);
    }

    protected function isDone(array $done, string $type, int $id): bool
    {
        return isset($done[$type . ':' . $id]);
    }

    protected function row(string $type, int $id, ?string $name, ?string $email, ?string $phone, ?string $context, $createdAt): array
    {
        return [
            'source_type'  => $type,
            'source_id'    => $id,
            'name'         => $name ?: null,
            'email'        => $email ?: null,
            'phone'        => $phone ?: null,
            'context'      => $context,
            'created_at'   => $createdAt,
            'source_label' => self::sourceLabels()[$type] ?? $type,
        ];
    }

    protected function rsvps(Collection $linkIds, array $done): Collection
    {
        return Rsvp::whereIn('link_id', $linkIds)
            ->with('link:id,alias,title')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($r) => !$this->isDone($done, self::SOURCE_RSVP, $r->id))
            ->map(fn ($r) => $this->row(
                self::SOURCE_RSVP, $r->id, $r->name, $r->email, $r->phone,
                trim(($r->response ? ucfirst($r->response) : '') . ($r->link?->alias ? ' · /' . $r->link->alias : '')) ?: null,
                $r->created_at
            ));
    }

    protected function formSubmissions(array $done): Collection
    {
        $formIds = Form::where('user_id', $this->userId)->pluck('id');

        return FormSubmission::whereIn('form_id', $formIds)
            ->where('is_spam', false)
            ->completed()
            ->with('form:id,title')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($f) => !$this->isDone($done, self::SOURCE_FORM, $f->id))
            ->map(function ($f) {
                [$name, $email, $phone] = $this->extractIdentity((array) ($f->data ?? []));
                return $this->row(
                    self::SOURCE_FORM, $f->id, $name, $email, $phone,
                    $f->form?->title,
                    $f->created_at
                );
            });
    }

    /** Best-effort name/email/phone extraction from a free-form submission payload. */
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

        return [$name, $email, $phone];
    }

    protected function subscribers(array $done): Collection
    {
        return Subscriber::where('user_id', $this->userId)
            ->where('is_spam', false)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($s) => !$this->isDone($done, self::SOURCE_SUBSCRIBER, $s->id))
            ->map(fn ($s) => $this->row(
                self::SOURCE_SUBSCRIBER, $s->id, $s->name, $s->email, $s->phone,
                $s->type ? ucfirst(str_replace('_', ' ', $s->type)) : null,
                $s->subscribed_at ?? $s->created_at
            ));
    }

    protected function storeOrders(Collection $linkIds, array $done): Collection
    {
        return StoreOrder::whereIn('link_id', $linkIds)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($o) => !$this->isDone($done, self::SOURCE_STORE_ORDER, $o->id))
            ->map(function ($o) {
                $contact = trim((string) $o->customer_contact);
                $email   = filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact : null;
                $phone   = ($contact !== '' && !$email) ? $contact : null;
                return $this->row(
                    self::SOURCE_STORE_ORDER, $o->id, $o->customer_name, $email, $phone,
                    'Order #' . $o->id . ' · ' . ($o->status_label ?? $o->status),
                    $o->created_at
                );
            });
    }

    protected function restaurantOrders(Collection $linkIds, array $done): Collection
    {
        return RestaurantOrder::whereIn('link_id', $linkIds)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($o) => !$this->isDone($done, self::SOURCE_RESTAURANT_ORDER, $o->id))
            ->map(fn ($o) => $this->row(
                self::SOURCE_RESTAURANT_ORDER, $o->id, $o->customer_name, null, null,
                'Order #' . $o->id . ($o->table_label ? ' · Table ' . $o->table_label : ''),
                $o->created_at
            ));
    }

    protected function serviceBookings(Collection $linkIds, array $done): Collection
    {
        return ServiceBookingRequest::whereIn('link_id', $linkIds)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($b) => !$this->isDone($done, self::SOURCE_SERVICE_BOOKING, $b->id))
            ->map(fn ($b) => $this->row(
                self::SOURCE_SERVICE_BOOKING, $b->id, $b->customer_name, $b->customer_email, $b->customer_phone,
                $b->status_label ?? $b->status,
                $b->created_at
            ));
    }

    protected function reviews(array $done): Collection
    {
        return Review::where('user_id', $this->userId)
            ->where('is_spam', false)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($r) => !$this->isDone($done, self::SOURCE_REVIEW, $r->id))
            ->map(fn ($r) => $this->row(
                self::SOURCE_REVIEW, $r->id, $r->author_name, $r->author_email, null,
                $r->rating ? $r->rating . '★ review' : 'Review',
                $r->created_at
            ));
    }

    protected function eventInterests(Collection $linkIds, array $done): Collection
    {
        return EventInterest::whereIn('link_id', $linkIds)
            ->where('status', EventInterest::INTERESTED)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($e) => !$this->isDone($done, self::SOURCE_EVENT_INTEREST, $e->id))
            ->map(fn ($e) => $this->row(
                self::SOURCE_EVENT_INTEREST, $e->id,
                $e->user?->name, $e->user?->email ?: $e->guest_email, null,
                'Interested in event',
                $e->created_at
            ));
    }
}
