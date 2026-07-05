<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\Link;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Mobile parity for the public events directory + paid ticketing
 * (Task #3589). Mirrors the web surfaces:
 *  - EventsDirectoryController  -> directory()
 *  - EventTicketPublicController -> show()/buy()/ticket()
 *  - EventTicketTierController  -> ownerTiers()/storeTier()/updateTier()/destroyTier()
 *  - EventCheckinController     -> scan()
 */
class EventTicketApiController extends Controller
{
    use ApiResponses;

    public function __construct(private MonetizationCheckout $checkout)
    {
    }

    // ─── Public directory + event detail ──────────────────────────

    public function directory(Request $request)
    {
        $q        = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $lat      = $request->query('lat');
        $lng      = $request->query('lng');
        $radiusKm = max(1, min(500, (int) $request->query('radius', 50)));

        $query = Link::query()
            ->where('type', 'ics')
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->with(['icsData', 'eventTicketTiers' => fn ($t) => $t->where('is_active', true)])
            ->whereHas('icsData', fn ($w) => $w->where('start_date', '>=', now()->subDay()));

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'ilike', $like)
                  ->orWhereHas('icsData', fn ($ics) => $ics->where('location', 'ilike', $like)
                      ->orWhere('description', 'ilike', $like));
            });
        }
        if ($category !== '') {
            $query->whereRaw("settings->>'event_category' = ?", [$category]);
        }
        $query->where(fn ($w) => $w->whereRaw("(settings->>'hide_from_directory') IS DISTINCT FROM 'true'"));

        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            $lat = (float) $lat;
            $lng = (float) $lng;
            $query->whereHas('icsData', function ($w) use ($lat, $lng, $radiusKm) {
                $w->whereNotNull('latitude')->whereNotNull('longitude')
                  ->whereRaw(
                      '(6371 * acos(least(1, greatest(-1,
                          cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                          + sin(radians(?)) * sin(radians(latitude))
                      )))) <= ?',
                      [$lat, $lng, $lat, $radiusKm],
                  );
            });
        }

        $events = $query->orderBy(
            \App\Modules\User\Models\IcsData::select('start_date')
                ->whereColumn('ics_data.link_id', 'links.id')->limit(1)
        )->paginate(24);

        return $this->ok([
            'items' => collect($events->items())->map(fn (Link $l) => $this->eventShape($l))->all(),
            'meta'  => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'total'        => $events->total(),
            ],
        ]);
    }

    public function show(Request $request, string $alias)
    {
        $link = Link::where('alias', $alias)->where('type', 'ics')->with(['icsData', 'eventTicketTiers'])->first();
        if (!$link) return $this->notFound('Event not found.');

        return $this->ok($this->eventShape($link, includeAllTiers: true));
    }

    // ─── Buy + ticket lookup ───────────────────────────────────────

    public function buy(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized('Sign in to buy tickets.', 'login_required');

        $link = Link::where('alias', $alias)->where('type', 'ics')->first();
        if (!$link) return $this->notFound('Event not found.');
        if (empty(($link->settings ?? [])['ticketing_enabled'])) {
            return $this->fail('This event does not sell tickets.', 422);
        }

        $data = $request->validate([
            'tier_id'  => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:50'],
        ]);

        $tier = EventTicketTier::where('link_id', $link->id)->where('is_active', true)->find($data['tier_id']);
        if (!$tier) return $this->fail('This ticket tier is not available.', 422);

        $result = $this->checkout->startEventTicket(
            $user,
            $tier,
            (int) ($data['quantity'] ?? 1),
            ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null],
            route('redirect.handle', ['alias' => $alias]),
        );

        return $this->ok(['checkout_url' => $result['url']]);
    }

    /** My purchased tickets (for the "My tickets" screen). */
    public function myTickets(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $tickets = EventTicket::where('buyer_user_id', $user->id)
            ->orWhere('attendee_email', $user->email)
            ->with(['tier', 'link.icsData'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->ok([
            'items' => collect($tickets->items())->map(fn (EventTicket $t) => $this->ticketShape($t))->all(),
            'meta'  => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'total'        => $tickets->total(),
            ],
        ]);
    }

    public function ticket(Request $request, string $alias, string $code)
    {
        $link = Link::where('alias', $alias)->where('type', 'ics')->first();
        if (!$link) return $this->notFound('Event not found.');

        $ticket = EventTicket::where('link_id', $link->id)->where('code', $code)->with(['tier', 'link.icsData'])->first();
        if (!$ticket) return $this->notFound('Ticket not found.');

        $checkinUrl = route('user.events.checkin.lookup', ['link' => $link->id, 'code' => $ticket->code]);
        $qrSvg = QrCode::format('svg')->size(260)->margin(1)->generate($checkinUrl);

        return $this->ok(array_merge($this->ticketShape($ticket), [
            'checkin_url' => $checkinUrl,
            'qr_svg'      => $qrSvg,
        ]));
    }

    // ─── Owner: tier CRUD + ticketing dashboard ────────────────────

    public function ownerTiers(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($linkId);
        if (!$link) return $this->notFound();

        $tiers = $link->eventTicketTiers()->withCount([
            'tickets as sold' => fn ($q) => $q->whereIn('status', ['valid', 'checked_in']),
        ])->get();

        $totals = [
            'gross_cents' => (int) $link->eventTickets()->whereIn('status', ['valid', 'checked_in'])->sum('price_cents'),
            'sold'        => (int) $link->eventTickets()->whereIn('status', ['valid', 'checked_in'])->sum('quantity'),
            'checked_in'  => (int) $link->eventTickets()->where('status', 'checked_in')->count(),
            'refunded'    => (int) $link->eventTickets()->where('status', 'refunded')->count(),
        ];

        return $this->ok([
            'tiers'  => $tiers->map(fn (EventTicketTier $t) => $this->tierShape($t))->all(),
            'totals' => $totals,
        ]);
    }

    public function storeTier(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($linkId);
        if (!$link) return $this->notFound();

        $data = $this->validateTier($request);
        $data['link_id'] = $link->id;
        $data['sort_order'] = (int) ($link->eventTicketTiers()->max('sort_order') ?? 0) + 1;

        $tier = EventTicketTier::create($data);

        return $this->created($this->tierShape($tier));
    }

    public function updateTier(Request $request, int $linkId, int $tierId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($linkId);
        if (!$link) return $this->notFound();

        $tier = EventTicketTier::where('link_id', $link->id)->find($tierId);
        if (!$tier) return $this->notFound();

        $tier->update($this->validateTier($request));

        return $this->ok($this->tierShape($tier));
    }

    public function destroyTier(Request $request, int $linkId, int $tierId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($linkId);
        if (!$link) return $this->notFound();

        $tier = EventTicketTier::where('link_id', $link->id)->find($tierId);
        if (!$tier) return $this->notFound();

        if ($tier->sold_count > 0) {
            return $this->fail('Cannot delete a tier that already has sales. Deactivate it instead.', 422);
        }
        $tier->delete();

        return $this->noContent();
    }

    // ─── Owner: door check-in ──────────────────────────────────────

    public function checkin(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($linkId);
        if (!$link) return $this->notFound();

        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $ticket = EventTicket::where('link_id', $link->id)->where('code', $data['code'])->with('tier')->first();
        if (!$ticket) {
            return $this->ok(['ok' => false, 'status' => 'not_found', 'message' => 'No ticket matches that code.']);
        }
        if ($ticket->status === EventTicket::STATUS_CHECKED_IN) {
            return $this->ok([
                'ok' => false, 'status' => 'already_checked_in',
                'message' => 'Already checked in at ' . optional($ticket->checked_in_at)->format('g:i A'),
                'ticket' => $this->ticketShape($ticket),
            ]);
        }
        if (in_array($ticket->status, [EventTicket::STATUS_CANCELLED, EventTicket::STATUS_REFUNDED], true)) {
            return $this->ok(['ok' => false, 'status' => $ticket->status, 'message' => 'This ticket was ' . $ticket->status . ' and is not valid.']);
        }

        $ticket->update(['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => now(), 'checked_in_by' => $user->id]);

        return $this->ok(['ok' => true, 'status' => 'checked_in', 'message' => 'Checked in successfully.', 'ticket' => $this->ticketShape($ticket)]);
    }

    /**
     * Live door progress (checked-in / sold, overall + per tier). Polled by
     * the mobile scanner so counts update as scans land across devices.
     */
    public function checkinProgress(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($linkId);
        if (!$link) return $this->notFound();

        return $this->ok(\App\Services\Events\EventCheckinProgress::for($link));
    }

    // ─── Shapes / helpers ──────────────────────────────────────────

    protected function validateTier(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'price'        => ['required', 'numeric', 'min:0', 'max:100000'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'capacity'     => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'sales_start'  => ['nullable', 'date'],
            'sales_end'    => ['nullable', 'date'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        return [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => (int) round(((float) $data['price']) * 100),
            'currency'    => strtoupper($data['currency'] ?? 'USD'),
            'capacity'    => $data['capacity'] ?? null,
            'sales_start' => $data['sales_start'] ?? null,
            'sales_end'   => $data['sales_end'] ?? null,
            'is_active'   => $request->has('is_active') ? $request->boolean('is_active') : true,
        ];
    }

    protected function eventShape(Link $link, bool $includeAllTiers = false): array
    {
        $ics = $link->icsData;
        $tiers = $includeAllTiers ? $link->eventTicketTiers : ($link->relationLoaded('eventTicketTiers') ? $link->eventTicketTiers : collect());

        return [
            'id'          => $link->id,
            'alias'       => $link->alias,
            'title'       => $link->title,
            'description' => $ics?->description,
            'location'    => $ics?->location,
            'start_date'  => optional($ics?->start_date)->toIso8601String(),
            'end_date'    => optional($ics?->end_date)->toIso8601String(),
            'latitude'    => $ics?->latitude,
            'longitude'   => $ics?->longitude,
            'category'    => ($link->settings ?? [])['event_category'] ?? null,
            'ticketing_enabled' => (bool) (($link->settings ?? [])['ticketing_enabled'] ?? false),
            'tiers'       => $tiers->map(fn (EventTicketTier $t) => $this->tierShape($t))->values()->all(),
        ];
    }

    protected function tierShape(EventTicketTier $tier): array
    {
        return [
            'id'          => $tier->id,
            'name'        => $tier->name,
            'description' => $tier->description,
            'price_cents' => $tier->price_cents,
            'currency'    => $tier->currency,
            'price_label' => $tier->priceLabel(),
            'is_free'     => $tier->isFree(),
            'capacity'    => $tier->capacity,
            'sold_count'  => $tier->sold_count,
            'remaining'   => $tier->remainingCapacity(),
            'is_sold_out' => $tier->isSoldOut(),
            'is_on_sale'  => $tier->isOnSale(),
            'is_active'   => (bool) $tier->is_active,
        ];
    }

    protected function ticketShape(EventTicket $ticket): array
    {
        return [
            'id'             => $ticket->id,
            'code'           => $ticket->code,
            'status'         => $ticket->status,
            'quantity'       => $ticket->quantity,
            'price_cents'    => $ticket->price_cents,
            'currency'       => $ticket->currency,
            'attendee_name'  => $ticket->attendee_name,
            'attendee_email' => $ticket->attendee_email,
            'checked_in_at'  => optional($ticket->checked_in_at)->toIso8601String(),
            'created_at'     => optional($ticket->created_at)->toIso8601String(),
            'tier'           => $ticket->relationLoaded('tier') && $ticket->tier ? [
                'id'   => $ticket->tier->id,
                'name' => $ticket->tier->name,
            ] : null,
            'event' => $ticket->relationLoaded('link') && $ticket->link ? [
                'id'         => $ticket->link->id,
                'alias'      => $ticket->link->alias,
                'title'      => $ticket->link->title,
                'location'   => $ticket->link->icsData?->location,
                'start_date' => optional($ticket->link->icsData?->start_date)->toIso8601String(),
            ] : null,
        ];
    }
}
