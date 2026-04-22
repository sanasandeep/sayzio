<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceRolePermission;
use App\Modules\User\Models\WorkspaceRolePermissionAudit;

/**
 * Loads, validates, and persists the per-workspace role × action matrix
 * customised by team Admins. Falls back to the hardcoded defaults shipped
 * by `WorkspacePermissions::defaults()` when a workspace has no override.
 *
 * Locked cells (always enforced regardless of what's stored):
 *   - the Admin row's `view` action is always true (so an Admin can never
 *     lock themselves out of the settings page).
 *   - the Owner is implicit — owners bypass the matrix entirely.
 */
class WorkspaceRoleMatrix
{
    /** Per-request memo key prefix in the service container. */
    protected const CACHE_KEY = 'workspace_role_matrix.cache';

    /** Hardcoded baseline matrix (the same one the invite UI used to ship). */
    public static function defaults(): array
    {
        return [
            'admin'   => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true,  'reply' => true],
            'editor'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false, 'reply' => true],
            'replier' => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false, 'reply' => true],
            'analyst' => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false, 'reply' => false],
            'viewer'  => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false, 'reply' => false],
        ];
    }

    /** Roles editable in the UI (Owner is implicit, not in the matrix). */
    public static function roles(): array
    {
        return ['admin', 'editor', 'replier', 'analyst', 'viewer'];
    }

    /** Actions in column order for the UI. */
    public static function actions(): array
    {
        return ['view', 'create', 'edit', 'delete', 'reply'];
    }

    /**
     * Effective matrix for a workspace — saved override merged on top of
     * defaults, with locked cells enforced. Safe to call without a workspace
     * (returns defaults).
     */
    public static function forWorkspace(?Workspace $ws): array
    {
        $defaults = self::defaults();
        if (!$ws) return $defaults;

        $cache = self::cache();
        if (array_key_exists($ws->id, $cache)) {
            return $cache[$ws->id];
        }

        $row = WorkspaceRolePermission::where('workspace_id', $ws->id)->first();
        $stored = $row ? (array) $row->matrix : [];

        $matrix = self::merge($defaults, $stored);
        $matrix = self::enforceLocks($matrix);

        $cache[$ws->id] = $matrix;
        app()->instance(self::CACHE_KEY, $cache);
        return $matrix;
    }

    /** Forget the cached matrix for a workspace (used after save). */
    public static function forget(?Workspace $ws): void
    {
        if (!$ws) return;
        $cache = self::cache();
        unset($cache[$ws->id]);
        app()->instance(self::CACHE_KEY, $cache);
    }

    /** Per-request memo, scoped to the application container. */
    protected static function cache(): array
    {
        return app()->bound(self::CACHE_KEY) ? (array) app(self::CACHE_KEY) : [];
    }

    /**
     * Validate a posted matrix and persist it for $ws, recording an audit
     * entry of the diff. Returns the saved (effective) matrix. Throws
     * InvalidArgumentException when the payload contains unknown roles or
     * actions.
     */
    public static function save(Workspace $ws, array $posted, ?User $actor): array
    {
        $clean = self::sanitise($posted);
        $clean = self::enforceLocks($clean);

        $previous = self::forWorkspace($ws);

        $diff = self::diff($previous, $clean);

        WorkspaceRolePermission::updateOrCreate(
            ['workspace_id' => $ws->id],
            ['matrix' => $clean],
        );

        // Always record the save attempt — even no-op saves leave a
        // breadcrumb that "<actor> reviewed permissions at <time>". An
        // empty diff is fine; the audit list distinguishes them visually.
        WorkspaceRolePermissionAudit::create([
            'workspace_id' => $ws->id,
            'user_id'      => $actor?->id,
            'changes'      => $diff,
            'created_at'   => now(),
        ]);

        self::forget($ws);
        return self::forWorkspace($ws);
    }

    /** Restore the workspace to the hardcoded defaults (with audit). */
    public static function reset(Workspace $ws, ?User $actor): array
    {
        return self::save($ws, self::defaults(), $actor);
    }

    /**
     * Most recent audit entries for the workspace, newest first. Each entry
     * is rendered into a list of "Role.Action: granted/revoked" strings for
     * easy display.
     */
    public static function recentAudits(Workspace $ws, int $limit = 10): array
    {
        return WorkspaceRolePermissionAudit::with('user:id,name,email')
            ->where('workspace_id', $ws->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($a) {
                $lines = [];
                foreach ((array) $a->changes as $role => $row) {
                    foreach ((array) $row as $action => $delta) {
                        $to   = $delta['to']   ?? null;
                        $verb = $to ? 'granted' : 'revoked';
                        $lines[] = ucfirst($role) . ' · ' . ucfirst($action) . ' — ' . $verb;
                    }
                }
                return [
                    'id'        => $a->id,
                    'when'      => $a->created_at,
                    'actor'     => optional($a->user)->name ?: optional($a->user)->email ?: 'Unknown',
                    'lines'     => $lines,
                    'noop'      => empty($lines),
                ];
            })
            ->all();
    }

    /** True if a (role, action) cell is locked and cannot be flipped. */
    public static function isLocked(string $role, string $action): bool
    {
        // Admin row's view stays on so admins keep access to the settings.
        if ($role === 'admin' && $action === 'view') return true;
        return false;
    }

    // ---- internals -------------------------------------------------------

    protected static function merge(array $defaults, array $stored): array
    {
        $out = [];
        foreach ($defaults as $role => $row) {
            $out[$role] = [];
            $storedRow = (array) ($stored[$role] ?? []);
            foreach ($row as $action => $value) {
                if (array_key_exists($action, $storedRow)) {
                    $out[$role][$action] = (bool) $storedRow[$action];
                } else {
                    $out[$role][$action] = (bool) $value;
                }
            }
        }
        return $out;
    }

    protected static function enforceLocks(array $matrix): array
    {
        foreach ($matrix as $role => $row) {
            foreach ($row as $action => $val) {
                if (self::isLocked($role, $action)) {
                    $matrix[$role][$action] = true;
                }
            }
        }
        return $matrix;
    }

    /**
     * Whitelist roles/actions, coerce to booleans, and reject anything
     * unknown. Missing cells are treated as false (UI sends checkboxes —
     * unchecked boxes don't appear in the payload).
     */
    protected static function sanitise(array $posted): array
    {
        $roles = self::roles();
        $actions = self::actions();
        $out = [];
        foreach ($roles as $role) {
            $row = (array) ($posted[$role] ?? []);
            $out[$role] = [];
            foreach ($actions as $action) {
                $out[$role][$action] = !empty($row[$action]);
            }
        }
        // Reject extras so a malicious payload can't smuggle in unknown
        // role/action keys that other code might later honour.
        foreach (array_keys($posted) as $role) {
            if (!in_array($role, $roles, true)) {
                throw new \InvalidArgumentException("Unknown role: {$role}");
            }
            foreach (array_keys((array) $posted[$role]) as $action) {
                if (!in_array($action, $actions, true)) {
                    throw new \InvalidArgumentException("Unknown action: {$action}");
                }
            }
        }
        return $out;
    }

    protected static function diff(array $before, array $after): array
    {
        $diff = [];
        foreach ($after as $role => $row) {
            foreach ($row as $action => $val) {
                $prev = (bool) ($before[$role][$action] ?? false);
                if ($prev !== (bool) $val) {
                    $diff[$role][$action] = ['from' => $prev, 'to' => (bool) $val];
                }
            }
        }
        return $diff;
    }
}
