<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;

/** Explicit capabilities. Legacy bundles are migrated once, never implied at runtime. */
final class StaffPermissionCatalog
{
    public static function definitions(): array
    {
        $definitions = [
            'users.details' => ['group' => 'User visibility', 'name' => 'Open account details', 'description' => 'Open the account workbench; does not reveal identity, contact data or finances.', 'legacy' => 'users.view'],
            'users.identity.view' => ['group' => 'User visibility', 'name' => 'Reveal name and handle', 'description' => 'Without this, reviewed account screens use only the internal account number. Unreviewed identity-bearing screens are blocked.', 'legacy' => 'users.view'],
            'users.email.view' => ['group' => 'User visibility', 'name' => 'Reveal email addresses', 'description' => 'Independent of name and phone visibility.', 'legacy' => 'users.view'],
            'users.phone.view' => ['group' => 'User visibility', 'name' => 'Reveal phone numbers', 'description' => 'Independent of name and email visibility.', 'legacy' => 'users.view'],
            'users.activity.view' => ['group' => 'User visibility', 'name' => 'View login and account activity', 'description' => 'Login dates, timezone and the separate activity log. Requires identity visibility for legacy audit screens.', 'legacy' => 'users.view'],
            'users.search' => ['group' => 'User search', 'name' => 'Search by account number', 'description' => 'Search an exact account ID. Does not grant identity lookup.', 'legacy' => 'users.view'],
            'users.search_identity' => ['group' => 'User search', 'name' => 'Search names, emails, handles and phones', 'description' => 'Requires permission to reveal each searched field; hidden fields cannot be queried.', 'legacy' => 'users.view'],
            'users.filter' => ['group' => 'User search', 'name' => 'Filter accounts', 'description' => 'Filter by status, plan, badge and joined date; does not grant financial filters.', 'legacy' => 'users.view'],
            'users.plan.view' => ['group' => 'User display', 'name' => 'View assigned plan', 'description' => 'Display the current plan and expiry. Does not permit a plan change.', 'legacy' => 'users.view'],
            'users.status.view' => ['group' => 'User display', 'name' => 'View account status', 'description' => 'Display active/suspended state.', 'legacy' => 'users.view'],
            'users.joined.view' => ['group' => 'User display', 'name' => 'View registration date', 'description' => 'Display account creation date.', 'legacy' => 'users.view'],
            'users.badges.view' => ['group' => 'User display', 'name' => 'View account badges', 'description' => 'Display assigned badges.', 'legacy' => 'users.view'],
            'users.wallet.view' => ['group' => 'User display', 'name' => 'View coin balance', 'description' => 'Balance only. Transaction history is separate.', 'legacy' => 'users.view'],
            'users.wallet.history' => ['group' => 'User display', 'name' => 'View coin transaction history', 'description' => 'Show transaction dates, types and amounts. Free-text reasons are omitted from the account workbench.', 'legacy' => 'users.view'],
            'users.coins.grant' => ['group' => 'Individual assignments', 'name' => 'Grant coins to one user', 'description' => 'Credit a wallet. Does not permit debits or bulk grants.', 'legacy' => 'users.credits'],
            'users.coins.deduct' => ['group' => 'Individual assignments', 'name' => 'Deduct coins from one user', 'description' => 'Debit a wallet. Does not permit grants.', 'legacy' => 'users.credits'],
            'users.plan.comp' => ['group' => 'Individual assignments', 'name' => 'Grant a temporary complimentary plan', 'description' => 'Allow comp_days on the dedicated plan assignment action. Also requires Assign / Comp Plans.', 'legacy' => 'users.assign_plan'],
            'users.badges.assign' => ['group' => 'Individual assignments', 'name' => 'Assign or remove account badges', 'description' => 'Does not grant profile editing or bulk badge changes.', 'legacy' => 'users.edit'],
            'users.badges.bulk' => ['group' => 'Individual assignments', 'name' => 'Assign or remove badges in bulk', 'description' => 'Separate from individual badges and bulk coins/plans.', 'legacy' => 'users.edit'],
            'users.transfer.manage' => ['group' => 'Individual assignments', 'name' => 'Enable user asset-transfer capability', 'description' => 'Allow a user to transfer their own assets. Does not move any asset itself.', 'legacy' => 'users.edit'],
            'users.links.assign' => ['group' => 'Individual assignments', 'name' => 'Reassign a link to a user', 'description' => 'Move a link and its ownership graph to the destination personal workspace; audit and notify both owners.', 'legacy' => null],
            'users.profile.edit' => ['group' => 'Account management', 'name' => 'Edit user profile', 'description' => 'Change the user name. Does not grant status, plan or password changes.', 'legacy' => 'users.edit'],
            'users.password.set' => ['group' => 'Account management', 'name' => 'Set a user password', 'description' => 'Independent recovery power. Requires full identity/contact visibility on legacy forms.', 'legacy' => 'users.edit'],
            'users.audit.view' => ['group' => 'Account management', 'name' => 'Read user role audit history', 'description' => 'Read role audit pages; requires full identity/contact visibility.', 'legacy' => 'users.edit'],
            'users.audit.export' => ['group' => 'Account management', 'name' => 'Export user role audit history', 'description' => 'Download role audit records; separate from reading.', 'legacy' => 'users.edit'],
            'links.view' => ['group' => 'Link management', 'name' => 'View links', 'description' => 'Read link content and owner details; requires full identity/contact visibility.', 'legacy' => 'staff.view'],
            'links.toggle' => ['group' => 'Link management', 'name' => 'Enable or disable links', 'description' => 'Change one link status.', 'legacy' => 'staff.edit'],
            'links.delete' => ['group' => 'Link management', 'name' => 'Delete links', 'description' => 'Delete one link. Separate from staff deletion.', 'legacy' => 'staff.delete'],
            'links.bulk' => ['group' => 'Link management', 'name' => 'Bulk link operations', 'description' => 'Also requires the action-specific toggle or delete permission.', 'legacy' => 'staff.edit'],
            'finance.invoices.totals' => ['group' => 'Financial summaries', 'name' => 'View invoice totals', 'description' => 'Currency-separated totals and counts; no invoice records or customer identities.', 'legacy' => 'settings.manage'],
            'finance.coins.totals' => ['group' => 'Financial summaries', 'name' => 'View coin purchase totals', 'description' => 'Currency-separated completed purchase totals; no buyer records.', 'legacy' => 'settings.manage'],
            'finance.profit.view' => ['group' => 'Financial summaries', 'name' => 'View profitability and provider costs', 'description' => 'Existing monetization report, including embedded CSV data. Does not grant other financial pages.', 'legacy' => 'settings.manage'],
            'finance.invoices.view' => ['group' => 'Financial records', 'name' => 'View invoice records', 'description' => 'Invoice number, account ID, kind, status and amount; identity fields are independently controlled.', 'legacy' => 'settings.manage'],
            'finance.coins.view' => ['group' => 'Financial records', 'name' => 'View coin purchase records', 'description' => 'Purchase rows on the finance workbench. Internal allocation report also requires margin permission and full identity visibility.', 'legacy' => 'settings.manage'],
            'finance.margin.view' => ['group' => 'Financial records', 'name' => 'View internal purchase allocation', 'description' => 'API budget and margin breakdown, separately from purchase amounts.', 'legacy' => 'settings.manage'],
            'finance.ledger.view' => ['group' => 'Financial records', 'name' => 'View global coin ledger', 'description' => 'Global transaction statement. Requires full identity/contact visibility on the legacy report.', 'legacy' => 'settings.manage'],
            'finance.ledger.export' => ['group' => 'Financial records', 'name' => 'Export global coin ledger', 'description' => 'Download coin ledger CSV. Requires ledger view and full identity/contact visibility.', 'legacy' => 'settings.manage'],
            'finance.ai.view' => ['group' => 'Financial records', 'name' => 'View AI spending details', 'description' => 'Cross-user and individual AI usage reports. Requires full identity/contact visibility on legacy reports.', 'legacy' => 'settings.manage'],
            'finance.payments.view' => ['group' => 'Financial actions', 'name' => 'View pending payment queue', 'description' => 'Read payment references and review attempts; requires full identity/contact visibility.', 'legacy' => 'settings.manage'],
            'finance.payments.confirm' => ['group' => 'Financial actions', 'name' => 'Confirm manual payments', 'description' => 'Mark an invoice paid; does not grant refunds.', 'legacy' => 'settings.manage'],
            'finance.refunds.view' => ['group' => 'Financial actions', 'name' => 'View invoice refund details', 'description' => 'Read the existing refund page; requires full identity/contact visibility.', 'legacy' => 'settings.manage'],
            'finance.refunds.create' => ['group' => 'Financial actions', 'name' => 'Initiate refunds', 'description' => 'Separate from manual refund confirmation.', 'legacy' => 'settings.manage'],
            'finance.refunds.confirm' => ['group' => 'Financial actions', 'name' => 'Confirm manual refunds', 'description' => 'Confirm an existing refund; separate from initiation.', 'legacy' => 'settings.manage'],
            'finance.subscriptions.view' => ['group' => 'Financial actions', 'name' => 'View subscription lifecycle', 'description' => 'Read subscription timeline, invoices, credit notes and refunds. Requires invoice and refund read permissions.', 'legacy' => 'settings.manage'],
            'finance.credit_reviews.view' => ['group' => 'Financial actions', 'name' => 'View credit review queue', 'description' => 'Read unused subscription time awaiting review.', 'legacy' => 'settings.manage'],
            'finance.credit_reviews.approve' => ['group' => 'Financial actions', 'name' => 'Approve subscription time credits', 'description' => 'Extend subscription time through the review queue.', 'legacy' => 'settings.manage'],
            'finance.credit_reviews.dismiss' => ['group' => 'Financial actions', 'name' => 'Dismiss subscription credit reviews', 'description' => 'Close a review without granting subscription time.', 'legacy' => 'settings.manage'],
            'finance.referrals.view' => ['group' => 'Financial actions', 'name' => 'View referral rewards', 'description' => 'Read referral conversion totals and individual reward details.', 'legacy' => 'settings.manage'],
            'finance.referrals.manage' => ['group' => 'Financial actions', 'name' => 'Enable or disable referral rewards', 'description' => 'Change program availability. Separate from viewing rewards.', 'legacy' => 'settings.manage'],
            'users.activity.export' => ['group' => 'Account management', 'name' => 'Export account activity', 'description' => 'Download the activity log. Requires full identity/contact visibility.', 'legacy' => 'users.view'],
        ];
        foreach (\App\Services\AI\AiFeatureCatalog::FEATURES as $feature) {
            foreach (['totals' => 'View totals', 'records' => 'View transaction records'] as $mode => $label) {
                $definitions['finance.ai.'.$feature.'.'.$mode] = [
                    'group' => 'AI feature finances',
                    'name' => $label.' · '.\App\Services\AI\AiFeatureCatalog::featureLabel($feature),
                    'description' => 'Only '.$feature.'. Totals and records are independent. Does not reveal identity or unlock other AI features.',
                    'legacy' => 'settings.manage',
                ];
            }
        }
        return $definitions;
    }

    public static function aiSlugs(): array
    {
        return array_values(array_filter(array_keys(self::definitions()), fn ($slug) => str_starts_with($slug, 'finance.ai.') && $slug !== 'finance.ai.view'));
    }

    public static function fullIdentity(?Admin $admin): bool
    {
        return $admin && $admin->hasPermission('users.identity.view')
            && $admin->hasPermission('users.email.view') && $admin->hasPermission('users.phone.view');
    }

    /** Do not let delegated role/staff managers grant authority they do not hold. */
    public static function assertAssignable(Admin $actor, Role $role): void
    {
        abort_unless($role->guard === 'admin', 422, 'Choose an admin role.');
        if ($actor->isSuperAdmin()) return;
        abort_if($role->slug === 'super-admin', 403, 'Only a super administrator can assign this role.');
        foreach ($role->permissions as $permission) {
            abort_unless($actor->hasPermission($permission->slug), 403, 'Cannot delegate permissions you do not hold.');
        }
    }

    public static function assertCoins(?Admin $actor, int $delta): void
    {
        abort_unless($actor?->hasPermission($delta > 0 ? 'users.coins.grant' : 'users.coins.deduct'), 403);
    }

    /** Project allowed fields only; never serialize the full User model to staff clients. */
    public static function userRow(Admin $actor, \App\Modules\User\Models\User $user, bool $identityOnly = false): array
    {
        $row = ['id' => $user->id, 'label' => 'Account #'.$user->id];
        if ($actor->hasPermission('users.identity.view')) {
            $row['name'] = $user->name;
            $row['handle'] = $user->handle;
        }
        foreach (($identityOnly ? ['email' => 'users.email.view', 'phone' => 'users.phone.view'] : ['email' => 'users.email.view', 'phone' => 'users.phone.view', 'status' => 'users.status.view', 'created_at' => 'users.joined.view']) as $field => $permission) {
            if ($actor->hasPermission($permission)) $row[$field] = $user->$field;
        }
        if (! $identityOnly && $actor->hasPermission('users.status.view') && $user->isSuspended()) $row['status'] = 'suspended';
        if (! $identityOnly && $actor->hasPermission('users.plan.view')) $row['plan'] = $user->plan?->name;
        if (! $identityOnly && $actor->hasPermission('users.badges.view')) $row['badges'] = $user->accountBadges->pluck('name')->implode(', ');
        return $row;
    }
}
