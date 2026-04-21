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

    /** Friendly singular noun for each resource prefix used in permission slugs. */
    protected static function featureLabels(): array
    {
        return [
            'links'           => 'links',
            'biolinks'        => 'bio links',
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
