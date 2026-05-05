<?php

namespace App\Modules\User\Services;

use App\Mail\PlatformRoleAttachedAlertMail;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Decides whether a user-role grant rises to "platform-admin" level
 * and, if so, fans an alert email out to ops. Mirrors the
 * `SensitiveActionLogger` workspace-tier pattern but operates on
 * cross-workspace role attachments.
 *
 * Sensitivity is the union of two checks:
 *   - the role's slug is in `platform_role_alerts.sensitive_role_slugs`, OR
 *   - any of the role's permissions has a slug in
 *     `platform_role_alerts.sensitive_permission_slugs`.
 *
 * The permission-side check guarantees a custom role that quietly
 * carries `user.workspaces.access_any` (or any other admin power) is
 * still caught even if it hasn't been enumerated in the slug list.
 */
class PlatformRoleAlertService
{
    /** True iff the role qualifies as a platform-admin grant. */
    public function isSensitive(?Role $role): bool
    {
        return !empty($this->matchedReasons($role));
    }

    /**
     * Returns the slug-based reasons the role was flagged. Empty array
     * means the role is not sensitive. Reasons are deduplicated and
     * prefixed with their source bucket (`role:` vs `permission:`) so
     * the alert email can show reviewers exactly why it fired.
     *
     * @return array<int,string>
     */
    public function matchedReasons(?Role $role): array
    {
        if (!$role) {
            return [];
        }

        $reasons = [];

        $roleSlugs = (array) config('platform_role_alerts.sensitive_role_slugs', []);
        if (in_array((string) $role->slug, $roleSlugs, true)) {
            $reasons[] = 'role:' . $role->slug;
        }

        $permSlugs = (array) config('platform_role_alerts.sensitive_permission_slugs', []);
        if (!empty($permSlugs)) {
            try {
                $role->loadMissing('permissions');
                foreach ($role->permissions as $perm) {
                    if (in_array((string) $perm->slug, $permSlugs, true)) {
                        $reasons[] = 'permission:' . $perm->slug;
                    }
                }
            } catch (\Throwable $e) {
                // If permissions can't be loaded (e.g. schema not
                // migrated in a stripped-down test) fall back to the
                // role-slug check we already performed.
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Resolve the recipient address list. An explicit configured list
     * wins outright; otherwise we fan out to every user holding
     * `user.ops_alerts.receive` (same fallback as the site-assistant
     * cut-off and image-reoptimisation alerts).
     *
     * @return array<int,string>
     */
    public function recipientEmails(): array
    {
        $explicit = (array) config('platform_role_alerts.recipient_emails', []);
        $explicit = array_values(array_filter(array_map(
            fn ($v) => strtolower(trim((string) $v)),
            $explicit
        ), fn ($v) => $v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)));

        if (!empty($explicit)) {
            return array_values(array_unique($explicit));
        }

        try {
            return User::query()
                ->withPermission('user.ops_alerts.receive')
                ->whereNotNull('email')
                ->pluck('email')
                ->map(fn ($e) => strtolower(trim((string) $e)))
                ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('PlatformRoleAlertService: recipient lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Dispatch one alert email per recipient for the given attached
     * audit row. Failures are swallowed and logged so a mail outage
     * cannot break the originating role-edit request.
     *
     * Thin wrapper around `dispatchForBatch` so callers handling a
     * single attach don't have to wrap it in an array themselves.
     */
    public function dispatchFor(UserRoleAudit $audit, ?Role $role): void
    {
        $this->dispatchForBatch([[$audit, $role]]);
    }

    /**
     * Dispatch a SINGLE alert email per recipient covering every
     * sensitive attach in the supplied batch. This collapses the
     * "operator granted two admin roles in the same save" case from
     * N near-identical emails into one summary email per recipient.
     *
     * Each entry must be `[UserRoleAudit $audit, ?Role $role]`. Rows
     * whose action isn't `attached` or whose role doesn't match the
     * sensitivity rules are silently dropped from the batch — they
     * weren't going to email anyway.
     *
     * If the filtered batch ends up empty, no mail is sent. Failures
     * are swallowed and logged so a mail outage cannot break the
     * originating role-edit request.
     *
     * @param array<int, array{0: UserRoleAudit, 1: ?Role}> $items
     */
    public function dispatchForBatch(array $items): void
    {
        $grants = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item[0]) || !$item[0] instanceof UserRoleAudit) {
                continue;
            }
            $audit = $item[0];
            $role  = $item[1] ?? null;

            if ($audit->action !== UserRoleAudit::ACTION_ATTACHED) {
                continue;
            }
            $reasons = $this->matchedReasons($role);
            if (empty($reasons)) {
                continue;
            }
            $grants[] = ['audit' => $audit, 'reasons' => $reasons];
        }

        if (empty($grants)) {
            return;
        }

        $emails = $this->recipientEmails();
        if (empty($emails)) {
            Log::info('PlatformRoleAlertService: no recipients configured for sensitive role grant', [
                'audit_ids'  => array_map(fn ($g) => $g['audit']->id, $grants),
                'role_slugs' => array_map(fn ($g) => $g['audit']->role_slug, $grants),
            ]);
            return;
        }

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new PlatformRoleAttachedAlertMail($grants));
            } catch (\Throwable $e) {
                Log::warning('PlatformRoleAlertService: alert mail failed', [
                    'audit_ids' => array_map(fn ($g) => $g['audit']->id, $grants),
                    'email'     => $email,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }
}
