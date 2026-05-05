<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Services\ReferralService;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('plan');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan')) {
            $query->where('plan_id', $request->plan);
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $plans = Plan::active()->ordered()->get();

        return view('admin.users.index', compact('users', 'plans'));
    }

    public function show(User $user)
    {
        $user->load('plan');
        $plans = Plan::active()->ordered()->get();
        $wallet = app(WalletService::class)->walletFor($user);
        $walletEnabled = WalletService::isEnabled();
        $walletTransactions = $wallet->transactions()->limit(10)->get();

        // Latest role grants/revokes against this user. The route is
        // gated by `users.view`, but the task restricts visibility of
        // role-change audits to operators with `users.edit` (the same
        // permission required to mutate roles). We skip the query
        // entirely for read-only viewers so the data never reaches
        // the response body — the view also hides the panel.
        $admin = Auth::guard('admin')->user();
        $canSeeRoleAudits = $admin && $admin->hasPermission('users.edit');
        $roleAudits = $canSeeRoleAudits
            ? UserRoleAudit::query()
                ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email'])
                ->where('target_user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
            : collect();

        return view('admin.users.show', compact(
            'user', 'plans', 'wallet', 'walletEnabled', 'walletTransactions', 'roleAudits'
        ));
    }

    public function adjustWallet(Request $request, User $user, WalletService $wallets)
    {
        $data = $request->validate([
            'delta'  => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);
        try {
            $wallets->adjust($user, (int) $data['delta'], $data['reason'], optional(Auth::user())->id);
            return back()->with('success', 'Wallet adjusted.');
        } catch (\App\Services\Billing\InsufficientCoinsException $e) {
            return back()->with('error', "Adjustment would overdraw the wallet (balance {$e->balance}, needs {$e->required}).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not adjust wallet: ' . $e->getMessage());
        }
    }

    public function update(Request $request, User $user, ReferralService $referrals)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive,banned',
            'plan_id' => 'sometimes|nullable|exists:plans,id',
            'plan_expires_at' => 'sometimes|nullable|date',
        ]);

        $previousPlanId = $user->plan_id;
        $user->update($validated);

        // If the admin just moved this user onto a different paid plan, treat
        // it as a plan activation and run the referral reward engine. The
        // service is idempotent so repeated saves won't double-pay.
        if (array_key_exists('plan_id', $validated) && $validated['plan_id'] && $validated['plan_id'] != $previousPlanId) {
            $newPlan = Plan::find($validated['plan_id']);
            if ($newPlan) {
                $referrals->handlePlanActivation($user->fresh(), $newPlan);
            }
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function impersonate(User $user)
    {
        $admin = Auth::guard('admin')->user();
        session(['impersonate_user_id' => $user->id, 'admin_id' => $admin->id]);
        Auth::guard('web')->login($user);

        return redirect()->route('user.dashboard')->with('info', 'You are now impersonating ' . $user->name);
    }

    public function stopImpersonation()
    {
        $adminId = session('admin_id');
        session()->forget(['impersonate_user_id', 'admin_id']);
        Auth::guard('web')->logout();

        return redirect()->route('admin.users.index')->with('success', 'Impersonation stopped.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }
}
