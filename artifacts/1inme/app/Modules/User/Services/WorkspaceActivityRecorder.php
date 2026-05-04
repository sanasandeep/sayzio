<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceActivityEvent;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Lightweight recorder for per-member workspace activity events. Used by
 * controllers to write structured audit rows that the workspace owner /
 * admins can review and filter on the Activity page.
 *
 * Failures are swallowed — activity logging must never break a write
 * (e.g. if the migration hasn't been applied or the row is malformed).
 */
class WorkspaceActivityRecorder
{
    /**
     * Catalogue of well-known action slugs used across the codebase. The
     * filter UI on the Activity page reads this so newly-instrumented
     * surfaces show up without further wiring.
     */
    public const ACTIONS = [
        'link.create', 'link.update', 'link.delete',
        'biolink.block.create', 'biolink.block.update', 'biolink.block.delete',
        'post.publish', 'post.update', 'post.delete', 'post.pin', 'post.unpin',
        'inbox.reply',
        'billing.upgrade', 'billing.cancel', 'billing.resume', 'billing.refund',
        'member.invite', 'member.invite.revoke', 'member.update', 'member.remove',
        'role.update',
        'domain.add', 'domain.verify', 'domain.remove',
        'workspace.update',
    ];

    public const OBJECT_TYPES = [
        'link', 'biolink', 'post', 'inbox_thread', 'billing',
        'member', 'invite', 'role', 'domain', 'workspace',
    ];

    /**
     * Record an activity event. All arguments after $action are optional.
     * If no actor is supplied, the currently signed-in user is used.
     */
    public static function record(
        ?Workspace $workspace,
        string $action,
        ?string $objectType = null,
        $objectId = null,
        ?string $objectLabel = null,
        ?string $objectUrl = null,
        array $payload = [],
        $actor = null,
    ): void {
        try {
            $ws = $workspace ?: (app()->bound('current_workspace') ? app('current_workspace') : null);
            if (!$ws) return;

            $actorUser = $actor ?: (auth()->check() ? auth()->user() : null);
            $req = RequestFacade::instance();

            WorkspaceActivityEvent::create([
                'workspace_id'  => $ws->id,
                'actor_user_id' => $actorUser?->id,
                'action'        => $action,
                'object_type'   => $objectType,
                'object_id'     => $objectId !== null ? (int) $objectId : null,
                'object_label'  => $objectLabel ? mb_substr($objectLabel, 0, 250) : null,
                'object_url'    => $objectUrl ? mb_substr($objectUrl, 0, 1000) : null,
                'payload'       => $payload ?: null,
                'ip'            => $req?->ip(),
                'user_agent'    => $req ? mb_substr((string) $req->userAgent(), 0, 500) : null,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // best-effort — never break the calling write
        }
    }
}
