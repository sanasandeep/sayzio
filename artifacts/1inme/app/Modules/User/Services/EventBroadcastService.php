<?php

namespace App\Modules\User\Services;

use App\Mail\EventGuestBroadcastMail;
use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\EventBroadcast;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Support\Facades\Log;

/**
 * Organizer → guest broadcast messaging (venue moved, time changed,
 * cancellation). Resolves recipients for an event by audience filter,
 * dedupes by email (a guest may both RSVP and hold a ticket), fans out the
 * send through the central Emailer pipeline, and logs the broadcast so the
 * organizer can review past messages.
 */
class EventBroadcastService
{
    public const AUDIENCES = ['going', 'waitlist', 'all_rsvps', 'ticket_holders'];

    /** Max broadcasts allowed per link in a rolling 24h window. */
    public const DAILY_CAP = 10;

    /** Minimum seconds between two sends for the same link. */
    public const COOLDOWN_SECONDS = 60;

    /**
     * Throw when a send is refused by the per-link cooldown or daily cap,
     * carrying a user-facing message the web/API layers surface verbatim.
     */
    public function assertCanSend(Link $link): void
    {
        $now = now();

        $recent = EventBroadcast::query()
            ->where('link_id', $link->id)
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        if ($recent >= self::DAILY_CAP) {
            throw new \App\Modules\User\Services\EventBroadcastLimitException(
                'You can only send ' . self::DAILY_CAP . ' broadcasts per event per day. Please try again later.'
            );
        }

        $last = EventBroadcast::query()
            ->where('link_id', $link->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($last && $last->created_at) {
            $elapsed = $last->created_at->diffInSeconds($now, false);
            if ($elapsed >= 0 && $elapsed < self::COOLDOWN_SECONDS) {
                $wait = self::COOLDOWN_SECONDS - (int) $elapsed;
                throw new \App\Modules\User\Services\EventBroadcastLimitException(
                    "Please wait {$wait}s before sending another message to this event's guests."
                );
            }
        }
    }

    /**
     * Resolve the deduped recipient list for a given audience.
     *
     * @return array<int, array{name: ?string, email: string}>
     */
    public function recipients(Link $link, string $audience): array
    {
        $out = [];

        if ($audience === 'ticket_holders') {
            $rows = EventTicket::query()
                ->where('link_id', $link->id)
                ->whereIn('status', [EventTicket::STATUS_VALID, EventTicket::STATUS_CHECKED_IN])
                ->whereNotNull('attendee_email')
                ->get(['attendee_name', 'attendee_email']);
            foreach ($rows as $r) {
                $out[] = ['name' => $r->attendee_name, 'email' => (string) $r->attendee_email];
            }
        } else {
            $q = Rsvp::query()
                ->where('link_id', $link->id)
                ->whereNotNull('email');

            switch ($audience) {
                case 'going':
                    $q->where('status', 'confirmed')->where('response', 'yes');
                    break;
                case 'waitlist':
                    $q->where('status', 'waitlist');
                    break;
                case 'all_rsvps':
                    $q->where('status', '!=', 'cancelled');
                    break;
            }

            foreach ($q->get(['name', 'email']) as $r) {
                $out[] = ['name' => $r->name, 'email' => (string) $r->email];
            }
        }

        // Dedupe by lower-cased email; first occurrence (with its name) wins.
        $seen = [];
        $deduped = [];
        foreach ($out as $rec) {
            $email = trim($rec['email']);
            if ($email === '') continue;
            $key = mb_strtolower($email);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $deduped[] = ['name' => $rec['name'], 'email' => $email];
        }

        return $deduped;
    }

    /** Recipient count for the chosen audience (drives the live confirm UI). */
    public function recipientCount(Link $link, string $audience): int
    {
        return count($this->recipients($link, $audience));
    }

    /** Counts for every audience at once (precomputed for the form). */
    public function audienceCounts(Link $link): array
    {
        $counts = [];
        foreach (self::AUDIENCES as $a) {
            $counts[$a] = $this->recipientCount($link, $a);
        }
        return $counts;
    }

    /**
     * Queue the broadcast to every resolved recipient and persist a log row.
     * Returns the recorded EventBroadcast.
     */
    public function send(Link $link, int $userId, string $audience, string $subject, string $message): EventBroadcast
    {
        $this->assertCanSend($link);

        $audience = in_array($audience, self::AUDIENCES, true) ? $audience : 'all_rsvps';
        $recipients = $this->recipients($link, $audience);

        $title = $link->title ?: $link->alias;
        $sent  = 0;

        foreach ($recipients as $rec) {
            try {
                Emailer::sendMailable(
                    'events.guest_broadcast',
                    $rec['email'],
                    new EventGuestBroadcastMail($link, $subject, $message, $rec['name']),
                    ['subject' => $subject, 'title' => $title],
                    ['related' => $link, 'user' => $link->user_id, 'queue' => true]
                );
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Event broadcast send failed for {$rec['email']}: " . $e->getMessage());
            }
        }

        return EventBroadcast::create([
            'link_id'          => $link->id,
            'user_id'          => $userId,
            'audience'         => $audience,
            'subject'          => $subject,
            'message'          => $message,
            'recipients_count' => $sent,
        ]);
    }

    /** Past broadcasts for an event, newest first. */
    public function history(Link $link, int $limit = 20)
    {
        return EventBroadcast::query()
            ->where('link_id', $link->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
