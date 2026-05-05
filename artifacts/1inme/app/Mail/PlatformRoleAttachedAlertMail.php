<?php

namespace App\Mail;

use App\Modules\User\Models\UserRoleAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the configured ops recipients (or holders of
 * `user.ops_alerts.receive`) whenever one OR MORE platform-admin
 * level roles are attached to a user inside the same diff. The
 * grants are batched into a single email so an operator granting
 * two admin roles in one save no longer fans out N near-identical
 * alerts per recipient.
 *
 * Each grant carries its own deep link into the user-access audit
 * timeline; the call-to-action button lands on the timeline list
 * rather than a single anchored row, matching how reviewers want
 * to triage a multi-row save.
 */
class PlatformRoleAttachedAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Normalised list of attaches included in this email. Each entry:
     *   - audit:     UserRoleAudit (the persisted attach row)
     *   - roleLabel: string display name for the role
     *   - reasons:   string[] slug-prefixed reasons it was flagged
     *   - auditUrl:  string deep link to that exact row in the timeline
     *
     * @var array<int, array{audit: UserRoleAudit, roleLabel: string, reasons: array<int,string>, auditUrl: string}>
     */
    public array $grants;

    public string $actorLabel;
    public string $targetLabel;
    /** Deep link to the audit timeline list (no row anchor). */
    public string $listUrl;

    /**
     * @param array<int, array{audit: UserRoleAudit, reasons?: array<int,string>}> $grants
     */
    public function __construct(array $grants)
    {
        if (empty($grants)) {
            throw new \InvalidArgumentException(
                'PlatformRoleAttachedAlertMail requires at least one grant.'
            );
        }

        // All grants in a batch share the same actor (one diff = one
        // recordDiff call) and the same target user, so derive both
        // from the first row to avoid asking the caller to thread
        // them through separately.
        $first = $grants[0]['audit'];
        $first->loadMissing(['actorUser', 'actorAdmin', 'targetUser']);

        $this->actorLabel = $first->actorLabel();
        $target = $first->targetUser;
        $this->targetLabel = $target?->name
            ?: ($target?->email ?: ('User #' . $first->target_user_id));
        $this->listUrl = $this->buildListUrl();

        $normalized = [];
        foreach ($grants as $g) {
            /** @var UserRoleAudit $audit */
            $audit = $g['audit'];
            $audit->loadMissing(['actorUser', 'actorAdmin', 'targetUser']);

            $normalized[] = [
                'audit'     => $audit,
                'roleLabel' => (string) ($audit->role_name ?: $audit->role_slug),
                'reasons'   => array_values($g['reasons'] ?? []),
                'auditUrl'  => $this->buildAuditUrl($audit),
            ];
        }
        $this->grants = $normalized;
    }

    /**
     * Deep link to the user-access "Recent role changes" panel,
     * anchored to the row that triggered this individual grant.
     * Falls back to the app root if the route can't be resolved
     * (e.g. console dispatch context with no host).
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

    /**
     * Deep link to the audit timeline list itself (no row anchor).
     * Used by the prominent CTA button so a multi-row email lands
     * on the panel rather than scrolling past the other grants.
     */
    protected function buildListUrl(): string
    {
        try {
            return route('user.access.users.index');
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
        $count = count($this->grants);

        if ($count === 1) {
            $subject = sprintf(
                '[%s] Platform admin role granted: %s → %s',
                config('app.name'),
                $this->grants[0]['roleLabel'],
                $this->targetLabel,
            );
        } else {
            $subject = sprintf(
                '[%s] %d platform admin roles granted to %s',
                config('app.name'),
                $count,
                $this->targetLabel,
            );
        }

        return $this->subject($subject)->view('emails.platform-role-attached-alert');
    }
}
