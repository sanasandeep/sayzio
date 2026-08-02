<?php

namespace App\Mail;

use App\Modules\User\Models\Link;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * A free-form broadcast an organizer sends to their event guests
 * ("venue moved", "time changed", cancellation). Subject + message are
 * supplied by the organizer; recipients are resolved and fanned out by
 * EventBroadcastService.
 */
class EventGuestBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Link $link,
        public string $subjectLine,
        public string $messageBody,
        public ?string $recipientName = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->text('emails.event-guest-broadcast-text', [
                'link'          => $this->link,
                'subjectLine'   => $this->subjectLine,
                'messageBody'   => $this->messageBody,
                'recipientName' => $this->recipientName,
            ]);
    }
}
