<?php

namespace App\Mail;

use App\Modules\User\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Branded transactional email sent when a verified custom domain's
 * DNS drifts away from us, and again as a final warning when the
 * grace period elapses and we auto-unverify the domain.
 *
 * Triggered from {@see \App\Modules\Common\Services\DomainHealthChecker}
 * via {@see \App\Modules\Common\Services\NotificationService::prefersChannel()}
 * so the recipient's per-type "email" toggle is honoured.
 */
class DomainHealthAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public Domain $domain;
    public string $type;
    public array $payload;

    public function __construct(Domain $domain, string $type, array $payload)
    {
        $this->domain  = $domain;
        $this->type    = $type;
        $this->payload = $payload;
    }

    public function build(): self
    {
        $host = $this->domain->domain;
        $subject = $this->type === 'custom_domain_unverified'
            ? "Action required: {$host} has been unverified"
            : "Heads up: DNS for {$host} stopped pointing at Sayzio";

        return $this
            ->subject($subject)
            ->view('emails.domain-health-alert', [
                'domain'  => $this->domain,
                'type'    => $this->type,
                'payload' => $this->payload,
                'subject' => $subject,
            ]);
    }
}
