<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\MasterPasswordLogin;
use App\Services\Integrations\MasterPasswordSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Admin "Master Password" settings page. Lets a super-admin set, change,
 * clear, and enable/disable a single master override password, and review
 * the audit trail of every login that used it.
 *
 * Restricted to super-admins (mirrors the ProtectedAccountController gate):
 * the route carries the `settings.manage` permission, and the super-admin
 * requirement is enforced here in the controller.
 */
class MasterPasswordController extends Controller
{
    /** Abort unless the current admin is a super-admin. */
    private function ensureSuperAdmin(): void
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin instanceof Admin || ! $admin->isSuperAdmin()) {
            throw new HttpException(403, 'Only super-admins can manage the master password.');
        }
    }

    public function index()
    {
        $this->ensureSuperAdmin();

        $logins = MasterPasswordLogin::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.master-password.index', [
            'status'       => MasterPasswordSettings::status(),
            'isEnabled'    => MasterPasswordSettings::isEnabled(),
            'hasPassword'  => MasterPasswordSettings::hasPassword(),
            'isActive'     => MasterPasswordSettings::isActive(),
            'logins'       => $logins,
        ]);
    }

    public function update(Request $request)
    {
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'enabled'        => ['nullable', 'boolean'],
            'password'       => ['nullable', 'string', 'min:8', 'max:200'],
            'clear_password' => ['nullable', 'boolean'],
        ]);

        // Clearing wins over everything else: remove the stored password and
        // disable the override.
        if ($request->boolean('clear_password')) {
            MasterPasswordSettings::clear();
            return redirect()->route('admin.master-password.index')
                ->with('success', 'Master password cleared. The override is now disabled.');
        }

        $newPassword = $data['password'] ?? null;
        if ($newPassword !== null && $newPassword !== '') {
            MasterPasswordSettings::setPassword($newPassword);
        }

        // Can't enable the override without a password configured.
        $wantEnabled = $request->boolean('enabled');
        if ($wantEnabled && ! MasterPasswordSettings::hasPassword()) {
            return back()->withErrors([
                'password' => 'Set a master password before enabling the override.',
            ]);
        }

        MasterPasswordSettings::setEnabled($wantEnabled);

        return redirect()->route('admin.master-password.index')
            ->with('success', 'Master password settings saved.');
    }
}
