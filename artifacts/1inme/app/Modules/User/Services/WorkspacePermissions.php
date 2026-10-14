<?php

namespace App\Modules\User\Services;

use App\Modules\User\Services\WorkspaceRoleMatrix;

/** Feature-specific role permissions; owners bypass through User::canInWorkspace. */
class WorkspacePermissions
{
    /** Recognised role slugs (lower priority → higher). */
    public const ROLES = ['viewer', 'analyst', 'replier', 'editor', 'admin'];

    /** Universal actions a member can perform on any resource. */
    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'reply'];

    /**
     * Source-of-truth defaults. The active workspace may override individual
     * cells via `WorkspaceRoleMatrix` — the legacy callers keep working
     * because middleware/views resolve through `roleCan()` which honours the
     * effective matrix when a workspace is bound.
     */
    public static function roleActions(): array
    {
        return WorkspaceRoleMatrix::defaults();
    }

    /**
     * Effective role × action matrix for a given workspace, falling back to
     * the active workspace bound on the container, and finally the
     * hardcoded defaults. Used by the team UI and the invite preview so
     * they reflect the workspace's customisations.
     */
    public static function effectiveRoleActions(?\App\Modules\User\Models\Workspace $ws = null): array
    {
        if (!$ws) {
            $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        }
        return WorkspaceRoleMatrix::forWorkspace($ws);
    }

    /** Friendly one-line description of each role for the team UI. */
    public static function roleDescriptions(): array
    {
        $descriptions = [
            'admin'   => 'Admin — configurable content access; manages team and roles',
            'editor'  => 'Editor — defaults to view, create, edit and reply',
            'replier' => 'Replier — view and reply only (great for support)',
            'analyst' => 'Analyst — view-only, focused on analytics',
            'viewer'  => 'Viewer — defaults to view-only access',
        ];
        foreach (self::presets() as $role) {
            if (!isset($descriptions[$role])) $descriptions[$role] = self::roleLabel($role).' — custom permissions';
        }
        return $descriptions;
    }

    /**
     * Does $role allow $action? Accepts either a bare action like 'edit'
     * or the legacy 'feature.action' form like 'links.edit' (the feature and action are evaluated separately).
     */
    public static function roleCan(string $role, string $action, ?\App\Modules\User\Models\Workspace $ws = null): bool
    {
        if (!str_contains($action, '.') && !$ws) {
            $feature = WorkspacePermissionCatalog::routeFeature(request()->route()?->uri());
            if ($feature) $action = $feature.'.'.$action;
        }
        $action = WorkspacePermissionCatalog::canonical($action);
        $matrix = self::effectiveRoleActions($ws);
        $row = $matrix[$role] ?? [];
        if (array_key_exists($action, $row)) return (bool) $row[$action];
        // Unconfigured built-in workspaces use the historic action defaults.
        if (str_contains($action, '.') && isset(WorkspacePermissionCatalog::features()[explode('.', $action, 2)[0]])) {
            $base = WorkspacePermissionCatalog::baseAction(explode('.', $action, 2)[1]);
            return (bool) ($row[$base] ?? false);
        }
        return (bool) ($row[$action] ?? false);
    }

    public static function roleLabel(string $role): string
    {
        return ucwords(str_replace('_', ' ', preg_replace('/^custom_/', '', $role)));
    }

    /** Names of roles in priority order — used by the role dropdown. */
    public static function presets(): array
    {
        return array_keys(self::effectiveRoleActions());
    }

    /**
     * Convenience check for views/menus: does the *current authenticated user*
     * hold the given permission in the *currently bound workspace*?
     *
     * Owners (and super-admins) of the active workspace always pass. Returns
     * false when no user is signed in or no workspace is bound (e.g. CLI).
     */
    public static function userCan(string $permission): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        if (!$ws) return false;
        return $user->canInWorkspace($ws, $permission);
    }

    /** Friendly singular noun for each resource prefix used in permission slugs. */
    protected static function featureLabels(): array
    {
        return [
            'links'           => 'links',
            'biolinks'        => 'Link in Bio pages',
            'posts'           => 'posts',
            'forms'           => 'forms',
            'subscribers'     => 'subscribers',
            'contacts'        => 'contacts',
            'qr'              => 'QR codes',
            'qr-codes'        => 'QR codes',
            'qrcodes'         => 'QR codes',
            'projects'        => 'projects',
            'pixels'          => 'tracking pixels',
            'domains'         => 'custom domains',
            'splash-pages'    => 'splash pages',
            'splash_pages'    => 'splash pages',
            'social-accounts' => 'social accounts',
            'social_accounts' => 'social accounts',
            'social-proofs'   => 'social proofs',
            'integrations'    => 'integrations',
            'inbox'           => 'the inbox',
            'tasks'           => 'task boards',
            'vault'           => 'the vault',
            'followers'       => 'followers',
            'visitors'        => 'visitors',
            'feed'            => 'the activity feed',
            'files'           => 'files',
            'identifiers'     => 'identifiers',
            'invoices'        => 'invoices',
            'dialer'          => 'the dialer',
            'events'          => 'events',
            'notifications'   => 'notifications',
        ];
    }

    /** Friendly verb for each action used in permission slugs. */
    protected static function actionLabels(): array
    {
        return [
            'view'   => 'View',
            'create' => 'Create',
            'edit'   => 'Edit',
            'delete' => 'Delete',
            'reply'  => 'Reply to',
        ];
    }

    /**
     * Translate a permission slug like `posts.create` or `links.edit` into a
     * human sentence like "Create posts" / "Edit links". A bare action like
     * `edit` becomes "Edit content".
     */
    public static function permissionLabel(string $permission): string
    {
        $feature = null;
        $action = $permission;
        if (str_contains($permission, '.')) {
            [$feature, $action] = explode('.', $permission, 2);
        }
        $verb = self::actionLabels()[$action] ?? ucfirst(str_replace(['_', '-'], ' ', $action));
        if (!$feature) {
            return $verb . ' content';
        }
        $noun = self::featureLabels()[$feature] ?? str_replace(['_', '-'], ' ', $feature);
        return $verb . ' ' . $noun;
    }

    /**
     * Lowest role that grants the given permission (so we can tell members
     * "ask an Editor or Admin"). Returns null if no preset role grants it
     * (in which case only the workspace owner / a super-admin can).
     */
    public static function lowestRoleFor(string $permission): ?string
    {
        foreach (self::ROLES as $role) {
            if (self::roleCan($role, $permission)) {
                return $role;
            }
        }
        return null;
    }

    /**
     * True if the active user is the owner of the active workspace, or
     * holds the `user.workspaces.access_any` permission.
     */
    public static function userIsOwner(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if (method_exists($user, 'hasPermission') && $user->hasPermission('user.workspaces.access_any')) return true;
        $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        if (!$ws) return false;
        return (int) $ws->owner_user_id === (int) $user->id;
    }
}
