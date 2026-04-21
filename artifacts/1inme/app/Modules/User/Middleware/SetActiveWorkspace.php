<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Ensures every authenticated request has a resolved "active workspace"
 * bound in the container as `current_workspace`. Also binds the workspace
 * owner as `workspace_owner` so existing controllers that scope queries
 * by `user_id` (the owner-id) continue working transparently when a team
 * member is acting in their owner's workspace.
 *
 * Critically, this middleware does NOT swap the auth user — `auth()->user()`
 * always returns the actual signed-in person so attribution and the
 * permission gate can check the right identity.
 */
class SetActiveWorkspace
{
    public function __construct(protected WorkspaceContext $ctx) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $ws = $this->ctx->resolve($user);
            if ($ws) {
                app()->instance('current_workspace', $ws);
                $owner = User::find($ws->owner_user_id);
                if ($owner) {
                    app()->instance('workspace_owner', $owner);
                }
                $request->attributes->set('current_workspace', $ws);
            }
        }
        return $next($request);
    }
}
