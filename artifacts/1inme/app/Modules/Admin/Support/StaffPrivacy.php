<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Admin;
use Illuminate\Http\Request;

final class StaffPrivacy
{
    public static function allows(Admin $actor, string $route): bool
    {
        if (StaffPermissionCatalog::fullIdentity($actor)) return true;
        // Reviewed projections / no-customer-data screens only. Others fail closed,
        // including exports, logs, impersonation and settings containing free text.
        return self::restrictedRoute($route);
    }

    public static function restrictedRoute(string $route): bool
    {
        return in_array($route, [
            'admin.dashboard', 'admin.meta.last-updated', 'admin.logout',
            'admin.switch-to-user', 'admin.users.index', 'admin.users.show',
            'admin.users.wallet.adjust', 'admin.users.assign-plan', 'admin.users.bulk',
            'admin.users.badges.update', 'admin.users.transfer-capability',
            'admin.users.suspend', 'admin.users.reactivate', 'admin.users.assign-link',
            'admin.finance.index', 'admin.finance.records', 'admin.finance.ai',
            'admin.roles.index', 'admin.roles.show', 'admin.roles.create', 'admin.roles.store',
            'admin.roles.duplicate', 'admin.roles.edit', 'admin.roles.update', 'admin.roles.destroy',
        ], true);
    }

    public static function enforce(Admin $actor, Request $request): void
    {
        abort_unless(self::allows($actor, (string) $request->route()?->getName()), 403,
            'This screen can reveal customer details. Name, email and phone visibility are required.');
    }
}
