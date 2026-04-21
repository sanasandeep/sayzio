<?php

namespace App\Mail;

use App\Modules\User\Models\WorkspaceInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Branded transactional email sent when a workspace owner invites a teammate.
 *
 * Used by both the initial invite and the "resend invite" flows in
 * {@see \App\Modules\User\Controllers\TeamController::sendInviteEmail()} so
 * that both paths produce the exact same branded email.
 */
class WorkspaceInviteMailable extends Mailable
{
    use Queueable, SerializesModels;

    public WorkspaceInvite $invite;
    public string $workspaceName;
    public string $inviterName;
    public string $roleLabel;
    public string $acceptUrl;
    public ?string $expiresAt;

    public function __construct(WorkspaceInvite $invite)
    {
        $invite->loadMissing(['workspace', 'inviter']);

        $this->invite        = $invite;
        $this->workspaceName = optional($invite->workspace)->name ?? 'a workspace';
        $this->inviterName   = optional($invite->inviter)->name
            ?? optional($invite->inviter)->email
            ?? 'A teammate';
        $this->roleLabel     = ucfirst((string) $invite->role);
        $this->acceptUrl     = route('user.workspaces.invite.show', ['token' => $invite->token]);
        $this->expiresAt     = optional($invite->expires_at)->toDayDateTimeString();
    }

    public function build(): self
    {
        return $this
            ->subject("You've been invited to {$this->workspaceName}")
            ->view('emails.workspace-invite', [
                'invite'        => $this->invite,
                'workspaceName' => $this->workspaceName,
                'inviterName'   => $this->inviterName,
                'roleLabel'     => $this->roleLabel,
                'acceptUrl'     => $this->acceptUrl,
                'expiresAt'     => $this->expiresAt,
            ]);
    }
}
