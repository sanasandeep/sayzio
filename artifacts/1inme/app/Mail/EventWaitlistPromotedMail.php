<?php

namespace App\Mail;

use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a waitlisted guest's spot opens up.
 *
 * For FREE RSVP events the guest is already auto-promoted to confirmed, so
 * this is a "you're in" confirmation with their manage link. For PAID tiers
 * we cannot auto-charge, so a $tier is passed and the mail becomes a "a spot
 * opened — grab it" invite with a purchase link instead.
 */
class EventWaitlistPromotedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Link $link,
        public Rsvp $rsvp,
        public ?EventTicketTier $tier = null,
    ) {}

    public function build(): self
    {
        $title = $this->link->title ?: $this->link->alias;
        $paid  = $this->tier !== null;

        return $this
            ->subject($paid ? "A spot opened up: {$title}" : "You're in: {$title}")
            ->text('emails.event-waitlist-promoted-text', [
                'link'  => $this->link,
                'rsvp'  => $this->rsvp,
                'tier'  => $this->tier,
                'title' => $title,
                'paid'  => $paid,
            ]);
    }
}
