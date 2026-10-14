<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceRolePermission;
use App\Modules\User\Models\WorkspaceRolePermissionAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceRoleDirectory
{
    public static function names(?Workspace $ws, ?WorkspaceRolePermission $record = null): array
    {
        $defaults = ['admin' => 'Admin', 'editor' => 'Editor', 'replier' => 'Replier', 'analyst' => 'Analyst', 'viewer' => 'Viewer'];
        if (! $ws) {
            return $defaults;
        }
        $record ??= WorkspaceRolePermission::where('workspace_id', $ws->id)->first();
        if ($record?->role_definitions !== null) {
            return ['admin' => $record->role_definitions['admin'] ?? 'Admin'] + $record->role_definitions;
        }
        foreach ((array) $record?->matrix as $role => $row) {
            if (preg_match('/^custom_[a-z0-9_]{1,60}$/', $role)) {
                $defaults[$role] = ucwords(str_replace('_', ' ', substr($role, 7)));
            }
        }

        return $defaults;
    }

    public static function change(Workspace $ws, $actor, string $operation, array $data): string
    {
        return DB::transaction(function () use ($ws, $actor, $operation, $data) {
            WorkspaceRolePermission::firstOrCreate(['workspace_id' => $ws->id], ['matrix' => WorkspaceRoleMatrix::defaults()]);
            $record = WorkspaceRolePermission::where('workspace_id', $ws->id)->lockForUpdate()->firstOrFail();
            WorkspaceRoleMatrix::forget($ws);
            $names = self::names($ws, $record);
            $matrix = WorkspaceRoleMatrix::forWorkspace($ws);
            $role = $data['role'] ?? null;
            if (isset($data['name'])) {
                $data['name'] = trim($data['name']);
                abort_if($data['name'] === '', 422, 'Enter a role name.');
                abort_if(mb_strtolower($data['name']) === 'owner', 422, 'Owner is reserved for the workspace owner.');
                foreach ($names as $key => $name) {
                    abort_if($key !== $role && mb_strtolower($name) === mb_strtolower($data['name']), 422, 'A role with this name already exists.');
                }
            }
            if ($operation === 'create') {
                abort_if(count($names) >= 30, 422, 'Maximum 30 roles per workspace.');
                $role = 'custom_'.str_replace('-', '_', Str::slug($data['name']));
                abort_unless(preg_match('/^custom_[a-z0-9_]{1,60}$/', $role), 422, 'Use letters and numbers in the role name.');
                abort_if(isset($names[$role]), 422, 'A role with this name already exists.');
                $source = $data['source'] ?? null;
                abort_if($source && ! isset($matrix[$source]), 422, 'Choose an existing source role.');
                $names[$role] = $data['name'];
                $row = $source ? $matrix[$source] : array_fill_keys(WorkspacePermissionCatalog::keys(), false);
                $record->update(['role_definitions' => $names, 'matrix' => $matrix + [$role => array_fill_keys(WorkspacePermissionCatalog::keys(), false)]]);
                WorkspaceRoleMatrix::forget($ws);
                WorkspaceRoleMatrix::save($ws, $matrix + [$role => $row], $actor);
            } else {
                abort_unless(isset($names[$role]), 422, 'Role does not exist.');
                if ($operation === 'rename') {
                    $before = $names[$role];
                    $names[$role] = $data['name'];
                    $record->update(['role_definitions' => $names]);
                    $changes = [$role => ['name' => ['from' => $before, 'to' => $names[$role]]]];
                } else {
                    abort_if($role === 'admin', 422, 'Admin is protected and cannot be deleted.');
                    $target = $data['reassign_to'] ?? null;
                    abort_unless($target && $target !== $role && isset($names[$target]), 422, 'Choose a replacement role before deleting.');
                    DB::table('workspace_members')->where('workspace_id', $ws->id)->where('role', $role)->update(['role' => $target, 'permissions' => json_encode($matrix[$target])]);
                    DB::table('workspace_invites')->where('workspace_id', $ws->id)->where('role', $role)->whereNull('accepted_at')->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->update(['role' => $target, 'permissions' => json_encode($matrix[$target])]);
                    $settings = $ws->settings ?? [];
                    if (isset($settings['post_approval']['approver_roles'])) {
                        $settings['post_approval']['approver_roles'] = array_values(array_diff($settings['post_approval']['approver_roles'], [$role]));
                        $ws->update(['settings' => $settings]);
                    }
                    unset($names[$role],$matrix[$role]);
                    $record->update(['role_definitions' => $names, 'matrix' => $matrix]);
                    $changes = [$role => ['role_deleted' => ['from' => true, 'to' => false]]];
                    $role = $target;
                }
                WorkspaceRolePermissionAudit::create(['workspace_id' => $ws->id, 'user_id' => $actor->id, 'changes' => $changes, 'created_at' => now()]);
                WorkspaceRoleMatrix::forget($ws);
            }
            WorkspaceActivityRecorder::record($ws, 'role.'.$operation, 'role', null, 'Workspace role '.$operation, route('user.team.roles.index'));

            return $role;
        });
    }
}
