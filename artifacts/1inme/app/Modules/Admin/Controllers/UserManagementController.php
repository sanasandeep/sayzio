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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Admin status for the current page, in one query. The admin pool
        // is a separate table linked to a user by email, so we batch-load
        // matching admin records keyed by lowercased email rather than
        // firing one lookup per row.
        $emails = $users->getCollection()
            ->pluck('email')
            ->filter()
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->unique()
            ->values()
            ->all();

        $adminAccounts = collect();
        if (! empty($emails)) {
            $adminAccounts = \App\Modules\Admin\Models\Admin::query()
                ->with('role')
                ->where(function ($q) use ($emails) {
                    foreach ($emails as $email) {
                        $q->orWhereRaw('lower(email) = ?', [$email]);
                    }
                })
                ->get()
                ->keyBy(fn ($a) => strtolower(trim((string) $a->email)));
        }

        $operator = Auth::guard('admin')->user();
        $canManageAdminAccess = $operator && $operator->hasPermission('staff.create');

        return view('admin.users.index', compact('users', 'plans', 'adminAccounts', 'canManageAdminAccess'));
    }

    public function show(Request $request, User $user)
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
        //
        // Same simple filter inputs (date range, actor, role, action,
        // source) the per-user back-office roles page exposes — kept
        // in sync via the shared shape so the panel's CSV export
        // download matches what the reviewer is looking at.
        $admin = Auth::guard('admin')->user();
        $canSeeRoleAudits = $admin && $admin->hasPermission('users.edit');

        $auditFilters = $this->panelAuditFilters($request);

        $roleAudits = $canSeeRoleAudits
            ? UserRoleAudit::query()
                ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email'])
                ->where('target_user_id', $user->id)
                ->bySourceFilter(UserRoleAudit::normaliseSourceFilter($auditFilters['audit_source']))
                ->betweenDates(
                    UserRoleAudit::normaliseRangePreset($auditFilters['audit_range']),
                    $auditFilters['audit_from'],
                    $auditFilters['audit_to'],
                )
                ->filtered([
                    'actor'  => $auditFilters['actor'],
                    'role'   => $auditFilters['role'],
                    'action' => $auditFilters['action'],
                ])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
            : collect();

        $auditRoleSlugs = $canSeeRoleAudits ? UserRoleAudit::distinctRoleSlugs() : [];
        $auditActions   = UserRoleAudit::actionLabels();
        $auditSources   = UserRoleAudit::sourceFilters();
        $auditRanges    = UserRoleAudit::rangeFilters();

        return view('admin.users.show', compact(
            'user', 'plans', 'wallet', 'walletEnabled', 'walletTransactions',
            'roleAudits', 'auditFilters', 'auditRoleSlugs', 'auditActions',
            'auditSources', 'auditRanges'
        ));
    }

    /**
     * Filter inputs the role-change panel on the user-detail page
     * exposes. Mirrors the shape used by `UserRoleController` so the
     * "Export CSV" link can pass the same query string straight
     * through to the per-user export endpoint.
     *
     * @return array{actor:string,role:string,action:string,source:string,from:string,to:string}
     */
    protected function panelAuditFilters(Request $request): array
    {
        return [
            'actor'        => trim((string) $request->get('actor', '')),
            'role'         => trim((string) $request->get('role', '')),
            'action'       => (string) $request->get('action', ''),
            'audit_source' => (string) ($request->get('audit_source', '') ?? ''),
            'audit_range'  => (string) ($request->get('audit_range', '') ?? ''),
            'audit_from'   => trim((string) $request->get('audit_from', '')),
            'audit_to'     => trim((string) $request->get('audit_to', '')),
        ];
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

    /**
     * Dedicated, paginated role-change audit page for the back-office.
     * Mirrors `UserAccessController::audit` but lives behind the admin
     * guard so reviewers without a web session can still pull the
     * full ledger. Filters and CSV export use the same shared
     * model-level helpers so the two surfaces stay in sync.
     */
    public function roleAudits(Request $request)
    {
        $filters = $this->roleAuditFilters($request);

        $audits = UserRoleAudit::query()
            ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email', 'targetUser:id,name,email'])
            ->filtered($filters)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $targetUser = null;
        if ($filters['target'] !== '' && ctype_digit($filters['target'])) {
            $targetUser = User::query()
                ->select('id', 'name', 'email')
                ->find((int) $filters['target']);
        }

        return view('admin.users.role-audits', [
            'audits'      => $audits,
            'filters'     => $filters,
            'roleSlugs'   => UserRoleAudit::distinctRoleSlugs(),
            'actions'     => UserRoleAudit::actionLabels(),
            'sources'     => UserRoleAudit::sourceLabels(),
            'targetUser'  => $targetUser,
        ]);
    }

    public function roleAuditsExport(Request $request): StreamedResponse
    {
        $filters = $this->roleAuditFilters($request);

        $query = UserRoleAudit::query()->filtered($filters);

        $filename = 'role-audit-' . now()->format('Ymd-His') . '.csv';
        return UserRoleAudit::streamCsv($query, $filename);
    }

    /**
     * @return array{actor:string,target:string,role:string,action:string,source:string,from:string,to:string}
     */
    protected function roleAuditFilters(Request $request): array
    {
        return [
            'actor'  => trim((string) $request->get('actor', '')),
            'target' => trim((string) $request->get('target', '')),
            'role'   => trim((string) $request->get('role', '')),
            'action' => (string) $request->get('action', ''),
            'source' => (string) $request->get('source', ''),
            'from'   => trim((string) $request->get('from', '')),
            'to'     => trim((string) $request->get('to', '')),
        ];
    }
}
