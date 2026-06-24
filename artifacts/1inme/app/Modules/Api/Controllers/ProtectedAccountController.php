<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Bearer-token parity for the web back-office "Protected accounts" page —
 * the super-admin-managed list of accounts that can never be deleted or
 * suspended (the canonical guard is {@see ProtectedAccount::isProtected()}).
 *
 * As on the web: staff with `users.view` may read the list, but only a
 * super-admin may add or remove entries, and the two seeded, hard-locked
 * entries (super-admin + demo) can never be removed. The operator's
 * authority comes from their email-linked back-office Admin record, exactly
 * like {@see AdminAccessController}.
 */
class ProtectedAccountController extends Controller
{
    use ApiResponses;

    /**
     * Read the protected-accounts list. Gated behind `users.view`.
     */
    public function index(Request $request)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('users.view')) {
            return $this->forbidden('You are not allowed to view protected accounts.');
        }

        $accounts = ProtectedAccount::query()
            ->orderByDesc('locked')
            ->orderBy('email')
            ->get();

        return $this->ok([
            'accounts'   => $accounts->map(fn (ProtectedAccount $a) => $this->row($a))->all(),
            'can_manage' => $admin->isSuperAdmin(),
        ]);
    }

    /**
     * Add an account to the protected list. Super-admin only.
     */
    public function store(Request $request, AdminActionLogger $audit)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin) {
            return $this->forbidden('You are not allowed to manage protected accounts.');
        }
        if (! $admin->isSuperAdmin()) {
            return $this->forbidden('Only super-admins can manage protected accounts.');
        }

        $data = $request->validate([
            'email' => 'required|email|max:191',
            'label' => 'nullable|string|max:191',
        ]);

        $email = ProtectedAccount::normalizeEmail($data['email']);

        if (ProtectedAccount::isProtectedEmail($email)) {
            return $this->fail('That account is already protected.', 422, 'already_protected');
        }

        ProtectedAccount::create([
            'email'      => $email,
            'locked'     => false,
            'label'      => $data['label'] ?? null,
            'created_by' => $admin->id,
        ]);

        // Snapshot the matching user (if any) so the audit row links back to
        // the account; the email is always recorded in details.
        $target = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $audit->log(AdminActionLogger::PROTECTED_ADDED, $target, [
            'email' => $email,
            'label' => $data['label'] ?? null,
        ], $admin);

        return $this->index($request);
    }

    /**
     * Remove an account from the protected list. Super-admin only; the
     * hard-locked seeds (super-admin + demo) can never be removed.
     */
    public function destroy(Request $request, int $protectedAccountId, AdminActionLogger $audit)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin) {
            return $this->forbidden('You are not allowed to manage protected accounts.');
        }
        if (! $admin->isSuperAdmin()) {
            return $this->forbidden('Only super-admins can manage protected accounts.');
        }

        $protectedAccount = ProtectedAccount::find($protectedAccountId);
        if (! $protectedAccount) {
            return $this->notFound('Protected account not found.');
        }

        if ($protectedAccount->isLocked()) {
            return $this->fail(
                'This account is permanently protected and cannot be removed.',
                422,
                'protected_locked'
            );
        }

        $email = $protectedAccount->email;
        $target = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        $protectedAccount->delete();

        $audit->log(AdminActionLogger::PROTECTED_REMOVED, $target, [
            'email' => $email,
        ], $admin);

        return $this->index($request);
    }

    /**
     * @return array{id:int,email:string,label:?string,locked:bool}
     */
    protected function row(ProtectedAccount $account): array
    {
        return [
            'id'     => $account->id,
            'email'  => $account->email,
            'label'  => $account->label,
            'locked' => $account->isLocked(),
        ];
    }

    /**
     * The signed-in user's active back-office Admin record, or null.
     * Mirrors {@see AdminAccessController::activeAdmin()}.
     */
    protected function activeAdmin(Request $request): ?Admin
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return null;
        }

        $admin = $user->adminAccount();
        return ($admin && $admin->status === 'active') ? $admin : null;
    }
}
