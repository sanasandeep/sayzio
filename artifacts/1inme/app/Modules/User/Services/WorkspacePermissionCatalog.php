<?php

namespace App\Modules\User\Services;

/** Shared vocabulary for the editor, route gates and record scopes. */
class WorkspacePermissionCatalog
{
    public static function features(): array
    {
        return [
            'links' => 'Links & Link in Bio', 'forms' => 'Forms & submissions',
            'projects' => 'Folders', 'posts' => 'Posts', 'tasks' => 'Tasks',
            'planner' => 'AI Planner & notes', 'minds' => 'AI Minds',
            'ai-personas' => 'AI Personas', 'ai-agents' => 'AI Agents & Staff',
            'ai' => 'Other AI tools', 'calendar' => 'Calendar & bookings',
            'files' => 'Files & cloud storage', 'inbox' => 'Inbox & replies',
            'subscribers' => 'Subscribers', 'contacts' => 'Contacts & leads',
            'qr' => 'QR codes', 'stats' => 'Analytics & reports',
            'pixels' => 'Tracking pixels', 'domains' => 'Domains',
            'splash-pages' => 'Splash pages', 'social-accounts' => 'Social accounts',
            'social-proofs' => 'Buzz campaigns', 'integrations' => 'Integrations',
            'vault' => 'Vault', 'invoices' => 'Client invoices',
            'delivery-projects' => 'Clients', 'client-portals' => 'Client portals',
            'events' => 'Events', 'followers' => 'Followers', 'referrals' => 'Referrals',
            'notifications' => 'Notifications', 'digests' => 'Digests',
            'identifiers' => 'Identifiers', 'settings' => 'Workspace settings',
        ];
    }

    public static function actions(): array
    {
        return ['view', 'create', 'edit', 'delete', 'reply'];
    }

    public static function featureActions(string $feature): array
    {
        $actions = self::actions();
        if (in_array($feature, ['links', 'forms', 'stats', 'files', 'tasks', 'posts', 'subscribers', 'contacts', 'invoices', 'projects', 'planner', 'qr'])) {
            $actions[] = 'export';
        }
        if (in_array($feature, ['links', 'forms', 'posts'])) {
            $actions[] = 'publish';
        }
        if (in_array($feature, ['ai', 'minds', 'ai-personas', 'ai-agents', 'planner', 'forms', 'inbox'])) {
            $actions[] = 'run';
        }

        return $actions;
    }

    public static function baseAction(string $action): string
    {
        $action = str_replace('_others', '', $action);

        return ['export' => 'view', 'publish' => 'edit', 'run' => 'edit'][$action] ?? $action;
    }

    public static function keys(): array
    {
        $keys = self::actions(); // Legacy callers remain supported.
        foreach (self::features() as $feature => $label) {
            foreach (self::featureActions($feature) as $action) {
                $keys[] = "$feature.$action";
            }
            foreach (['view', 'edit', 'delete'] as $action) {
                $keys[] = "$feature.{$action}_others";
            }
        }

        return $keys;
    }

    public static function canonical(string $permission): string
    {
        if (! str_contains($permission, '.')) {
            return $permission;
        }
        [$feature, $action] = explode('.', $permission, 2);
        $feature = ['biolinks' => 'links', 'qrcodes' => 'qr', 'calendars' => 'calendar', 'qr-codes' => 'qr', 'social_accounts' => 'social-accounts', 'splash_pages' => 'splash-pages'][$feature] ?? $feature;

        return "$feature.$action";
    }

    public static function routeFeature(?string $uri): ?string
    {
        $path = preg_replace('#^(api/v[0-9]+/|user/)#', '', $uri ?? '');
        if (str_starts_with($path, 'ai/planner') || preg_match('#^ai/mind(?:/|$)#', $path)) {
            return 'planner';
        }
        if (str_starts_with($path, 'settings/domains')) {
            return 'domains';
        }
        if (str_starts_with($path, 'settings/webhooks')) {
            return 'integrations';
        }
        if (str_starts_with($path, 'social/proofs')) {
            return 'social-proofs';
        }
        if (str_starts_with($path, 'social/accounts')) {
            return 'social-accounts';
        }
        if (str_starts_with($path, 'ai/minds')) {
            return 'minds';
        }
        if (str_starts_with($path, 'ai/personas')) {
            return 'ai-personas';
        }
        if (str_starts_with($path, 'ai/agents') || str_starts_with($path, 'ai/staff')) {
            return 'ai-agents';
        }
        $first = explode('/', $path)[0];
        $first = ['links-url' => 'links', 'links-biolink' => 'links', 'links-page' => 'links', 'cloud-files' => 'files', 'my-files' => 'files', 'qr-codes' => 'qr', 'qrcodes' => 'qr', 'calendars' => 'calendar', 'leads' => 'contacts', 'my-calendar' => 'calendar', 'calendars' => 'calendar', 'bookings' => 'calendar', 'client-invoices' => 'invoices', 'ai-companions' => 'ai-agents'][$first] ?? $first;

        return isset(self::features()[$first]) ? $first : null;
    }
}
