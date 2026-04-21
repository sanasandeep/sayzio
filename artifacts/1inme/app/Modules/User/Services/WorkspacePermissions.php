<?php

namespace App\Modules\User\Services;

/**
 * Canonical vocabulary for workspace permissions and the preset-role →
 * permission-matrix mapping. Single source of truth for both the API gate
 * and the Team-settings UI.
 *
 * Permissions are stored on each `workspace_members.permissions` row as
 * a flat associative array keyed by "{feature}.{action}" with boolean
 * values, e.g. ['links.view' => true, 'links.edit' => false, ...].
 */
class WorkspacePermissions
{
    /** Feature areas surfaced in the Team settings matrix. */
    public const FEATURES = [
        'links', 'posts', 'inbox', 'stats', 'followers', 'digests', 'referrals', 'settings',
    ];

    /** Actions per feature. Reply only applies where it's meaningful. */
    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'reply'];

    /** Subset of (feature, action) pairs that are *meaningful*. */
    public static function matrix(): array
    {
        // Default: every feature supports view/create/edit/delete; reply is
        // limited to inbox + posts (comment replies, future).
        $m = [];
        foreach (self::FEATURES as $f) {
            $m[$f] = ['view', 'create', 'edit', 'delete'];
        }
        $m['inbox'][] = 'reply';
        $m['posts'][] = 'reply';
        // Stats only has view (no creation/edit semantics).
        $m['stats'] = ['view'];
        // Settings (workspace-level) — view + edit only; create/delete is
        // owner-only and not exposed to members.
        $m['settings'] = ['view', 'edit'];
        // Referrals — view + edit (toggle on/off, set rewards). No replies.
        $m['referrals'] = ['view', 'edit'];
        // Digests — view + edit only.
        $m['digests'] = ['view', 'edit'];
        return $m;
    }

    /**
     * Preset role → permissions matrix. Owner is implicit and always allowed
     * (handled in the gate, not here).
     */
    public static function presets(): array
    {
        return [
            'admin'   => self::buildAdmin(),
            'editor'  => self::buildEditor(),
            'replier' => self::buildReplier(),
            'analyst' => self::buildAnalyst(),
            'viewer'  => self::buildViewer(),
        ];
    }

    /** Resolve a preset by slug into its permission matrix. */
    public static function preset(string $slug): array
    {
        return self::presets()[$slug] ?? [];
    }

    /** Build a flat permissions array from a feature→actions map. */
    public static function flatten(array $matrix): array
    {
        $out = [];
        foreach ($matrix as $feature => $actions) {
            foreach ($actions as $action) {
                $out[$feature . '.' . $action] = true;
            }
        }
        return $out;
    }

    /** Compare a permissions blob against every preset; return matching slug or 'custom'. */
    public static function detectRole(array $permissions): string
    {
        $normalized = self::normalize($permissions);
        foreach (self::presets() as $slug => $preset) {
            if ($normalized === self::normalize($preset)) return $slug;
        }
        return 'custom';
    }

    /** Sort+strip-false for stable comparison. */
    public static function normalize(array $perms): array
    {
        $clean = [];
        foreach ($perms as $k => $v) {
            if ($v) $clean[$k] = true;
        }
        ksort($clean);
        return $clean;
    }

    private static function buildAdmin(): array
    {
        // Everything except billing/delete-workspace/manage-members which
        // are owner-only and never exposed in the matrix.
        $out = [];
        foreach (self::matrix() as $feature => $actions) {
            foreach ($actions as $action) $out[$feature . '.' . $action] = true;
        }
        return $out;
    }

    private static function buildEditor(): array
    {
        $out = [];
        foreach (['links', 'posts', 'inbox', 'followers'] as $f) {
            foreach (['view', 'create', 'edit'] as $a) {
                $out[$f . '.' . $a] = true;
            }
            if (in_array('reply', self::matrix()[$f] ?? [], true)) {
                $out[$f . '.reply'] = true;
            }
        }
        $out['stats.view'] = true;
        $out['digests.view'] = true;
        return $out;
    }

    private static function buildReplier(): array
    {
        return [
            'inbox.view' => true,
            'inbox.reply' => true,
            'posts.view' => true,
        ];
    }

    private static function buildAnalyst(): array
    {
        return ['stats.view' => true];
    }

    private static function buildViewer(): array
    {
        $out = [];
        foreach (self::matrix() as $feature => $actions) {
            if (in_array('view', $actions, true)) {
                $out[$feature . '.view'] = true;
            }
        }
        return $out;
    }
}
