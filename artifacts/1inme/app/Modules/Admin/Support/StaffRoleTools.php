<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;

final class StaffRoleTools
{
    public static function risk(string $slug): int
    {
        if (in_array($slug, ['users.edit', 'settings.view'], true) || preg_match('/^(roles\.manage|staff\.(create|edit|delete)|users\.(grant_admin|revoke_admin|assign_roles|impersonate|password\.set|delete)|settings\.manage|user\.)$/', $slug)
            || str_starts_with($slug, 'user.')) return 4;
        if (str_ends_with($slug, '.totals') || in_array($slug, ['users.view', 'users.search', 'users.filter', 'users.details', 'users.plan.view', 'users.status.view', 'users.joined.view', 'users.badges.view', 'roles.view', 'plans.view', 'blogs.view'])) return 1;
        if (preg_match('/\.(view|records|history|export|search_identity)$/', $slug)) return 2;
        // Changes and unclassified capabilities require an elevated review.
        return 3;
    }

    public static function labels(): array
    {
        return [1 => 'Level 1 · Basic access', 2 => 'Level 2 · Sensitive data', 3 => 'Level 3 · Changes', 4 => 'Critical · Security access'];
    }

    public static function canManage(Admin $actor, Role $role): bool
    {
        if (! $actor->hasPermission('roles.manage')) return false;
        if ($actor->isSuperAdmin()) return true;
        if ($role->guard !== 'admin' || $role->slug === 'super-admin') return false;
        return $role->permissions->every(fn ($permission) => $actor->hasPermission($permission->slug));
    }

    public static function presets(): array
    {
        return [
            'anonymous-support' => ['name' => 'Anonymous support', 'description' => 'Find accounts by ID and inspect status and plan without customer identity.', 'permissions' => ['users.view', 'users.search', 'users.details', 'users.status.view', 'users.plan.view']],
            'customer-support' => ['name' => 'Customer support', 'description' => 'Search customer identity and inspect account details. No account changes or exports.', 'permissions' => ['users.view', 'users.search', 'users.search_identity', 'users.details', 'users.identity.view', 'users.email.view', 'users.phone.view', 'users.status.view', 'users.plan.view', 'users.joined.view']],
            'finance-overview' => ['name' => 'Finance overview', 'description' => 'Invoice and coin totals only. No purchase records or customer identity.', 'permissions' => ['finance.invoices.totals', 'finance.coins.totals']],
            'purchase-auditor' => ['name' => 'Purchase auditor', 'description' => 'Read purchase records and totals without payment confirmation or refunds.', 'permissions' => ['finance.invoices.totals', 'finance.coins.totals', 'finance.invoices.view', 'finance.coins.view']],
            'coin-operator' => ['name' => 'Coin operator', 'description' => 'Find accounts by ID, inspect wallets and grant coins individually. No deductions or bulk grants.', 'permissions' => ['users.view', 'users.search', 'users.details', 'users.wallet.view', 'users.coins.grant']],
            'plan-operator' => ['name' => 'Plan operator', 'description' => 'Find accounts by ID, inspect and assign individual plans. No bulk assignments.', 'permissions' => ['users.view', 'users.search', 'users.details', 'users.plan.view', 'users.assign_plan']],
        ];
    }
}
