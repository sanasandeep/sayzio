<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

/**
 * Owner CRUD for event ticket tiers + the ticketing dashboard (Task #3589).
 * Mirrors RsvpController's ownership-gating style. Tiers live under a
 * single `ics` link; sales totals are read straight off `event_tickets`.
 */
class EventTicketTierController extends Controller
{
    public function index(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $tiers = $link->eventTicketTiers()->withCount([
            'tickets as sold' => fn ($q) => $q->whereIn('status', ['valid', 'checked_in']),
        ])->get();

        $tickets = $link->eventTickets()->with('tier')->orderByDesc('created_at')->paginate(50);

        $totals = [
            'gross_cents'  => (int) $link->eventTickets()->whereIn('status', ['valid', 'checked_in'])->sum('price_cents'),
            'sold'         => (int) $link->eventTickets()->whereIn('status', ['valid', 'checked_in'])->sum('quantity'),
            'checked_in'   => (int) $link->eventTickets()->where('status', 'checked_in')->count(),
            'refunded'     => (int) $link->eventTickets()->where('status', 'refunded')->count(),
        ];

        $settings = (array) ($link->settings ?? []);

        return view('user.links.event-tickets', compact('link', 'tiers', 'tickets', 'totals', 'settings'));
    }

    public function store(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $data = $this->validateTier($request);
        $data['link_id'] = $link->id;
        $data['sort_order'] = (int) ($link->eventTicketTiers()->max('sort_order') ?? 0) + 1;

        EventTicketTier::create($data);

        return back()->with('success', 'Ticket tier added.');
    }

    public function update(Request $request, Link $link, EventTicketTier $tier)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($tier->link_id !== $link->id, 404);

        $data = $this->validateTier($request);
        // If the owner raises capacity, clear the capacity-alert stamps so a
        // subsequent re-fill re-alerts (Task #3623).
        if (array_key_exists('capacity', $data) && $data['capacity'] !== null
            && $tier->capacity !== null && (int) $data['capacity'] > (int) $tier->capacity) {
            $data['capacity_alerted_near_at'] = null;
            $data['capacity_alerted_full_at'] = null;
        }
        $tier->update($data);

        return back()->with('success', 'Ticket tier updated.');
    }

    public function destroy(Request $request, Link $link, EventTicketTier $tier)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($tier->link_id !== $link->id, 404);

        if ($tier->sold_count > 0) {
            return back()->with('error', 'Cannot delete a tier that already has ticket sales. Deactivate it instead.');
        }
        $tier->delete();

        return back()->with('success', 'Ticket tier removed.');
    }

    /**
     * Refund a single paid ticket (Task #3591). Reuses the shared refund
     * plumbing (gateway reversal + negative ledger row, 0% platform fee),
     * frees the seat back into its tier, rejects the ticket at door check-in,
     * and emails/notifies the attendee. Idempotent against double-refund.
     */
    public function refundTicket(Request $request, Link $link, \App\Modules\User\Models\EventTicket $ticket)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);
        abort_if($ticket->link_id !== $link->id, 404);

        if (in_array($ticket->status, ['refunded', 'cancelled'], true)) {
            return back()->with('error', 'This ticket has already been ' . $ticket->status . '.');
        }

        $data = $request->validate([
            'refund_reason' => ['nullable', 'string', 'max:280'],
        ]);

        $ok = app(\App\Services\Monetization\MonetizationCheckout::class)
            ->refundEventTicket($ticket->id, $data['refund_reason'] ?? null);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'Ticket refunded — the attendee has been notified.' : 'Could not refund this ticket.',
        );
    }

    private function validateTier(Request $request): array
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
}
