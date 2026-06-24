<?php

namespace App\Mail;

use App\Modules\Common\Models\PrivacyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Transactional email sent to the requester at each step of a privacy
 * data request (received, verify-your-email, verified, approved,
 * rejected, deletion completed, export ready). One mailable keyed by a
 * "stage" so all the copy lives in one text view.
 */
class PrivacyRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrivacyRequest $privacyRequest,
        public string $stage,
        public ?string $actionUrl = null,
    ) {}

    public function build(): self
    {
        $typeLabel = strtolower($this->privacyRequest->typeLabel());

        $subjects = [
            'verify'    => 'Confirm your ' . $typeLabel . ' request',
            'received'  => 'We received your ' . $typeLabel . ' request',
            'verified'  => 'Your ' . $typeLabel . ' request is now under review',
            'approved'  => 'Your ' . $typeLabel . ' request has been approved',
            'rejected'  => 'Your ' . $typeLabel . ' request could not be completed',
            'completed' => 'Your account has been deleted',
            'ready'     => 'Your data export is ready to download',
        ];

        $subject = $subjects[$this->stage] ?? ('Update on your ' . $typeLabel . ' request');

        return $this
            ->subject($subject)
            ->text('emails.privacy-request-text', [
                'pr'        => $this->privacyRequest,
                'stage'     => $this->stage,
                'actionUrl' => $this->actionUrl,
                'subject'   => $subject,
                'typeLabel' => $typeLabel,
            ]);
    }
}
