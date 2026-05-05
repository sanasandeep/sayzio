<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\UserRoleAuditExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Read-only super-admin panel surfacing the most recent CSV
 * downloads of the role-change audit. Closes the
 * audit-the-auditor loop: the exports themselves are now
 * recorded in `user_role_audit_exports`, and this page makes
 * those rows visible so unusual download activity (very large
 * pulls, bursts from a single actor, exports of accounts that
 * shouldn't be looked at) can be spotted.
 *
 * Restricted to super-admins because the data implicates
 * staff/admin behaviour, not the platform's end users — same
 * tier of trust as `DemoContentController`.
 */
class UserRoleAuditExportController extends Controller
{
    /**
     * 403 unless the current admin holds the `super-admin` role.
     * Mirrors `DemoContentController::requireSuperAdmin()` so the
     * gate behaves the same way across back-office surfaces.
     */
    protected function requireSuperAdmin(): void
    {
        $admin = Auth::guard('admin')->user();
        $role  = is_object($admin?->role)
            ? ($admin->role->slug ?? $admin->role->name ?? null)
            : ($admin->role ?? null);
        if (! in_array(strtolower((string) $role), ['super_admin', 'super-admin', 'superadmin'])) {
            throw new HttpException(403, 'Only super-admins can review role-audit downloads.');
        }
    }

    public function index(Request $request)
    {
        $this->requireSuperAdmin();

        $exports = UserRoleAuditExport::query()
            ->with([
                'actorUser:id,name,email',
                'actorAdmin:id,name,email',
                'targetUser:id,name,email',
            ])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.users.role-audit-exports', [
            'exports' => $exports,
            'scopes'  => [
                UserRoleAuditExport::SCOPE_FULL_POOL   => 'Full user pool',
                UserRoleAuditExport::SCOPE_SINGLE_USER => 'Single user',
            ],
        ]);
    }
}
