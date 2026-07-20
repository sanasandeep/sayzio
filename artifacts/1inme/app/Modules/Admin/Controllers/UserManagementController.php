<?php

namespace App\Modules\Admin\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\Admin\Services\UserAccountNotifier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Services\ReferralService;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        if ($request->filled('badge')) {
            $badgeId = (int) $request->badge;
            $query->whereHas('accountBadges', fn ($q) => $q->where('account_badges.id', $badgeId));
        }

        $query->with('accountBadges');

        $users = $query->latest()->paginate(15)->withQueryString();
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

        return view('admin.users.index', compact('users', 'plans', 'badges', 'adminAccounts', 'canManageAdminAccess', 'protectedEmails', 'protectedUserIds'));
    }

    public function show(Request $request, User $user)
    {
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

    public function adjustWallet(Request $request, User $user, WalletService $wallets, AdminActionLogger $audit)
    {
        $data = $request->validate([
            'delta'  => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);
        try {
            $delta = (int) $data['delta'];
            // WalletService::adjust() already fires the user-facing
            // in-app + email "wallet adjusted" notification, so we only
            // need to record the operator action in the audit trail.
            $wallets->adjust($user, $delta, $data['reason'], optional(Auth::guard('admin')->user())->id);
            $audit->log(
                $delta >= 0 ? AdminActionLogger::COINS_GRANTED : AdminActionLogger::COINS_DEDUCTED,
                $user,
                ['coins' => abs($delta), 'reason' => $data['reason']]
            );
            return back()->with('success', 'Wallet adjusted.');
        } catch (\App\Services\Billing\InsufficientCoinsException $e) {
            return back()->with('error', "Adjustment would overdraw the wallet (balance {$e->balance}, needs {$e->required}).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not adjust wallet: ' . $e->getMessage());
        }
    }

    /**
     * Show the "Create account" form. Staff/admin creation controls are
     * only surfaced when the operator holds `staff.create` (the same
     * permission the dedicated Staff page enforces).
     */
    public function create()
    {
        $plans = Plan::active()->ordered()->get();
        $operator = Auth::guard('admin')->user();
        $canCreateStaff = $operator && $operator->hasPermission('staff.create');
        $roles = $canCreateStaff
            ? Role::where('guard', 'admin')->orderBy('name')->get()
            : collect();

        return view('admin.users.create', compact('plans', 'roles', 'canCreateStaff'));
    }

    /**
     * Provision a new user account (name, email, handle, initial plan,
     * optional starting coins, optional password/invite) and — where the
     * operator is permitted — a matching back-office staff record.
     */
    public function store(
        Request $request,
        WalletService $wallets,
        ReferralService $referrals,
        AdminActionLogger $audit
    ) {
        $operator = Auth::guard('admin')->user();
        $canCreateStaff = $operator && $operator->hasPermission('staff.create');

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'handle'         => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_.\-]+$/', 'unique:users,handle'],
            'plan_id'        => 'nullable|exists:plans,id',
            'starting_coins' => 'nullable|integer|min:1|max:1000000',
            'password'       => 'nullable|string|min:8',
            'send_invite'    => 'sometimes|boolean',
            'create_staff'   => 'sometimes|boolean',
            'role_id'        => 'required_if:create_staff,1|nullable|exists:roles,id',
        ]);

        $wantsStaff = $request->boolean('create_staff');
        if ($wantsStaff && ! $canCreateStaff) {
            return back()->withInput()->with('error', 'You do not have permission to create staff accounts.');
        }

        // Auto-generate a password when none is supplied; that path always
        // emails the user their credentials so they can sign in / reset.
        $providedPassword = $validated['password'] ?? null;
        $plainPassword = $providedPassword ?: Str::random(16);

        $planId = $validated['plan_id'] ?? optional(Plan::defaultPlan())->id;

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'handle'   => $validated['handle'] ?? null,
            'password' => Hash::make($plainPassword),
            'status'   => 'active',
            'plan_id'  => $planId,
        ]);

        if ($planId && ($plan = Plan::find($planId))) {
            $referrals->handlePlanActivation($user->fresh(), $plan);
        }

        $coins = (int) ($validated['starting_coins'] ?? 0);
        if ($coins > 0) {
            try {
                $wallets->adjust($user, $coins, 'Initial coin grant on account creation', $operator?->id);
            } catch (\Throwable $e) {
                // Account already exists; a failed coin grant shouldn't abort it.
            }
        }

        $staffCreated = false;
        if ($wantsStaff && $canCreateStaff && ! empty($validated['role_id'])) {
            if (! Admin::where('email', $validated['email'])->exists()) {
                Admin::create([
                    'name'     => $validated['name'],
                    'email'    => $validated['email'],
                    'password' => Hash::make($plainPassword),
                    'role_id'  => $validated['role_id'],
                    'status'   => 'active',
                ]);
                $staffCreated = true;
            }
        }

        $audit->log(AdminActionLogger::ACCOUNT_CREATED, $user, [
            'plan_id'        => $planId,
            'starting_coins' => $coins,
            'staff_created'  => $staffCreated,
            'invited'        => $request->boolean('send_invite') || ! $providedPassword,
        ]);

        if ($request->boolean('send_invite') || ! $providedPassword) {
            $this->sendCredentialsEmail($user, $plainPassword);
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Account created successfully.' . ($staffCreated ? ' Staff access granted.' : ''));
    }

    /**
     * Assign a plan, optionally as a comp grant (free for N days). A comp
     * grant records `comp_plan_expires_at` so the scheduled revert job can
     * drop the account back to the default plan when the window elapses; a
     * permanent assignment clears any prior comp window.
     */
    public function assignPlan(
        Request $request,
        User $user,
        ReferralService $referrals,
        AdminActionLogger $audit,
        UserAccountNotifier $notifier
    ) {
        $data = $request->validate([
            'plan_id'   => 'required|exists:plans,id',
            'comp_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $previousPlanId = $user->plan_id;
        $compDays = $data['comp_days'] ?? null;

        $attrs = ['plan_id' => $plan->id];
        if ($compDays) {
            $expires = now()->addDays((int) $compDays);
            $attrs['plan_expires_at']      = $expires;
            $attrs['comp_plan_expires_at'] = $expires;
            $attrs['comp_plan_granted_by'] = Auth::guard('admin')->id();
        } else {
            $attrs['comp_plan_expires_at'] = null;
            $attrs['comp_plan_granted_by'] = null;
        }
        $user->update($attrs);

        if ($plan->id != $previousPlanId) {
            $referrals->handlePlanActivation($user->fresh(), $plan);
        }

        // Grant the plan's included coins — this manual path has no invoice,
        // so ActivateSubscription::run() never fires for it. Idempotent
        // per (user, plan, day) so a double-click can't double-credit.
        app(ActivateSubscription::class)->grantPlanCoinsForManualAssignment($user->fresh(), $plan);

        $audit->log(AdminActionLogger::PLAN_ASSIGNED, $user, [
            'plan_id'          => $plan->id,
            'plan_name'        => $plan->name,
            'previous_plan_id' => $previousPlanId,
            'comp_days'        => $compDays,
        ]);

        $notifier->planAssigned($user->fresh(), $plan->name, $compDays);

        return back()->with('success', 'Plan assigned' . ($compDays ? " (complimentary for {$compDays} days)" : '') . '.');
    }

    /**
     * Place an account on a temporary hold with a required reason and an
     * optional auto-reactivation date. Enforced at login/session by the
     * suspension middleware + auth checks.
     */
    public function suspend(
        Request $request,
        User $user,
        AdminActionLogger $audit,
        UserAccountNotifier $notifier
    ) {
        if (ProtectedAccount::isProtected($user)) {
            $audit->log(AdminActionLogger::SUSPEND_BLOCKED, $user, [
                'email'  => $user->email,
                'reason' => 'Account is protected and cannot be suspended.',
            ]);
            return back()->with('error', 'This account is protected and cannot be suspended.');
        }

        $data = $request->validate([
            'reason'        => 'required|string|max:1000',
            'reactivate_at' => 'nullable|date|after:now',
        ]);

        $reactivateAt = ! empty($data['reactivate_at'])
            ? \Illuminate\Support\Carbon::parse($data['reactivate_at'])
            : null;

        $user->update([
            'suspended_at'      => now(),
            'suspension_reason' => $data['reason'],
            'suspended_by'      => Auth::guard('admin')->id(),
            'reactivate_at'     => $reactivateAt,
        ]);

        $audit->log(AdminActionLogger::ACCOUNT_SUSPENDED, $user, [
            'reason'        => $data['reason'],
            'reactivate_at' => $reactivateAt?->toDateTimeString(),
        ]);

        $notifier->suspended($user->fresh(), $data['reason'], $reactivateAt);

        return back()->with('success', 'Account suspended.');
    }

    /** Lift a temporary hold immediately. */
    public function reactivate(
        Request $request,
        User $user,
        AdminActionLogger $audit,
        UserAccountNotifier $notifier
    ) {
        if (! $user->isSuspended()) {
            return back()->with('info', 'Account is not currently suspended.');
        }

        $user->update([
            'suspended_at'      => null,
            'suspension_reason' => null,
            'suspended_by'      => null,
            'reactivate_at'     => null,
        ]);

        $audit->log(AdminActionLogger::ACCOUNT_REACTIVATED, $user, []);
        $notifier->reactivated($user->fresh());

        return back()->with('success', 'Account reactivated.');
    }

    /**
     * Replace the full set of account badges attached to a single user.
     * Staff-only labelling — the user sees but cannot change these. A
     * missing/empty `badge_ids` clears every badge from the account.
     */
    public function updateBadges(Request $request, User $user, AdminActionLogger $audit)
    {
        $data = $request->validate([
            'badge_ids'   => 'nullable|array',
            'badge_ids.*' => 'integer|exists:account_badges,id',
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['badge_ids'] ?? [])));
        $previousIds = $user->accountBadges()->pluck('account_badges.id')->all();

        $user->accountBadges()->sync($ids);

        $audit->log(AdminActionLogger::BADGE_ASSIGNED, $user, [
            'badge_ids'          => $ids,
            'previous_badge_ids' => $previousIds,
        ]);

        return back()->with('success', 'Badges updated.');
    }

    /**
     * Bulk "assign plan" / "grant coins" over a set of selected users,
     * reusing the single-user logic. Coin grants go through the wallet
     * chokepoint with a per-(batch,user) idempotency key so a duplicate
     * submit can't double-credit.
     */
    public function bulkAction(
        Request $request,
        WalletService $wallets,
        ReferralService $referrals,
        AdminActionLogger $audit,
        UserAccountNotifier $notifier
    ) {
        $data = $request->validate([
            'action'      => 'required|in:assign_plan,grant_coins,assign_badge,remove_badge',
            'user_ids'    => 'required|array|min:1',
            'user_ids.*'  => 'integer|exists:users,id',
            'plan_id'     => 'required_if:action,assign_plan|nullable|exists:plans,id',
            'coins'       => 'required_if:action,grant_coins|nullable|integer|min:1|max:1000000',
            'reason'      => 'required_if:action,grant_coins|nullable|string|max:255',
            'badge_id'    => 'required_if:action,assign_badge,remove_badge|nullable|exists:account_badges,id',
        ]);

        // Per-action gate: this endpoint multiplexes two distinct
        // capabilities, so enforce the specific permission for the chosen
        // action server-side. The route only guarantees the operator holds
        // at least one of the two bulk permissions.
        $operator = Auth::guard('admin')->user();
        $needed = match ($data['action']) {
            'assign_plan'                  => 'users.bulk_plan',
            'grant_coins'                  => 'users.bulk_credits',
            'assign_badge', 'remove_badge' => 'users.edit',
        };
        if (! $operator || ! $operator->hasPermission($needed)) {
            abort(403, 'Unauthorized action.');
        }

        $users = User::whereIn('id', array_unique($data['user_ids']))->get();
        $operatorId = Auth::guard('admin')->id();
        $count = 0;

        if (in_array($data['action'], ['assign_badge', 'remove_badge'], true)) {
            $badge = \App\Modules\Admin\Models\AccountBadge::findOrFail($data['badge_id']);
            $assigning = $data['action'] === 'assign_badge';

            foreach ($users as $u) {
                if ($assigning) {
                    // Idempotent: re-assigning an already-attached badge is a no-op.
                    $u->accountBadges()->syncWithoutDetaching([$badge->id]);
                } else {
                    $u->accountBadges()->detach($badge->id);
                }
                $audit->log(
                    $assigning ? AdminActionLogger::BADGE_ASSIGNED : AdminActionLogger::BADGE_REMOVED,
                    $u,
                    ['badge_id' => $badge->id, 'badge_name' => $badge->name, 'bulk' => true]
                );
                $count++;
            }

            $verb = $assigning ? 'Assigned' : 'Removed';
            $prep = $assigning ? 'to' : 'from';
            return back()->with('success', "{$verb} badge \"{$badge->name}\" {$prep} {$count} account(s).");
        }

        if ($data['action'] === 'assign_plan') {
            $plan = Plan::findOrFail($data['plan_id']);
            foreach ($users as $u) {
                $prev = $u->plan_id;
                $u->update([
                    'plan_id'              => $plan->id,
                    'comp_plan_expires_at' => null,
                    'comp_plan_granted_by' => null,
                ]);
                if ($plan->id != $prev) {
                    $referrals->handlePlanActivation($u->fresh(), $plan);
                }
                // Included-coin grant for the manually assigned plan (idempotent
                // per user/plan/day, so a duplicate bulk submit is a no-op).
                app(ActivateSubscription::class)->grantPlanCoinsForManualAssignment($u->fresh(), $plan);
                $audit->log(AdminActionLogger::PLAN_ASSIGNED, $u, [
                    'plan_id'          => $plan->id,
                    'plan_name'        => $plan->name,
                    'previous_plan_id' => $prev,
                    'bulk'             => true,
                ]);
                $notifier->planAssigned($u->fresh(), $plan->name, null);
                $count++;
            }

            return back()->with('success', "Assigned {$plan->name} to {$count} account(s).");
        }

        // grant_coins
        $coins  = (int) $data['coins'];
        $reason = (string) $data['reason'];
        $batch  = (string) Str::uuid();

        foreach ($users as $u) {
            try {
                $wallets->adjust($u, $coins, $reason, $operatorId, [
                    'idempotency_key' => "bulk-grant:{$batch}:{$u->id}",
                ]);
                $audit->log(AdminActionLogger::COINS_GRANTED, $u, [
                    'coins'  => $coins,
                    'reason' => $reason,
                    'bulk'   => true,
                    'batch'  => $batch,
                ]);
                $count++;
            } catch (\Throwable $e) {
                // Skip this user, keep going with the rest of the batch.
            }
        }

        return back()->with('success', "Granted {$coins} coins to {$count} account(s).");
    }

    /** Best-effort credentials/invite email for a freshly created account. */
    protected function sendCredentialsEmail(User $user, string $plainPassword): void
    {
        if (! $user->email) {
            return;
        }
        try {
            \App\Modules\Common\Services\Emailer::send('account.credentials', $user->email, [
                'app_name'  => config('app.name'),
                'email'     => $user->email,
                'password'  => $plainPassword,
                'login_url' => route('user.login'),
            ], ['user' => $user]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Account credentials email skipped: ' . $e->getMessage());
        }
    }

    public function update(Request $request, User $user, ReferralService $referrals, AdminActionLogger $audit)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive,banned,suspended',
            'plan_id' => 'sometimes|nullable|exists:plans,id',
            'plan_expires_at' => 'sometimes|nullable|date',
        ]);

        // Granular enforcement: this basic edit route is gated only by
        // `users.edit`, so it must NOT become a backdoor for privileged
        // mutations. Plan assignment requires `users.assign_plan` and account
        // status changes require `users.suspend`; an operator lacking the
        // dedicated permission cannot change those fields here (name edits
        // still go through). Mirrors the dedicated assign-plan / suspend routes.
        $operator = Auth::guard('admin')->user();
        if (! $operator?->hasPermission('users.assign_plan')) {
            unset($validated['plan_id'], $validated['plan_expires_at']);
        }
        if (! $operator?->hasPermission('users.suspend')) {
            unset($validated['status']);
        }

        // Changing a protected account's status to anything that blocks
        // sign-in (banned/suspended/inactive) is a suspend in disguise —
        // block it server-side regardless of which surface initiated it.
        if (array_key_exists('status', $validated)
            && in_array($validated['status'], ['banned', 'suspended', 'inactive'], true)
            && $validated['status'] !== $user->status
            && ProtectedAccount::isProtected($user)) {
            $audit->log(AdminActionLogger::SUSPEND_BLOCKED, $user, [
                'email'           => $user->email,
                'attempted_status' => $validated['status'],
                'reason'          => 'Account is protected and cannot be suspended.',
            ]);
            return redirect()->back()->with('error', 'This account is protected and cannot be suspended.');
        }

        $previousPlanId = $user->plan_id;
        $user->update($validated);

        // If the admin just moved this user onto a different paid plan, treat
        // it as a plan activation and run the referral reward engine. The
        // service is idempotent so repeated saves won't double-pay.
        if (array_key_exists('plan_id', $validated) && $validated['plan_id'] && $validated['plan_id'] != $previousPlanId) {
            $newPlan = Plan::find($validated['plan_id']);
            if ($newPlan) {
                $referrals->handlePlanActivation($user->fresh(), $newPlan);
                // Mirror the dedicated assign-plan route: an admin plan change
                // also grants the plan's included coins (idempotent per
                // user/plan/day).
                app(ActivateSubscription::class)->grantPlanCoinsForManualAssignment($user->fresh(), $newPlan);
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

    public function destroy(User $user, AdminActionLogger $audit)
    {
        if (ProtectedAccount::isProtected($user)) {
            $audit->log(AdminActionLogger::DELETE_BLOCKED, $user, [
                'email'  => $user->email,
                'reason' => 'Account is protected and cannot be deleted.',
            ]);
            return redirect()->route('admin.users.index')
                ->with('error', 'This account is protected and cannot be deleted.');
        }

        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Set or replace the password for a specific user account so the
     * user (or a bridged admin) can sign in at /login with the new
     * credential immediately. Gated behind `users.edit` and respects
     * the ProtectedAccount constraint so protected accounts cannot
     * have their passwords changed via this surface.
     */
    public function setPassword(Request $request, User $user, AdminActionLogger $audit)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        if (ProtectedAccount::isProtected($user)) {
            return back()->with('error', 'This account is protected and its password cannot be changed.');
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        $audit->log(AdminActionLogger::USER_PASSWORD_SET, $user, []);

        return back()->with('success', 'Password updated. The user can now sign in with the new password.');
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
