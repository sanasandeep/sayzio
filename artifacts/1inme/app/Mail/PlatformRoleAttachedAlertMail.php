<?php

namespace App\Mail;

use App\Modules\User\Models\UserRoleAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the configured ops recipients (or holders of
 * `user.ops_alerts.receive`) whenever a platform-admin level role is
 * attached to a user. Includes a link straight into the user-access
 * audit timeline anchored to the audit row that fired the alert.
 */
class PlatformRoleAttachedAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public UserRoleAudit $audit;
    /** @var array<int,string> Slug-prefixed reasons the role was flagged. */
    public array $reasons;
    public string $actorLabel;
    public string $targetLabel;
    public string $roleLabel;
    public string $auditUrl;

    /**
     * @param array<int,string> $reasons
     */
    public function __construct(UserRoleAudit $audit, array $reasons = [])
    {
        $audit->loadMissing(['actorUser', 'actorAdmin', 'targetUser']);

        $this->audit       = $audit;
        $this->reasons     = array_values($reasons);
        $this->actorLabel  = $audit->actorLabel();
        $target            = $audit->targetUser;
        $this->targetLabel = $target?->name
            ?: ($target?->email ?: ('User #' . $audit->target_user_id));
        $this->roleLabel   = $audit->role_name ?: $audit->role_slug;
        $this->auditUrl    = $this->buildAuditUrl($audit);
    }

    /**
     * Deep link to the user-access "Recent role changes" panel,
     * anchored to the row that triggered this alert. Falls back to
     * the app root if the route can't be resolved (e.g. console
     * dispatch context with no host).
     */
    protected function buildAuditUrl(UserRoleAudit $audit): string
    {
        try {
            return route('user.access.users.index') . '#audit-' . $audit->id;
        } catch (\Throwable $e) {
            try {
                return url('/');
            } catch (\Throwable $e2) {
                return '';
            }
        }
    }

    public function build(): self
    {
        $subject = sprintf(
            '[%s] Platform admin role granted: %s → %s',
            config('app.name'),
            $this->roleLabel,
            $this->targetLabel,
        );

        return $this->subject($subject)->view('emails.platform-role-attached-alert');
    }
}
