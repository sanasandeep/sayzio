<?php

namespace App\Modules\User\Services;

/**
 * Canonical role → action mapping for workspace members.
 *
 * Permissions are NOT stored per feature — a member's role on a workspace
 * grants the same action set across every resource in that workspace
 * (links, biolinks, posts, forms, subscribers, QR codes, projects, …).
 *
 * Owner of the workspace and super-admins bypass these checks entirely
 * (handled in `User::canInWorkspace()`). Workspace-level admin actions
 * (delete workspace, invite/remove members, change plan/billing) remain
 * owner-only and are enforced separately in their respective controllers.
 *
 * The legacy `workspace.can:links.edit` / `@canInWorkspace('links.edit')`
 * syntax keeps working — the trailing `.edit` is what matters; the
 * feature prefix is ignored. A bare key like `'edit'` works the same way.
 */
class WorkspacePermissions
{
    /** Recognised role slugs (lower priority → higher). */
    public const ROLES = ['viewer', 'analyst', 'replier', 'editor', 'admin'];

    /** Universal actions a member can perform on any resource. */
    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'reply'];

    /**
     * Source-of-truth: which actions each role can perform on every
     * resource inside the workspace. Owner is implicit (always allowed).
     */
    public static function roleActions(): array
    {
        return [
            'admin'   => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true,  'reply' => true],
            'editor'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false, 'reply' => true],
            'replier' => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false, 'reply' => true],
            'analyst' => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false, 'reply' => false],
            'viewer'  => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false, 'reply' => false],
        ];
    }

    /** Friendly one-line description of each role for the team UI. */
    public static function roleDescriptions(): array
    {
        return [
            'admin'   => 'Admin — full access to everything in this workspace',
            'editor'  => 'Editor — view, create and edit (cannot delete)',
            'replier' => 'Replier — view and reply only (great for support)',
            'analyst' => 'Analyst — view-only, focused on analytics',
            'viewer'  => 'Viewer — view-only across the workspace',
        ];
    }

    /**
     * Does $role allow $action? Accepts either a bare action like 'edit'
     * or the legacy 'feature.action' form like 'links.edit' (the prefix
     * is ignored).
     */
    public static function roleCan(string $role, string $action): bool
    {
        if (str_contains($action, '.')) {
            [, $action] = explode('.', $action, 2);
        }
        $row = self::roleActions()[$role] ?? self::roleActions()['viewer'];
        return (bool) ($row[$action] ?? false);
    }

    /** Names of roles in priority order — used by the role dropdown. */
    public static function presets(): array
    {
        return self::ROLES;
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

    /** True if the active user is the owner (or super-admin) of the active workspace. */
    public static function userIsOwner(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;
        $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        if (!$ws) return false;
        return (int) $ws->owner_user_id === (int) $user->id;
    }
}
