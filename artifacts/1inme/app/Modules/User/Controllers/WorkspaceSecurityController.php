<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\WorkspaceTwoFactorPolicyMailable;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\TwoFactorPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Owner-only workspace security settings: today this is just the 2FA
 * enforcement policy, but the page is intentionally generic so future
 * security toggles (IP allow-listing, session timeout, etc.) land here.
 *
 * Compliance is rendered next to the team list, so this controller only
 * handles the policy mutation + the "remind everyone" email blast.
 */
class WorkspaceSecurityController extends Controller
{
    public function __construct(protected TwoFactorPolicy $policy) {}

    protected function ownerWorkspace(Request $request): Workspace
    {
        $ws = app('current_workspace');
        $user = $request->user();
        $isPlatformAny = $user && method_exists($user, 'hasPermission') && $user->hasPermission('user.workspaces.access_any');
        abort_unless($isPlatformAny || (int) $ws->owner_user_id === (int) $user->id,
            403, 'Only the workspace owner can change security settings.');
        return $ws;
    }

    public function update(Request $request)
    {
        $ws = $this->ownerWorkspace($request);

        $data = $request->validate([
            'require_2fa'   => 'sometimes|boolean',
            // Grace window in days. 0 means "enforce immediately."
            'grace_days'    => 'nullable|integer|min:0|max:90',
        ]);

        $require = (bool) ($data['require_2fa'] ?? false);
        $settings = $ws->settings ?? [];
        $wasOn = (bool) ($settings['require_2fa'] ?? false);

        $settings['require_2fa'] = $require;

        if ($require) {
            $graceDays = (int) ($data['grace_days'] ?? 7);
            if (!$wasOn || empty($settings['2fa_grace_until'])) {
                $settings['2fa_grace_until'] = now()->addDays($graceDays)->toIso8601String();
            } elseif ($request->boolean('reset_grace')) {
                $settings['2fa_grace_until'] = now()->addDays($graceDays)->toIso8601String();
            }
        } else {
            // Clear the deadline on a turn-off so a future re-enable starts fresh.
            unset($settings['2fa_grace_until'], $settings['2fa_enrollment_emails_sent_at']);
        }

        $ws->settings = $settings;
        $ws->save();

        // Blast the heads-up email the first time the policy is turned on.
        if ($require && !$wasOn) {
            $this->notifyMembers($ws);
        }

        $msg = $require
            ? 'Two-factor authentication is now required in this workspace.'
            : 'Two-factor authentication is no longer required.';
        return back()->with('success', $msg);
    }

    /** Manually re-send the heads-up email to anyone still un-enrolled. */
    public function remindMembers(Request $request)
    {
        $ws = $this->ownerWorkspace($request);
        if (!$this->policy->workspaceRequires2FA($ws)) {
            return back()->with('error', 'Turn on the 2FA requirement before sending reminders.');
        }
        $count = $this->notifyMembers($ws, onlyUnenrolled: true);
        return back()->with('success', "Reminder sent to {$count} member" . ($count === 1 ? '' : 's') . '.');
    }

    /**
     * Send the heads-up / reminder email to every member of the workspace.
     * Returns the count of mails attempted. Owner is intentionally
     * excluded — they triggered the change.
     */
    protected function notifyMembers(Workspace $ws, bool $onlyUnenrolled = false): int
    {
        $deadline = $this->policy->workspaceGraceDeadline($ws);
        $deadlineStr = $deadline ? $deadline->toDayDateTimeString() : null;
        $setupUrl = AppModulesCommonSupportPlatformHosts::outboundUrl(route('user.account.two-factor.show'));

        $memberUserIds = $ws->members()->pluck('user_id');
        $users = User::whereIn('id', $memberUserIds)->get();

        $sent = 0;
        foreach ($users as $member) {
            $enrolled = $this->policy->userHasEnrolledTotp($member);
            if ($onlyUnenrolled && $enrolled) continue;
            if (empty($member->email)) continue;

            try {
                \App\Modules\Common\Services\Emailer::sendMailable('workspace.two_factor_policy', $member->email, new WorkspaceTwoFactorPolicyMailable(
                    workspace: $ws,
                    memberName: $member->name ?: $member->email,
                    graceDeadline: $deadlineStr,
                    setupUrl: $setupUrl,
                    alreadyEnrolled: $enrolled,
                ), ['workspace_name' => $ws->name], ['user' => $member->id, 'related' => $ws]);
                $sent++;
            } catch (\Throwable $e) {
                \Log::warning('Workspace 2FA policy email failed: ' . $e->getMessage(), [
                    'workspace_id' => $ws->id,
                    'user_id'      => $member->id,
                ]);
            }
        }

        $settings = $ws->settings ?? [];
        $settings['2fa_enrollment_emails_sent_at'] = now()->toIso8601String();
        $ws->settings = $settings;
        $ws->save();

        return $sent;
    }
}
