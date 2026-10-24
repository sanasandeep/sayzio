<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Services\WorkspacePermissionCatalog;
use App\Modules\User\Services\WorkspacePermissions;
use App\Modules\User\Services\WorkspaceRecordAccess;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Feature gate for workspace routes, including routes that lacked explicit gates. */
class EnforceWorkspaceFeature
{
    public static function action(Request $request): string
    {
        $route = $request->route();
        $name = $route?->getName() ?? '';
        $method = strtolower($route?->getActionMethod() ?? '');
        // Access telemetry follows the read permission; it never changes the record.
        if (in_array($name, ['user.vault.events', 'user.delivery-projects.tracking.event'], true)) {
            return 'view';
        }
        if ($request->isMethod('DELETE')) {
            return 'delete';
        }
        if (preg_match('/export|download|printpdf/', $method)) {
            return 'export';
        }
        if (preg_match('/publish|unpublish|toggleactive/', $method)) {
            return 'publish';
        }
        if (preg_match('/^(generate|estimate|run|execute|think|chat)|autopilot/', $method)) {
            return 'run';
        }
        if ($request->isMethod('DELETE') || preg_match('/destroy|delete|remove|purge|resetstats/', $method)) {
            return 'delete';
        }
        if (preg_match('/reply|respond|sendmessage/', $method) || preg_match('/(replies|messages)(\/\{[^}]+\})?$/', $route?->uri() ?? '') && ! $request->isMethod('GET')) {
            return 'reply';
        }
        if (preg_match('/(^|\.)(create|store|duplicate|clone|upload|import)(\.|$)/', $name) || in_array($method, ['create', 'store', 'duplicate', 'clone', 'upload', 'import'])) {
            return 'create';
        }
        if (in_array($method, ['preview', 'search', 'pickersearch', 'browse', 'probe'])) {
            return 'view';
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return 'edit';
        }
        if (preg_match('/(^|\.)edit(\.|$)/', $name)) {
            return 'edit';
        }

        return 'view';
    }

    public function handle(Request $request, Closure $next)
    {
        $feature = WorkspacePermissionCatalog::routeFeature($request->route()?->uri());
        if (! $feature) {
            return $next($request);
        }
        // Attendance has independent self-service, review and policy capabilities.
        // Its controller checks membership, record ownership and the exact capability.
        if ($feature === 'attendance') return $next($request);
        $permissionAction = self::action($request);
        if (! in_array($permissionAction, WorkspacePermissionCatalog::featureActions($feature))) {
            $permissionAction = WorkspacePermissionCatalog::baseAction($permissionAction);
        }

        return app(RequireWorkspacePermission::class)->handle($request, function ($request) use ($next) {
            foreach ($request->route()->parameters() as $record) {
                if (! $record instanceof Model) {
                    continue;
                }
                $parent = WorkspaceRecordAccess::parents()[class_basename($record)] ?? null;
                if ($parent && ! WorkspacePermissions::userIsOwner()) {
                    $record = $record->{$parent}()->first();
                    abort_unless($record, 404);
                }
                $entry = WorkspaceRecordAccess::models()[class_basename($record)] ?? null;
                if (! $entry || WorkspacePermissions::userIsOwner()) {
                    continue;
                }
                [$recordFeature, $column] = $entry;
                $action = WorkspacePermissionCatalog::baseAction(self::action($request));
                if (! in_array($action, ['edit', 'delete'])) {
                    $action = 'view';
                }
                if ((int) $record->getAttribute($column) !== (int) $request->user()->id) {
                    abort_unless($request->user()->canInWorkspace(app('current_workspace'), $recordFeature.'.view_others')
                        && $request->user()->canInWorkspace(app('current_workspace'), $recordFeature.'.'.$action.'_others'), 403);
                }
            }

            return $next($request);
        }, $feature.'.'.$permissionAction);
    }
}
