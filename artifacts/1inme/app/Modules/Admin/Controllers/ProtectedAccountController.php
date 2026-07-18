<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Superadmin-managed list of accounts that can never be deleted or
 * suspended. Regular staff with `users.view` may read the list, but
 * only a superadmin can add or remove entries. The two seeded,
 * hard-locked entries (superadmin + demo) can never be removed.
 */
class ProtectedAccountController extends Controller
{
    /**
     * Edits to the protected list are restricted to super-admins. Other
     * admin staff may view the page (gated by `users.view`) but the
     * add/remove actions 403.
     */
    protected function requireSuperAdmin(): void
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin instanceof Admin || ! $admin->isSuperAdmin()) {
            throw new HttpException(403, 'Only super-admins can manage protected accounts.');
        }
    }

    public function index()
    {
        $accounts = ProtectedAccount::query()
            ->orderByDesc('locked')
            ->orderBy('email')
            ->get();

        // Resolve the users behind id-keyed entries so the list can show
        // who an email-less entry protects.
        $userIds = $accounts->pluck('user_id')->filter()->all();
        $usersById = empty($userIds)
            ? collect()
            : User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $admin = Auth::guard('admin')->user();
        $canManage = $admin instanceof Admin && $admin->isSuperAdmin();

        return view('admin.protected-accounts.index', compact('accounts', 'canManage', 'usersById'));
    }

    public function store(Request $request, AdminActionLogger $audit)
    {
        $this->requireSuperAdmin();

        $data = $request->validate([
            'email'   => 'nullable|required_without:user_id|email|max:191',
            'user_id' => 'nullable|required_without:email|integer|min:1',
            'label'   => 'nullable|string|max:191',
        ]);

        $email = ProtectedAccount::normalizeEmail($data['email'] ?? null);
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        // An id-keyed entry must point at a real user. If that user has an
        // email, key the entry by email instead (covers a linked Admin too).
        $target = null;
        if ($userId) {
            $target = User::find($userId);
            if (! $target) {
                return back()->with('error', 'No user found with that ID.');
            }
            if ($email === '' && $target->email) {
                $email = ProtectedAccount::normalizeEmail($target->email);
            }
        }

        if ($email !== '' && ProtectedAccount::isProtectedEmail($email)) {
            return back()->with('info', 'That account is already protected.');
        }
        if ($userId && ProtectedAccount::isProtectedUserId($userId)) {
            return back()->with('info', 'That account is already protected.');
        }

        ProtectedAccount::create([
            'email'      => $email !== '' ? $email : null,
            'user_id'    => $email !== '' ? null : $userId,
            'locked'     => false,
            'label'      => $data['label'] ?? null,
            'created_by' => Auth::guard('admin')->id(),
        ]);

        // Snapshot the matching user (if any) so the audit row links back
        // to the account; the key is always recorded in details.
        if (! $target && $email !== '') {
            $target = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        }
        $audit->log(AdminActionLogger::PROTECTED_ADDED, $target, [
            'email'   => $email !== '' ? $email : null,
            'user_id' => $email !== '' ? null : $userId,
            'label'   => $data['label'] ?? null,
        ]);

        return back()->with('success', 'Account added to the protected list.');
    }

    public function destroy(ProtectedAccount $protectedAccount, AdminActionLogger $audit)
    {
        $this->requireSuperAdmin();

        if ($protectedAccount->isLocked()) {
            return back()->with('error', 'This account is permanently protected and cannot be removed.');
        }

        $email = $protectedAccount->email;
        $userId = $protectedAccount->user_id;
        $target = $userId
            ? User::find($userId)
            : User::query()->whereRaw('lower(email) = ?', [(string) $email])->first();

        $protectedAccount->delete();

        $audit->log(AdminActionLogger::PROTECTED_REMOVED, $target, [
            'email'   => $email,
            'user_id' => $userId,
        ]);

        return back()->with('success', 'Account removed from the protected list.');
    }
}
