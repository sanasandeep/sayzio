<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\Subscriber;

/**
 * Read-side aggregation for the unified contact timeline (Task #6501).
 *
 * Collects every capture record linked to a contact (via the contact_id
 * columns) into display-ready groups, resolves the follower bridge at query
 * time (contact ⇄ Sayzio account via biolink_user_id or verified
 * linked_identifiers), and provides cheap per-contact activity counts for
 * the contacts list.
 */
class ContactActivityService
{
    /** Per-group item cap on the timeline (full lists live on native pages). */
    public const GROUP_LIMIT = 10;

    /**
     * Grouped activity for one contact. Every query is pinned to the
     * contact's owner (user_id / creator scope), so a stale or foreign
     * contact_id can never leak someone else's rows.
     *
     * Each item carries a `refs` map of record identifiers (link_id, alias,
     * form_id, thread_id, invoice_id…) so native clients can deep-link to the
     * matching in-app screen without parsing the web `url`.
     *
     * @return array<int, array{key:string,label:string,icon:string,count:int,items:array<int,array{title:string,subtitle:?string,date:?string,url:?string,refs:array<string,int|string>}>}>
     */
    public function timeline(Contact $contact): array
    {
        $ownerId = (int) $contact->user_id;
        $cid = (int) $contact->id;
        $groups = [];

        $push = function (string $key, string $label, string $icon, int $count, array $items) use (&$groups) {
            if ($count > 0) {
                $groups[] = compact('key', 'label', 'icon', 'count', 'items');
            }
        };

        // -- Subscriptions -------------------------------------------------
        $q = Subscriber::withoutGlobalScope('workspace')->where('user_id', $ownerId)->where('contact_id', $cid);
        $push('subscriptions', 'Subscriptions', 'bell', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($s) => [
                'title'    => $s->email ?: ($s->phone ?: 'Subscriber'),
                'subtitle' => $s->status ? ucfirst((string) $s->status) : null,
                'date'     => optional($s->created_at)->toIso8601String(),
                'url'      => route('user.subscribers.index'),
                'refs'     => (object) [],
            ])->all());

        // -- Form submissions ----------------------------------------------
        $q = FormSubmission::withoutGlobalScope('workspace')
            ->where('contact_id', $cid)
            ->whereIn('form_id', function ($sub) use ($ownerId) {
                $sub->select('id')->from('forms')->where('user_id', $ownerId);
            });
        $push('form_submissions', 'Form submissions', 'clipboard', (clone $q)->count(),
            $q->with('form')->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($s) => [
                'title'    => $s->form?->name ?: 'Form submission',
                'subtitle' => null,
                'date'     => optional($s->created_at)->toIso8601String(),
                'url'      => $s->form_id ? route('user.forms.submissions.show', [$s->form_id, $s->id]) : null,
                'refs'     => (object) array_filter(['form_id' => (int) $s->form_id]),
            ])->all());

        // -- Restaurant orders ----------------------------------------------
        $q = RestaurantOrder::withoutGlobalScope('workspace')
            ->where('contact_id', $cid)
            ->whereIn('link_id', $this->ownerLinkIds($ownerId));
        $push('restaurant_orders', 'Restaurant orders', 'coffee', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($o) => [
                'title'    => 'Order #' . $o->id,
                'subtitle' => ucfirst((string) $o->status),
                'date'     => optional($o->created_at)->toIso8601String(),
                'url'      => $o->link_id ? route('user.links.restaurant.orders', $o->link_id) . '?highlight=' . $o->id : null,
                'refs'     => (object) array_filter(['link_id' => (int) $o->link_id, 'order_id' => (int) $o->id]),
            ])->all());

        // -- Store orders -----------------------------------------------------
        $q = StoreOrder::withoutGlobalScope('workspace')
            ->where('contact_id', $cid)
            ->whereIn('link_id', $this->ownerLinkIds($ownerId));
        $push('store_orders', 'Store orders', 'shopping-bag', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($o) => [
                'title'    => 'Order request #' . $o->id,
                'subtitle' => ucfirst((string) $o->status),
                'date'     => optional($o->created_at)->toIso8601String(),
                'url'      => $o->link_id ? route('user.links.store.orders', $o->link_id) . '?highlight=' . $o->id : null,
                'refs'     => (object) array_filter(['link_id' => (int) $o->link_id, 'order_id' => (int) $o->id]),
            ])->all());

        // -- Bookings ---------------------------------------------------------
        $q = ServiceBookingRequest::withoutGlobalScope('workspace')
            ->where('contact_id', $cid)
            ->whereIn('link_id', $this->ownerLinkIds($ownerId));
        $push('bookings', 'Bookings', 'calendar', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($b) => [
                'title'    => 'Booking #' . $b->id,
                'subtitle' => ucfirst((string) $b->status),
                'date'     => optional($b->created_at)->toIso8601String(),
                'url'      => $b->link_id ? route('user.links.service-booking.bookings', $b->link_id) . '?highlight=' . $b->id : null,
                'refs'     => (object) array_filter(['link_id' => (int) $b->link_id, 'booking_id' => (int) $b->id]),
            ])->all());

        // -- RSVPs -------------------------------------------------------------
        $q = Rsvp::withoutGlobalScope('workspace')
            ->where('contact_id', $cid)
            ->whereIn('link_id', $this->ownerLinkIds($ownerId));
        $push('rsvps', 'Event RSVPs', 'check-circle', (clone $q)->count(),
            $q->with('link')->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($r) => [
                'title'    => $r->link?->title ?: 'RSVP',
                'subtitle' => ucfirst((string) $r->status),
                'date'     => optional($r->created_at)->toIso8601String(),
                'url'      => $r->link_id ? route('user.links.rsvps.index', $r->link_id) . '?highlight=' . $r->id : null,
                'refs'     => (object) array_filter([
                    'link_id' => (int) $r->link_id,
                    'alias'   => (string) ($r->link?->alias ?? ''),
                    'rsvp_id' => (int) $r->id,
                    // The attendees screen is keyed by account id — best-effort
                    // via the contact's attached Sayzio account.
                    'user_id' => (int) ($contact->biolink_user_id ?? 0),
                ]),
            ])->all());

        // -- Event tickets --------------------------------------------------------
        $q = EventTicket::withoutGlobalScope('workspace')
            ->where('contact_id', $cid)
            ->whereIn('link_id', $this->ownerLinkIds($ownerId));
        $push('event_tickets', 'Event tickets', 'tag', (clone $q)->count(),
            $q->with('link')->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($t) => [
                'title'    => $t->link?->title ?: 'Event ticket',
                'subtitle' => $t->code ? ('Ticket ' . $t->code) : null,
                'date'     => optional($t->created_at)->toIso8601String(),
                'url'      => $t->link_id ? route('user.links.ics.tickets', $t->link_id) . '?highlight=' . $t->id : null,
                'refs'     => (object) array_filter([
                    'link_id'   => (int) $t->link_id,
                    'alias'     => (string) ($t->link?->alias ?? ''),
                    'ticket_id' => (int) $t->id,
                    'user_id'   => (int) ($t->buyer_user_id ?: ($contact->biolink_user_id ?? 0)),
                ]),
            ])->all());

        // -- Product purchases -------------------------------------------------
        $q = ProductOrder::query()->where('creator_user_id', $ownerId)->where('contact_id', $cid);
        $push('product_orders', 'Purchases', 'shopping-cart', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($o) => [
                'title'    => 'Purchase #' . $o->id,
                'subtitle' => ucfirst((string) $o->status),
                'date'     => optional($o->created_at)->toIso8601String(),
                'url'      => route('user.monetization.orders'),
                'refs'     => (object) [],
            ])->all());

        // -- Reviews ----------------------------------------------------------
        $q = Review::withoutGlobalScope('workspace')->where('user_id', $ownerId)->where('contact_id', $cid);
        $push('reviews', 'Reviews', 'star', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($r) => [
                'title'    => ($r->rating ? ($r->rating . '★ ') : '') . str(strip_tags((string) $r->body))->limit(60),
                'subtitle' => ucfirst((string) $r->status),
                'date'     => optional($r->created_at)->toIso8601String(),
                'url'      => $r->link_id ? route('user.links.reviews.editor', $r->link_id) : null,
                'refs'     => (object) array_filter(['link_id' => (int) $r->link_id]),
            ])->all());

        // -- Conversations ------------------------------------------------------
        $q = InboxThread::withoutGlobalScope('workspace')->where('user_id', $ownerId)->where('contact_id', $cid);
        $push('conversations', 'Conversations', 'message-circle', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($t) => [
                'title'    => $t->subject ?: ($t->sender_name ?: 'Conversation'),
                'subtitle' => $t->sender_email,
                'date'     => optional($t->created_at)->toIso8601String(),
                'url'      => route('user.inbox.index'),
                'refs'     => (object) ['thread_id' => (int) $t->id],
            ])->all());

        // -- Invoices (contact_id existed pre-6501) -----------------------------
        $q = Invoice::withoutGlobalScope('workspace')->where('user_id', $ownerId)->where('contact_id', $cid);
        $push('invoices', 'Invoices', 'file-text', (clone $q)->count(),
            $q->latest('id')->limit(self::GROUP_LIMIT)->get()->map(fn ($i) => [
                'title'    => $i->number ?: ('Invoice #' . $i->id),
                'subtitle' => ucfirst((string) $i->status),
                'date'     => optional($i->created_at)->toIso8601String(),
                'url'      => route('user.client-invoices.dashboard'),
                'refs'     => (object) ['invoice_id' => (int) $i->id],
            ])->all());

        return $groups;
    }

    /**
     * Follower bridge (read-side, no schema): is this contact also a Sayzio
     * account following the owner? Resolves via the explicit biolink
     * attachment first, then verified linked_identifiers by email/phone.
     *
     * @return array{is_follower:bool, followed_at:?string, matched_user_id:?int}
     */
    public function followerBridge(Contact $contact): array
    {
        $ownerId = (int) $contact->user_id;
        $matched = $contact->biolink_user_id ? $contact->biolinkUser : null;

        if (!$matched) {
            foreach ($contact->emails as $email) {
                $matched = LinkedIdentifier::resolveUser('email', (string) $email->value);
                if ($matched) {
                    break;
                }
            }
        }
        if (!$matched) {
            foreach ($contact->phones as $phone) {
                $matched = LinkedIdentifier::resolveUser('phone', (string) ($phone->value_e164 ?: $phone->value));
                if ($matched) {
                    break;
                }
            }
        }

        if (!$matched) {
            return ['is_follower' => false, 'followed_at' => null, 'matched_user_id' => null];
        }

        $follow = Follow::withoutGlobalScope('workspace')
            ->where('follower_id', $matched->id)
            ->where('creator_id', $ownerId)
            ->first();

        return [
            'is_follower'     => (bool) $follow,
            'followed_at'     => $follow?->created_at?->toIso8601String(),
            'matched_user_id' => (int) $matched->id,
        ];
    }

    /**
     * Total linked-activity count per contact for a page of the contacts
     * list. One grouped query per capture table, all hitting the new
     * contact_id indexes.
     *
     * @param  array<int,int> $contactIds
     * @return array<int,int> map contact_id => activity count
     */
    public function countsFor(int $ownerUserId, array $contactIds): array
    {
        $contactIds = array_values(array_filter(array_map('intval', $contactIds)));
        if (!$contactIds) {
            return [];
        }

        $totals = array_fill_keys($contactIds, 0);
        $tally = function ($query) use (&$totals, $contactIds) {
            $rows = $query->whereIn('contact_id', $contactIds)
                ->selectRaw('contact_id, COUNT(*) as c')
                ->groupBy('contact_id')
                ->pluck('c', 'contact_id');
            foreach ($rows as $id => $c) {
                $totals[(int) $id] = ($totals[(int) $id] ?? 0) + (int) $c;
            }
        };

        $linkIds = $this->ownerLinkIds($ownerUserId);

        $tally(Subscriber::withoutGlobalScope('workspace')->where('user_id', $ownerUserId));
        $tally(FormSubmission::withoutGlobalScope('workspace')->whereIn('form_id', function ($sub) use ($ownerUserId) {
            $sub->select('id')->from('forms')->where('user_id', $ownerUserId);
        }));
        $tally(RestaurantOrder::withoutGlobalScope('workspace')->whereIn('link_id', $linkIds));
        $tally(StoreOrder::withoutGlobalScope('workspace')->whereIn('link_id', $linkIds));
        $tally(ServiceBookingRequest::withoutGlobalScope('workspace')->whereIn('link_id', $linkIds));
        $tally(Rsvp::withoutGlobalScope('workspace')->whereIn('link_id', $linkIds));
        $tally(EventTicket::withoutGlobalScope('workspace')->whereIn('link_id', $linkIds));
        $tally(ProductOrder::query()->where('creator_user_id', $ownerUserId));
        $tally(Review::withoutGlobalScope('workspace')->where('user_id', $ownerUserId));
        $tally(InboxThread::withoutGlobalScope('workspace')->where('user_id', $ownerUserId));
        $tally(Invoice::withoutGlobalScope('workspace')->where('user_id', $ownerUserId));

        return $totals;
    }

    /**
     * Derived-table builder for sorting/filtering the contacts index by
     * linked activity: one UNION ALL pass over the same capture tables as
     * countsFor(), grouped by contact_id, then summed. Meant for
     * leftJoinSub() against contacts — a single bulk query, never per-row
     * correlated subqueries.
     *
     * Returns a query builder yielding (contact_id, activity_total) rows
     * for the owner's contacts that have at least one linked record.
     */
    public function activityTotalsQuery(int $ownerUserId): \Illuminate\Database\Query\Builder
    {
        $linkIds = $this->ownerLinkIds($ownerUserId);

        $parts = [
            Subscriber::withoutGlobalScope('workspace')->where('user_id', $ownerUserId),
            FormSubmission::withoutGlobalScope('workspace')->whereIn('form_id', function ($sub) use ($ownerUserId) {
                $sub->select('id')->from('forms')->where('user_id', $ownerUserId);
            }),
            RestaurantOrder::withoutGlobalScope('workspace')->whereIn('link_id', clone $linkIds),
            StoreOrder::withoutGlobalScope('workspace')->whereIn('link_id', clone $linkIds),
            ServiceBookingRequest::withoutGlobalScope('workspace')->whereIn('link_id', clone $linkIds),
            Rsvp::withoutGlobalScope('workspace')->whereIn('link_id', clone $linkIds),
            EventTicket::withoutGlobalScope('workspace')->whereIn('link_id', clone $linkIds),
            ProductOrder::query()->where('creator_user_id', $ownerUserId),
            Review::withoutGlobalScope('workspace')->where('user_id', $ownerUserId),
            InboxThread::withoutGlobalScope('workspace')->where('user_id', $ownerUserId),
            Invoice::withoutGlobalScope('workspace')->where('user_id', $ownerUserId),
        ];

        $union = null;
        foreach ($parts as $part) {
            $q = $part->whereNotNull('contact_id')
                ->selectRaw('contact_id, COUNT(*) as c')
                ->groupBy('contact_id')
                ->toBase();
            $union = $union ? $union->unionAll($q) : $q;
        }

        return \Illuminate\Support\Facades\DB::query()
            ->fromSub($union, 'contact_activity_union')
            ->selectRaw('contact_id, SUM(c) as activity_total')
            ->groupBy('contact_id');
    }

    /** Subquery of the owner's link ids (account-wide, workspace-agnostic). */
    protected function ownerLinkIds(int $ownerUserId)
    {
        return \App\Modules\User\Models\Link::withoutGlobalScope('workspace')
            ->where('user_id', $ownerUserId)
            ->select('id');
    }
}
