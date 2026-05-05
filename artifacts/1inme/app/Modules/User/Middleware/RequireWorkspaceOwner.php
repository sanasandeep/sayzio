<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Models\Workspace;
use Closure;
use Illuminate\Http\Request;

/**
 * Gate a route to the workspace owner (and super-admins) only.
 *
 * Used for actions that affect the workspace owner's account itself —
 * billing, subscription, refunds, invoices, plan upgrades — which the
 * universal role-based `workspace.can:*` gates intentionally cannot
 * cover (those grant action access uniformly across every resource
 * inside the workspace; billing is *outside* that scope).
 */
class RequireWorkspaceOwner
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        /** @var Workspace|null $ws */
        $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        if (!$ws) {
            abort(403, 'No active workspace.');
        }

        if ($user->hasPermission('user.workspaces.access_any') || (int) $ws->owner_user_id === $user->id) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'error'  => 'forbidden',
                'reason' => 'owner_only',
            ], 403);
        }
        abort(403, 'Only the workspace owner can perform this action.');
    }
}
