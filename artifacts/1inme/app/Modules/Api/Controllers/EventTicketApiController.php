<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Support\EventCategories;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
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
        $tag      = mb_strtolower(ltrim(trim((string) $request->query('tag', '')), '#'));
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
                      ->orWhere('description', 'ilike', $like)
                      ->orWhereRaw('hashtags::text ilike ?', [$like]));
            });
        }
        if ($category !== '') {
            $query->whereRaw("settings->>'event_category' = ?", [$category]);
        }
        if ($tag !== '') {
            $query->whereHas('icsData', fn ($ics) => $ics->whereRaw('hashtags::text ilike ?', ['%"' . $tag . '"%']));
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
            // Curated category catalogue (EventCategories::CATEGORIES) so mobile can
            // render the same tappable filter chips as the web /events directory
            // without a separate round-trip. slug is what the `category` filter
            // param expects; label/icon drive the chip UI.
            'categories' => collect(EventCategories::CATEGORIES)
                ->map(fn (array $meta, string $slug) => [
                    'slug'  => $slug,
                    'label' => $meta['label'],
                    'icon'  => $meta['icon'],
                ])->values()->all(),
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

        $link = Link::where('alias', $alias)->where('type', 'ics')->with('icsData')->first();
        if (!$link) return $this->notFound('Event not found.');
        if (empty(($link->settings ?? [])['ticketing_enabled'])) {
            return $this->fail('This event does not sell tickets.', 422);
        }

        // Badge-gated events (Task #3593): mirrors RedirectController::rsvpSubmit.
        $requiredBadgeId = $link->icsData?->required_badge_id;
        if ($requiredBadgeId && !$user->accountBadges()->where('account_badges.id', $requiredBadgeId)->exists()) {
            return $this->fail('This event requires an invite badge you don\'t have yet.', 403);
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

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.view')) return $this->forbidden();

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

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

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

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        $tier = EventTicketTier::where('link_id', $link->id)->find($tierId);
        if (!$tier) return $this->notFound();

        $data = $this->validateTier($request);
        // If the owner raises capacity, clear the capacity-alert stamps so a
        // subsequent re-fill re-alerts (Task #3623).
        if (array_key_exists('capacity', $data) && $data['capacity'] !== null
            && $tier->capacity !== null && (int) $data['capacity'] > (int) $tier->capacity) {
            $data['capacity_alerted_near_at'] = null;
            $data['capacity_alerted_full_at'] = null;
        }
        $tier->update($data);

        return $this->ok($this->tierShape($tier));
    }

    public function destroyTier(Request $request, int $linkId, int $tierId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        $tier = EventTicketTier::where('link_id', $link->id)->find($tierId);
        if (!$tier) return $this->notFound();

        if ($tier->sold_count > 0) {
            return $this->fail('Cannot delete a tier that already has sales. Deactivate it instead.', 422);
        }
        $tier->delete();

        return $this->noContent();
    }

    // ─── Interested / Not-interested signal (Task #3593) ──────────
    // Separate from RSVP/ticket purchase — a one-tap interest signal any
    // signed-in user can toggle. Mirrors web EventInterestController.

    public function interest(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized('Sign in to mark interest.', 'login_required');

        $link = Link::where('alias', $alias)->where('type', 'ics')->first();
        if (!$link) return $this->notFound('Event not found.');

        $data = $request->validate(['status' => ['required', 'in:interested,not_interested']]);

        $interest = \App\Modules\User\Models\EventInterest::updateOrCreate(
            ['link_id' => $link->id, 'user_id' => $user->id],
            ['status' => $data['status']],
        );

        return $this->ok([
            'status' => $interest->status,
            'counts' => [
                'interested'     => $link->eventInterests()->where('status', 'interested')->count(),
                'not_interested' => $link->eventInterests()->where('status', 'not_interested')->count(),
            ],
        ]);
    }

    // ─── Owner: door check-in ──────────────────────────────────────

    public function checkin(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $ticket = EventTicket::where('link_id', $link->id)->where('code', $data['code'])->with('tier')->first();
        if (!$ticket) {
            return $this->ok(['ok' => false, 'status' => 'not_found', 'message' => 'No ticket matches that code.']);
        }
        if ($ticket->status === EventTicket::STATUS_CHECKED_IN) {
            $ticket->loadMissing('checkedInBy');
            $scannerName = $ticket->checkedInBy?->name;
            return $this->ok([
                'ok' => false, 'status' => 'already_checked_in',
                'message' => 'Already checked in at ' . optional($ticket->checked_in_at)->format('g:i A')
                    . ($scannerName ? ' by ' . $scannerName : ''),
                'ticket' => $this->ticketShape($ticket),
            ]);
        }
        if (in_array($ticket->status, [EventTicket::STATUS_CANCELLED, EventTicket::STATUS_REFUNDED], true)) {
            return $this->ok(['ok' => false, 'status' => $ticket->status, 'message' => 'This ticket was ' . $ticket->status . ' and is not valid.']);
        }

        $requiredBadgeId = $link->icsData?->required_badge_id;
        if ($requiredBadgeId && !$this->attendeeHasBadge($ticket, $requiredBadgeId)) {
            return $this->ok([
                'ok' => false, 'status' => 'badge_required',
                'message' => 'This event requires a badge that the attendee does not hold. Entry denied.',
                'ticket' => $this->ticketShape($ticket),
            ]);
        }

        $ticket->update(['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => now(), 'checked_in_by' => $user->id]);

        $this->awardAttendanceBadge($link, $ticket);

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

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.view')) return $this->forbidden();

        return $this->ok(\App\Services\Events\EventCheckinProgress::for($link));
    }

    // ─── Owner: ticket list + refund ─────────────────

    public function ownerTickets(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.view')) return $this->forbidden();

        $tickets = $link->eventTickets()->with('tier')->orderByDesc('created_at')->paginate(50);

        return $this->ok([
            'items' => collect($tickets->items())->map(fn (EventTicket $t) => $this->ticketShape($t))->all(),
            'meta'  => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'total'        => $tickets->total(),
            ],
        ]);
    }

    public function refundTicket(Request $request, int $linkId, int $ticketId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        $ticket = EventTicket::where('link_id', $link->id)->find($ticketId);
        if (!$ticket) return $this->notFound();

        if (in_array($ticket->status, [EventTicket::STATUS_REFUNDED, EventTicket::STATUS_CANCELLED], true)) {
            return $this->fail('This ticket has already been ' . $ticket->status . '.', 422);
        }

        $data = $request->validate(['refund_reason' => ['nullable', 'string', 'max:280']]);

        $ok = $this->checkout->refundEventTicket($ticket->id, $data['refund_reason'] ?? null);
        if (!$ok) {
            return $this->fail('Could not refund this ticket.', 422);
        }

        return $this->ok($this->ticketShape($ticket->fresh(['tier'])));
    }

    /**
     * Badge-powered entry rules: check-in is denied unless the attendee
     * (resolved by email against a registered account) already holds the
     * event's required badge. Mirrors EventCheckinController's web logic.
     */
    private function attendeeHasBadge(EventTicket $ticket, int $badgeId): bool
    {
        $attendee = $ticket->attendee_email
            ? \App\Modules\User\Models\User::where('email', $ticket->attendee_email)->first()
            : null;
        if (!$attendee) return false;

        return $attendee->accountBadges()->where('account_badges.id', $badgeId)->exists();
    }

    /**
     * Badge-powered invites: checking in an attendee who is a registered
     * account and matches the event's `award_badge_id` attaches that
     * badge, if not already held. Mirrors EventCheckinController's web logic.
     */
    private function awardAttendanceBadge(Link $link, EventTicket $ticket): void
    {
        $awardBadgeId = $link->icsData?->award_badge_id;
        if (!$awardBadgeId) return;

        $attendee = $ticket->attendee_email
            ? \App\Modules\User\Models\User::where('email', $ticket->attendee_email)->first()
            : null;
        if (!$attendee) return;

        if (!$attendee->accountBadges()->where('account_badges.id', $awardBadgeId)->exists()) {
            $attendee->accountBadges()->attach($awardBadgeId);
        }
    }

    // ─── Workspace-aware access (Task #3606) ────────────────────────
    // Mirrors LinkInsuranceController::findLink/canAct/accessibleWorkspaceIds:
    // the API is stateless (no active-workspace binding), so an "owner
    // only" literal `user_id` match under-serves team members. Any
    // authorized workspace collaborator with `links.view`/`links.edit`
    // should reach the scanner/checkin/tier surfaces, not just the owner.

    protected function findEventLink(Request $request, int $id): ?Link
    {
        $user = $request->user();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($id);
        if ($link) return $link;

        $workspaceIds = $this->accessibleWorkspaceIds($user);
        if (empty($workspaceIds)) return null;

        return Link::where('type', 'ics')->whereIn('workspace_id', $workspaceIds)->find($id);
    }

    protected function accessibleWorkspaceIds($user): array
    {
        if (!Schema::hasColumn('links', 'workspace_id')) return [];

        return $user->accessibleWorkspaces()->pluck('id')->all();
    }

    protected function canAct(Request $request, Link $link, string $permission): bool
    {
        $user = $request->user();

        if ((int) $link->user_id === (int) $user->id) return true;
        if (empty($link->workspace_id)) return true;

        $workspace = Workspace::find($link->workspace_id);
        if (!$workspace) return true;

        return $user->canInWorkspace($workspace, $permission);
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

        $category = ($link->settings ?? [])['event_category'] ?? null;
        $categoryLabel = $category !== null && $category !== '' ? EventCategories::label($category) : null;
        $categoryIcon = $category !== null && $category !== '' ? EventCategories::icon($category) : null;

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
            'category'    => $category,
            // Curated label + icon (Task #3615 parity) so mobile renders the same
            // icon/name as the web /events directory instead of guessing.
            'category_label' => $categoryLabel,
            'category_icon'  => $categoryIcon,
            'ticketing_enabled' => (bool) (($link->settings ?? [])['ticketing_enabled'] ?? false),
            'tiers'       => $tiers->map(fn (EventTicketTier $t) => $this->tierShape($t))->values()->all(),
            // Task #3593: hashtags, richer page content, badge invites/entry.
            'hashtags'          => $ics?->hashtagList() ?? [],
            'cover_image_url'   => $ics?->cover_image_url,
            'gallery'           => $ics?->gallery ?? [],
            'info_sections'     => $ics?->info_sections ?? [],
            'required_badge_id' => $ics?->required_badge_id,
            'award_badge_id'    => $ics?->award_badge_id,
            'interested_count'      => $link->eventInterests()->where('status', 'interested')->count(),
            'not_interested_count'  => $link->eventInterests()->where('status', 'not_interested')->count(),
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
        $ticket->loadMissing('checkedInBy');

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
            'checked_in_by'  => $ticket->checkedInBy?->name,
            'is_rsvp_ticket' => (bool) $ticket->rsvp_id,
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
