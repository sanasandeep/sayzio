<?php

namespace App\Mail;

use App\Modules\User\Models\Link;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Branded email sent when a link's "Link Insurance" promotes a backup
 * (type=link_failover) or restores the primary (type=link_restored).
 *
 * Used from {@see \App\Modules\Common\Services\LinkHealthChecker} via
 * {@see \App\Modules\Common\Services\NotificationService::prefersChannel()}
 * so the user's per-type "email" toggle is honoured.
 */
class LinkInsuranceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public Link $link;
    public string $type;
    public array $payload;
    public string $shortUrl;

    public function __construct(Link $link, string $type, array $payload)
    {
        $this->link     = $link;
        $this->type     = $type;
        $this->payload  = $payload;
        $this->shortUrl = $link->getShortUrl();
    }

    public function build(): self
    {
        $alias   = $this->link->alias;
        $subject = $this->type === 'link_restored'
            ? "Primary destination restored for /{$alias}"
            : "Link Insurance: failover triggered for /{$alias}";

        return $this
            ->subject($subject)
            ->view('emails.link-insurance-alert', [
                'link'     => $this->link,
                'type'     => $this->type,
                'payload'  => $this->payload,
                'shortUrl' => $this->shortUrl,
            ]);
    }
}
