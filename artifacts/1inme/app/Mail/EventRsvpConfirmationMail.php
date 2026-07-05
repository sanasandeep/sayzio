<?php

namespace App\Mail;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a guest after they submit an RSVP. Confirms the response, lists
 * any picked occurrences, and links them back to the manage page so they
 * can edit or cancel without re-typing their email. When the RSVP earned
 * a QR check-in ticket (Task #3606 — confirmed "yes"), the ticket code
 * and check-in/QR link are included too.
 */
class EventRsvpConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Link $link, public Rsvp $rsvp, public ?EventTicket $ticket = null) {}

    public function build(): self
    {
        $title = $this->link->title ?: $this->link->alias;
        $verb  = $this->rsvp->status === 'waitlist' ? 'on the waitlist for' : 'confirmed for';
        return $this
            ->subject("RSVP {$verb}: {$title}")
            ->text('emails.event-rsvp-confirmation-text', [
                'link'   => $this->link,
                'rsvp'   => $this->rsvp,
                'title'  => $title,
                'ticket' => $this->ticket,
            ]);
    }
}
