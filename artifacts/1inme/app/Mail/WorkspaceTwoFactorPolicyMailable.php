<?php

namespace App\Mail;

use App\Modules\User\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Heads-up email sent to every member of a workspace as soon as the
 * owner turns on the "Require 2FA" policy. Tells them when enforcement
 * begins (the grace deadline) and links to the setup page so they can
 * enroll before they're forced to.
 */
class WorkspaceTwoFactorPolicyMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public string $memberName,
        public ?string $graceDeadline,
        public string $setupUrl,
        public bool $alreadyEnrolled = false,
    ) {}

    public function build(): self
    {
        $subject = $this->alreadyEnrolled
            ? "2FA is now required in {$this->workspace->name}"
            : "Action required: enable 2FA for {$this->workspace->name}";

        return $this
            ->subject($subject)
            ->view('emails.workspace-2fa-policy', [
                'workspace'       => $this->workspace,
                'memberName'      => $this->memberName,
                'graceDeadline'   => $this->graceDeadline,
                'setupUrl'        => $this->setupUrl,
                'alreadyEnrolled' => $this->alreadyEnrolled,
            ]);
    }
}
