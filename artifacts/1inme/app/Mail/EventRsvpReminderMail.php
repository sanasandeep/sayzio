<?php

namespace App\Mail;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to confirmed guests N hours before the next occurrence. The
 * scheduled command fans this out idempotently.
 */
class EventRsvpReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Link $link, public Rsvp $rsvp, public \DateTimeInterface $occurrence) {}

    public function build(): self
    {
        $title = $this->link->title ?: $this->link->alias;
        return $this
            ->subject("Reminder: {$title}")
            ->text('emails.event-rsvp-reminder-text', [
                'link'       => $this->link,
                'rsvp'       => $this->rsvp,
                'occurrence' => $this->occurrence,
                'title'      => $title,
            ]);
    }
}
