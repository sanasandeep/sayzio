<?php

namespace App\Mail;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceAuditEvent;
use App\Modules\User\Services\SensitiveActionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Sent to workspace owners whenever a sensitive action fires (link
 * deletion, domain change, follower export, member removal, API key
 * rotation, …). Includes a one-click "this wasn't authorised" signed
 * link that opens the investigation view for that audit row.
 */
class SensitiveWorkspaceActionMail extends Mailable
{
    use Queueable, SerializesModels;

    public WorkspaceAuditEvent $event;
    public Workspace $workspace;
    public User $owner;
    public string $actionLabel;
    public string $actorName;
    public string $reportUrl;
    public string $auditUrl;

    public function __construct(WorkspaceAuditEvent $event, Workspace $workspace, User $owner)
    {
        $event->loadMissing('actor');

        $this->event       = $event;
        $this->workspace   = $workspace;
        $this->owner       = $owner;
        $this->actionLabel = SensitiveActionLogger::label($event->action);
        $this->actorName   = optional($event->actor)->name
            ?? optional($event->actor)->email
            ?? 'Unknown actor';

        // Signed URL — owner can click straight from the email without
        // signing in. The route still verifies the signature, the event's
        // workspace ownership, and forwards into the investigation flow.
        $this->reportUrl = URL::signedRoute(
            'user.workspaces.audit.report.show',
            ['event' => $event->id, 'recipient' => $owner->id],
            now()->addDays(30),
        );
        $this->auditUrl = route('user.workspaces.audit.index');
    }

    public function build(): self
    {
        $subject = sprintf(
            "[%s] %s in %s",
            config('app.name'),
            $this->actionLabel,
            $this->workspace->name,
        );

        return $this->subject($subject)->view('emails.sensitive-action-alert');
    }
}
