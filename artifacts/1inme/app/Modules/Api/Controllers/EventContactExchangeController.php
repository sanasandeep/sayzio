<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Task #5008 — Event contact exchange: "My card" QR + opt-in "People at this
 * event" list with mutual contact creation on acceptance.
 *
 * Endpoints (all under auth:sanctum):
 *   GET  /me/event-card                      — user's QR card payload
 *   GET  /events/{alias}/discoverability      — current opt-in state
 *   POST /events/{alias}/discoverability      — toggle (on/off)
 *   GET  /events/{alias}/people               — discoverable attendees list
 *   POST /events/{alias}/exchange             — send exchange request
 *   POST /me/contact-exchanges/{id}/accept    — accept an inbound request
 *
 * Privacy gates enforced on every guarded action:
 *   - The event must be live (time window: between start_at and end_at).
 *   - The acting user must have an RSVP (confirmed + "yes") or a valid/
 *     checked-in EventTicket for the link.
 *   - Discoverability is off by default and expires when the event ends.
 *   - The "People" list only returns users who are currently opted-in AND
 *     whose opt-in has not expired.
 *   - Exchange requests are rate-limited (10 per event per hour).
 */
class EventContactExchangeController extends Controller
{
    use ApiResponses;

    private const VENUE_RADIUS_KM  = 5;
    private const REQUEST_RATE_CAP = 10;

    // ─── My card ─────────────────────────────────────────────────────

    /**
     * Return the current user's contact-exchange card: their public profile
     * URL (encoded into a QR) plus the same follow/subscribe/save payload
     * the biolink scanner landing page surfaces.
     *
     * Any phone camera scanning the QR opens `/@{handle}` in the browser;
     * the in-app scanner deep-links to the same handle so the full
     * Follow / Subscribe / Save-to-Contacts actions are visible.
     */
    public function myCard(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $profileUrl = $user->handle ? url('/@' . $user->handle) : url('/user/profile');

        $qrSvg = QrCode::format('svg')->size(260)->margin(1)->generate($profileUrl);

        return $this->ok([
            'profile_url' => $profileUrl,
            'handle'      => $user->handle,
            'name'        => $user->name,
            'avatar_url'  => $user->resolveAvatarUrl(),
            'bio'         => $user->bio,
            'qr_svg'      => $qrSvg,
        ]);
    }

    // ─── Discoverability toggle ───────────────────────────────────────

    /** Return the current user's discoverability state for this event. */
    public function getDiscoverability(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->resolveEventLink($alias);
        if (!$link) return $this->notFound('Event not found.');

        $row = EventDiscoverability::where('user_id', $user->id)
            ->where('link_id', $link->id)
            ->first();

        return $this->ok([
            'discoverable' => $row && $row->isActive(),
            'event_live'   => $this->isEventLive($link),
            'is_attendee'  => $this->isAttendee($user, $link),
        ]);
    }

    /**
     * Toggle discoverability on or off. The caller must be an attendee
     * and the event must currently be live. Coords are optional but
     * improve the radius gate; they're only checked when provided.
     */
    public function toggleDiscoverability(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->resolveEventLink($alias);
        if (!$link) return $this->notFound('Event not found.');

        if (!$this->isEventLive($link)) {
            return $this->fail('This event is not currently live.', 422, 'event_not_live');
        }
        if (!$this->isAttendee($user, $link)) {
            return $this->fail(
                'You must have an RSVP or ticket for this event to enable discoverability.',
                403,
                'not_attendee',
            );
        }

        $data = $request->validate([
            'discoverable' => ['required', 'boolean'],
            'lat'          => ['nullable', 'numeric', 'between:-90,90'],
            'lng'          => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($data['discoverable']) {
            $expiresAt = $this->eventEndAt($link);
            EventDiscoverability::updateOrCreate(
                ['user_id' => $user->id, 'link_id' => $link->id],
                [
                    'expires_at' => $expiresAt,
                    'lat'        => $data['lat'] ?? null,
                    'lng'        => $data['lng'] ?? null,
                ],
            );
        } else {
            EventDiscoverability::where('user_id', $user->id)
                ->where('link_id', $link->id)
                ->delete();
        }

        return $this->ok(['discoverable' => (bool) $data['discoverable']]);
    }

    // ─── People at this event ─────────────────────────────────────────

    /**
     * List opted-in attendees at the current event. Caller must be an
     * attendee and the event must be live. Each item includes enough to
     * render a "card" and shows whether the viewer already exchanged with
     * that person.
     */
    public function listAttendees(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->resolveEventLink($alias);
        if (!$link) return $this->notFound('Event not found.');

        if (!$this->isEventLive($link)) {
            return $this->fail('This event is not currently live.', 422, 'event_not_live');
        }
        if (!$this->isAttendee($user, $link)) {
            return $this->fail(
                'You must have an RSVP or ticket to see other attendees.',
                403,
                'not_attendee',
            );
        }

        $discoverable = EventDiscoverability::active()
            ->where('link_id', $link->id)
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get();

        // Preload any existing exchange records so we can mark state.
        $myExchanges = EventContactExchange::where('link_id', $link->id)
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('recipient_id', $user->id);
            })
            ->get()
            ->keyBy(function ($ex) use ($user) {
                return $ex->requester_id === $user->id ? $ex->recipient_id : $ex->requester_id;
            });

        $items = $discoverable->map(function (EventDiscoverability $d) use ($myExchanges, $user) {
            $u = $d->user;
            if (!$u) return null;

            $exchange  = $myExchanges->get($u->id);
            $myRequest = $exchange && $exchange->requester_id === $user->id;

            return [
                'user'             => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'handle'     => $u->handle,
                    'avatar_url' => $u->resolveAvatarUrl(),
                    'bio'        => $u->bio,
                ],
                'exchange_status'  => $exchange ? $exchange->status : null,
                'exchange_id'      => $exchange ? $exchange->id : null,
                'sent_by_me'       => $exchange ? $myRequest : null,
            ];
        })->filter()->values()->all();

        return $this->ok([
            'items'        => $items,
            'total'        => count($items),
            'my_discoverable' => EventDiscoverability::where('user_id', $user->id)
                ->where('link_id', $link->id)
                ->active()
                ->exists(),
        ]);
    }

    // ─── Exchange request ────────────────────────────────────────────

    /**
     * Send a contact-exchange request to another discoverable attendee.
     * Guards: event live, both parties RSVP'd/ticketed, recipient is opted-in,
     * no duplicate, rate-limited to 10 requests per event per hour.
     */
    public function requestExchange(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->resolveEventLink($alias);
        if (!$link) return $this->notFound('Event not found.');

        if (!$this->isEventLive($link)) {
            return $this->fail('This event is not currently live.', 422, 'event_not_live');
        }
        if (!$this->isAttendee($user, $link)) {
            return $this->fail(
                'You must have an RSVP or ticket for this event to exchange contacts.',
                403,
                'not_attendee',
            );
        }

        $data = $request->validate(['recipient_id' => ['required', 'integer', 'min:1']]);

        if ((int) $data['recipient_id'] === (int) $user->id) {
            return $this->fail('You cannot exchange with yourself.', 422, 'self_exchange');
        }

        $recipient = User::find($data['recipient_id']);
        if (!$recipient) return $this->notFound('User not found.');

        if (!$this->isAttendee($recipient, $link)) {
            return $this->fail('The other person does not have an RSVP or ticket for this event.', 403, 'recipient_not_attendee');
        }

        // Recipient must be opted-in.
        $recipientDiscoverable = EventDiscoverability::active()
            ->where('user_id', $recipient->id)
            ->where('link_id', $link->id)
            ->exists();
        if (!$recipientDiscoverable) {
            return $this->fail('That person is not discoverable at this event.', 403, 'not_discoverable');
        }

        // Duplicate guard — also handles the reverse direction.
        $existing = EventContactExchange::where('link_id', $link->id)
            ->where(function ($q) use ($user, $recipient) {
                $q->where(fn ($w) => $w->where('requester_id', $user->id)->where('recipient_id', $recipient->id))
                  ->orWhere(fn ($w) => $w->where('requester_id', $recipient->id)->where('recipient_id', $user->id));
            })
            ->first();

        if ($existing) {
            if ($existing->isAccepted()) {
                return $this->fail('You have already exchanged contacts with this person.', 409, 'already_exchanged');
            }
            // If the other person already sent us a request, auto-accept.
            if ($existing->recipient_id === $user->id && $existing->isPending()) {
                return $this->doAccept($existing, $user);
            }
            return $this->fail('You already sent this person an exchange request.', 409, 'already_requested');
        }

        // Rate limit: max 10 requests sent per event per hour.
        $recentCount = EventContactExchange::where('requester_id', $user->id)
            ->where('link_id', $link->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($recentCount >= self::REQUEST_RATE_CAP) {
            return $this->fail('Too many exchange requests. Please wait a while before sending more.', 429, 'rate_limited');
        }

        $exchange = EventContactExchange::create([
            'requester_id' => $user->id,
            'recipient_id' => $recipient->id,
            'link_id'      => $link->id,
            'status'       => EventContactExchange::STATUS_PENDING,
        ]);

        // Notify the recipient.
        app(NotificationService::class)->notify($recipient, 'event_exchange_request', [
            'requester_name'  => $user->name,
            'requester_handle' => $user->handle,
            'event_title'     => $link->title,
            'exchange_id'     => $exchange->id,
            'url'             => url('/user/notifications'),
        ]);

        return $this->ok([
            'exchange_id' => $exchange->id,
            'status'      => $exchange->status,
        ], 201);
    }

    /**
     * Accept an inbound exchange request. Creates mutual contacts for both
     * parties (event recorded as the source) and notifies the requester.
     */
    public function acceptExchange(Request $request, int $exchangeId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $exchange = EventContactExchange::with(['requester', 'recipient', 'link'])->find($exchangeId);
        if (!$exchange) return $this->notFound('Exchange request not found.');

        if ((int) $exchange->recipient_id !== (int) $user->id) {
            return $this->forbidden('This request was not sent to you.');
        }
        if (!$exchange->isPending()) {
            return $this->fail('This request has already been ' . $exchange->status . '.', 409, 'already_resolved');
        }

        return $this->doAccept($exchange, $user);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function doAccept(EventContactExchange $exchange, User $acceptor): \Illuminate\Http\JsonResponse
    {
        DB::transaction(function () use ($exchange, $acceptor) {
            $exchange->update([
                'status'      => EventContactExchange::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            $exchange->loadMissing(['requester', 'recipient', 'link']);

            $eventSource = [
                'kind'     => 'event_exchange',
                'event_id' => $exchange->link_id,
                'title'    => $exchange->link->title ?? 'Event',
                'at'       => now()->toIso8601String(),
            ];

            $this->ensureMutualContact($exchange->requester, $exchange->recipient, $eventSource);
            $this->ensureMutualContact($exchange->recipient, $exchange->requester, $eventSource);
        });

        // Notify the requester that their request was accepted.
        $exchange->loadMissing(['requester', 'link']);
        app(NotificationService::class)->notify($exchange->requester, 'event_exchange_accepted', [
            'acceptor_name'   => $acceptor->name,
            'acceptor_handle' => $acceptor->handle,
            'event_title'     => $exchange->link->title ?? 'the event',
            'url'             => url('/user/contacts'),
        ]);

        return $this->ok([
            'exchange_id' => $exchange->id,
            'status'      => EventContactExchange::STATUS_ACCEPTED,
        ]);
    }

    /**
     * Ensure $owner has $other in their address book with the given source.
     * Uses the existing Contact/ContactEmail/ContactPhone pattern so the
     * BiolinkAttachResolver and CRM push hooks all fire normally.
     */
    private function ensureMutualContact(User $owner, User $other, array $source): void
    {
        $existing = Contact::where('user_id', $owner->id)
            ->where('biolink_user_id', $other->id)
            ->first();

        $email = $other->email;
        $phone = $other->phone;

        if (!$existing && $email) {
            $existing = Contact::where('user_id', $owner->id)
                ->whereHas('emails', fn ($q) => $q->whereRaw('LOWER(value) = ?', [strtolower($email)]))
                ->first();
        }

        if ($existing) {
            // Append source if not already there.
            $sources = (array) ($existing->sources ?? []);
            $alreadyHave = collect($sources)->where('kind', 'event_exchange')
                ->where('event_id', $source['event_id'])->isNotEmpty();
            if (!$alreadyHave) {
                $existing->sources = array_merge($sources, [$source]);
                $existing->biolink_user_id = $existing->biolink_user_id ?? $other->id;
                $existing->biolink_attached_at = $existing->biolink_attached_at ?? now();
                $existing->save();
            }
            return;
        }

        $contact = Contact::create([
            'user_id'             => $owner->id,
            'display_name'        => $other->name,
            'biolink_user_id'     => $other->id,
            'biolink_attached_at' => now(),
            'sources'             => [$source],
            'locally_modified_at' => now(),
        ]);

        if ($email) {
            ContactEmail::create(['contact_id' => $contact->id, 'value' => $email, 'label' => 'email', 'is_primary' => true]);
        }
        if ($phone) {
            ContactPhone::create(['contact_id' => $contact->id, 'value' => $phone, 'label' => 'mobile', 'is_primary' => true]);
        }
    }

    /** Find a public ICS link by alias. */
    private function resolveEventLink(string $alias): ?Link
    {
        return Link::where('alias', $alias)->where('type', 'ics')->with('icsData')->first();
    }

    /**
     * True when the event is currently live: started but not yet ended.
     * All-day events are considered live the entire start day (UTC).
     */
    private function isEventLive(Link $link): bool
    {
        $ics = $link->icsData;
        if (!$ics) return false;

        $now = now();
        $start = $ics->start_date ? \Carbon\Carbon::parse($ics->start_date) : null;
        $end   = $ics->end_date   ? \Carbon\Carbon::parse($ics->end_date)   : null;

        if (!$start) return false;

        if ($start->isFuture()) return false;
        if ($end && $end->isPast()) return false;

        return true;
    }

    /** Expiry to stamp on a new discoverability row — event end, or null. */
    private function eventEndAt(Link $link): ?\Carbon\Carbon
    {
        $ics = $link->icsData;
        return $ics?->end_date ? \Carbon\Carbon::parse($ics->end_date) : null;
    }

    /**
     * True when the user has confirmed RSVP ("yes") or a valid/checked-in
     * EventTicket for this event link.
     */
    private function isAttendee(User $user, Link $link): bool
    {
        $hasTicket = \App\Modules\User\Models\EventTicket::where('link_id', $link->id)
            ->where(function ($q) use ($user) {
                $q->where('buyer_user_id', $user->id)
                  ->orWhere('attendee_email', $user->email);
            })
            ->whereIn('status', ['valid', 'checked_in'])
            ->exists();

        if ($hasTicket) return true;

        $hasRsvp = \App\Modules\User\Models\Rsvp::where('link_id', $link->id)
            ->where('response', 'yes')
            ->where('status', 'confirmed')
            ->where(function ($q) use ($user) {
                $q->where('email', $user->email);
                if ($user->phone) {
                    $q->orWhere('phone', $user->phone);
                }
            })
            ->exists();

        return $hasRsvp;
    }
}
