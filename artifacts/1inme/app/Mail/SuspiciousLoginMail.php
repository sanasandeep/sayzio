<?php

namespace App\Mail;

use App\Modules\User\Models\LoginEvent;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the account owner whenever a successful login looks
 * suspicious (new country, OS family, or browser family vs. their
 * recent login history). Carries a one-click "This wasn't me" link
 * back to the signed revoke endpoint.
 */
class SuspiciousLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public LoginEvent $event;
    public string $revokeUrl;
    public string $reasonsLabel;

    public function __construct(User $user, LoginEvent $event, string $revokeUrl, string $reasonsLabel)
    {
        $this->user         = $user;
        $this->event        = $event;
        $this->revokeUrl    = $revokeUrl;
        $this->reasonsLabel = $reasonsLabel;
    }

    public function build(): self
    {
        $location = $this->event->country_code ?: 'Unknown location';
        $when     = optional($this->event->created_at)->format('M j, Y g:i A T') ?: 'just now';

        return $this
            ->subject("New sign-in to your Sayzio account ({$location})")
            ->view('emails.suspicious-login', [
                'user'         => $this->user,
                'event'        => $this->event,
                'revokeUrl'    => $this->revokeUrl,
                'reasonsLabel' => $this->reasonsLabel,
                'when'         => $when,
                'location'     => $location,
            ]);
    }
}
