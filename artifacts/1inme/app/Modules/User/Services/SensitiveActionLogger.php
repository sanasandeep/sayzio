<?php

namespace App\Modules\User\Services;

use App\Mail\SensitiveWorkspaceActionMail;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceAuditAlertPref;
use App\Modules\User\Models\WorkspaceAuditEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Catalog of sensitive workspace actions plus the single recording
 * entrypoint used by controllers. Writing through this service guarantees
 * (1) a hash-chained ledger row is appended, and (2) the workspace owner
 * is emailed when their preferences allow it.
 *
 * Adding a new sensitive action:
 *  1. Add a constant + CATALOG entry below.
 *  2. Call `app(SensitiveActionLogger::class)->record(...)` from the
 *     controller method that performs the action.
 */
class SensitiveActionLogger
{
    public const ACTION_LINK_DELETED        = 'link.deleted';
    public const ACTION_DOMAIN_ADDED        = 'domain.added';
    public const ACTION_DOMAIN_VERIFIED     = 'domain.verified';
    public const ACTION_DOMAIN_REMOVED      = 'domain.removed';
    public const ACTION_FOLLOWERS_EXPORTED  = 'followers.exported';
    public const ACTION_CLICKS_EXPORTED     = 'clicks.exported';
    public const ACTION_MEMBER_REMOVED      = 'member.removed';
    public const ACTION_API_KEY_ROTATED     = 'api_key.rotated';
    public const ACTION_INTEGRATION_REMOVED = 'integration.removed';

    /**
     * Catalogue of recognised sensitive actions. `default_alert` decides
     * whether the workspace owner is emailed for an action when no
     * explicit preference row exists.
     */
    public const CATALOG = [
        self::ACTION_LINK_DELETED        => ['label' => 'Link deleted',          'default_alert' => true],
        self::ACTION_DOMAIN_ADDED        => ['label' => 'Custom domain added',   'default_alert' => true],
        self::ACTION_DOMAIN_VERIFIED     => ['label' => 'Custom domain verified','default_alert' => false],
        self::ACTION_DOMAIN_REMOVED      => ['label' => 'Custom domain removed', 'default_alert' => true],
        self::ACTION_FOLLOWERS_EXPORTED  => ['label' => 'Follower list exported','default_alert' => true],
        self::ACTION_CLICKS_EXPORTED     => ['label' => 'Click log exported',    'default_alert' => false],
        self::ACTION_MEMBER_REMOVED      => ['label' => 'Member removed',        'default_alert' => true],
        self::ACTION_API_KEY_ROTATED     => ['label' => 'API key rotated',       'default_alert' => true],
        self::ACTION_INTEGRATION_REMOVED => ['label' => 'Integration removed',   'default_alert' => false],
    ];

    /**
     * Append a sensitive-action audit row and (if enabled) email the
     * workspace owner. Failures in either path are swallowed and logged
     * so they cannot break the originating user-facing action.
     */
    public function record(
        Workspace $workspace,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetLabel = null,
        array $payload = [],
        ?int $actorId = null,
        ?string $ip = null,
    ): ?WorkspaceAuditEvent {
        if (!array_key_exists($action, self::CATALOG)) {
            Log::warning("SensitiveActionLogger: unknown action '{$action}'");
            return null;
        }

        try {
            $event = WorkspaceAuditEvent::appendChained([
                'workspace_id'  => $workspace->id,
                'actor_user_id' => $actorId ?: auth()->id(),
                'action'        => $action,
                'target_type'   => $targetType,
                'target_id'     => $targetId,
                'target_label'  => $targetLabel,
                'ip'            => $ip ?: request()?->ip(),
                'payload'       => $payload ?: null,
            ]);
        } catch (\Throwable $e) {
            // Audit failure must not break the user's action. We log so
            // ops can investigate but return null and move on.
            Log::error('SensitiveActionLogger append failed', [
                'workspace_id' => $workspace->id,
                'action'       => $action,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }

        if ($this->shouldAlert($workspace, $action)) {
            $this->dispatchAlert($workspace, $event);
        }

        return $event;
    }

    public function shouldAlert(Workspace $workspace, string $action): bool
    {
        $pref = WorkspaceAuditAlertPref::where('workspace_id', $workspace->id)
            ->where('action', $action)
            ->first();

        if ($pref) {
            return (bool) $pref->alert_enabled;
        }

        return (bool) (self::CATALOG[$action]['default_alert'] ?? false);
    }

    /**
     * Owners of the workspace who should receive alert emails. The plural
     * "owners" is forward-looking — today the workspace has a single
     * owner_user_id, but the API accepts a collection so the future
     * "co-owners" feature drops in cleanly.
     */
    public function ownerRecipients(Workspace $workspace): array
    {
        $owner = $workspace->owner ?: User::find($workspace->owner_user_id);
        if (!$owner || !$owner->email) {
            return [];
        }
        return [$owner];
    }

    protected function dispatchAlert(Workspace $workspace, WorkspaceAuditEvent $event): void
    {
        try {
            foreach ($this->ownerRecipients($workspace) as $owner) {
                Mail::to($owner->email)->send(
                    new SensitiveWorkspaceActionMail($event->fresh(), $workspace, $owner)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('SensitiveActionLogger alert mail failed', [
                'workspace_id' => $workspace->id,
                'event_id'     => $event->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /** Human label for an action, falling back to the slug. */
    public static function label(string $action): string
    {
        return self::CATALOG[$action]['label'] ?? $action;
    }
}
