<?php

namespace App\Modules\Admin\Controllers\Concerns;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Preserve the owner's existing account tools and audit panels. Restricted staff use projected views. */
trait SuperAdminAccountViews
{
    private function superAdminIndex(Request $request)
    {
        abort_unless(Auth::guard('admin')->user()?->isSuperAdmin(), 403);
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

        if ($request->filled('badge')) {
            $badgeId = (int) $request->badge;
            $query->whereHas('accountBadges', fn ($q) => $q->where('account_badges.id', $badgeId));
        }

        $query->with('accountBadges');

        // Column sorting happens in the query, not in the browser: the table
        // only ever holds one page of this result, so sorting it client-side
        // would order fifteen rows and call it the account list.
        $sort = \App\Support\TableSort::apply($query, $request, [
            'user'   => ['name', 'email'],
            // Sorting by plan_id would order by an internal id nobody sees.
            // The subquery sorts by the plan's own name instead.
            'plan'   => Plan::select('name')->whereColumn('plans.id', 'users.plan_id'),
            'status' => 'status',
            'joined' => 'created_at',
        ], defaultKey: 'joined', defaultDir: 'desc');

        $users = $query->paginate(15)->withQueryString();
        $plans = Plan::active()->ordered()->get();
        $badges = \App\Modules\Admin\Models\AccountBadge::orderBy('name')->get();

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
        $canManageAdminAccess = $operator && (
            $operator->hasPermission('users.grant_admin')
            || $operator->hasPermission('users.revoke_admin')
        );

        // Lowercased set of protected emails on this page so the view can
        // hide delete/suspend controls for protected accounts.
        $protectedEmails = ProtectedAccount::query()
            ->whereIn('email', $emails)
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->flip();

        // Id-keyed protected entries (email-less accounts) on this page.
        $protectedUserIds = ProtectedAccount::query()
            ->whereIn('user_id', collect($users->items())->pluck('id')->all())
            ->pluck('user_id')
            ->flip();

        return view('admin.users.index', compact('users', 'plans', 'badges', 'adminAccounts', 'canManageAdminAccess', 'protectedEmails', 'protectedUserIds', 'sort'));
    }

    private function superAdminShow(Request $request, User $user)
    {
        abort_unless(Auth::guard('admin')->user()?->isSuperAdmin(), 403);
        $user->load('plan', 'accountBadges');
        $plans = Plan::active()->ordered()->get();
        $badges = \App\Modules\Admin\Models\AccountBadge::orderBy('name')->get();
        $userBadgeIds = $user->accountBadges->pluck('id')->all();
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

        $isProtected = ProtectedAccount::isProtected($user);

        return view('admin.users.show', compact(
            'user', 'plans', 'badges', 'userBadgeIds', 'wallet', 'walletEnabled', 'walletTransactions',
            'roleAudits', 'auditFilters', 'auditRoleSlugs', 'auditActions',
            'auditSources', 'auditRanges', 'isProtected'
        ));
    }

}
