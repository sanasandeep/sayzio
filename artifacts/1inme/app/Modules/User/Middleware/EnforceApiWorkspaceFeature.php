<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspacePermissionCatalog;
use Closure;
use Illuminate\Http\Request;

class EnforceApiWorkspaceFeature
{
    public function handle(Request $request, Closure $next)
    {
        if (! WorkspacePermissionCatalog::routeFeature($request->route()?->uri())) {
            return $next($request);
        }
        $user = $request->user();
        $requested = $request->input('workspace_id');
        $workspace = is_numeric($requested) ? Workspace::find((int) $requested) : null;
        if (! $workspace || ! $user->belongsToWorkspace($workspace)) {
            $workspace = app(WorkspaceContext::class)->resolve($user);
        }
        if ($workspace) {
            app()->instance('current_workspace', $workspace);
        }

        return app(EnforceWorkspaceFeature::class)->handle($request, $next);
    }
}
