<?php

namespace App\Mail;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the event organizer that someone just RSVPed.
 */
class EventRsvpNotifyOwnerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Link $link, public Rsvp $rsvp) {}

    public function build(): self
    {
        $name = $this->rsvp->name ?: 'A guest';
        $resp = Rsvp::RESPONSES[$this->rsvp->response] ?? $this->rsvp->response;
        return $this
            ->subject("New RSVP — {$name} · {$resp}")
            ->text('emails.event-rsvp-notify-text', [
                'link' => $this->link,
                'rsvp' => $this->rsvp,
            ]);
    }
}
