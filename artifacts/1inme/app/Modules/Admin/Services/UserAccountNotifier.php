<?php

namespace App\Modules\Admin\Services;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the affected user an in-app notification + email when an admin
 * changes their account: plan assigned/changed and suspend/reactivate.
 *
 * Coin grants/deductions are NOT handled here — those flow through
 * {@see \App\Services\Billing\WalletService::adjust()}, which already
 * fires its own `wallet.adjusted` in-app + email notification. Sending
 * a second message from here would double-notify.
 *
 * Mirrors WalletService's notify() pattern: in-app row always written,
 * email best-effort (failures are logged, never thrown) so a broken
 * SMTP config can't block the admin action.
 */
class UserAccountNotifier
{
    public function planAssigned(User $user, string $planName, ?int $compDays = null): void
    {
        $body = $compDays
            ? "Your account has been upgraded to the {$planName} plan, complimentary for {$compDays} day"
                . ($compDays === 1 ? '' : 's')
                . ". After that it will return to the default plan."
            : "Your account plan has been changed to {$planName} by an administrator.";

        $this->dispatch($user, 'account.plan_changed', [
            'plan'      => $planName,
            'comp_days' => $compDays,
        ], 'Your plan was updated', $body);
    }

    public function suspended(User $user, string $reason, ?\DateTimeInterface $reactivateAt = null): void
    {
        $when = $reactivateAt
            ? ' It is scheduled to be reactivated on ' . $reactivateAt->format('M j, Y') . '.'
            : '';

        $this->dispatch($user, 'account.suspended', [
            'reason'        => $reason,
            'reactivate_at' => $reactivateAt?->format('Y-m-d'),
        ], 'Your account has been suspended',
            "Your account has been temporarily suspended. Reason: {$reason}.{$when}");
    }

    public function reactivated(User $user): void
    {
        $this->dispatch($user, 'account.reactivated', [],
            'Your account has been reactivated',
            'Your account has been reactivated and you can sign in again.');
    }

    public function badgeApproved(User $user, string $badgeName): void
    {
        $this->dispatch($user, 'account.badge_approved', [
            'badge'   => $badgeName,
            'message' => "Your badge request was approved — the \"{$badgeName}\" badge is now on your account.",
            'url'     => route('user.badge-requests.index'),
        ], 'Your badge request was approved',
            "Good news! Your request for the \"{$badgeName}\" badge has been approved and it now appears on your account.");
    }

    /**
     * Notify the recipient that another creator passed them a badge they hold
     * (Task #3045). Mirrors badgeApproved so creator-granted badges reach the
     * user through the same in-app + email path as admin approvals.
     */
    public function badgeGranted(User $user, string $badgeName, string $granterName): void
    {
        $this->dispatch($user, 'account.badge_granted', [
            'badge'   => $badgeName,
            'granter' => $granterName,
            'message' => "{$granterName} gave you the \"{$badgeName}\" badge.",
            'url'     => route('user.badge-requests.index'),
        ], 'You received a new badge',
            "{$granterName} gave you the \"{$badgeName}\" badge — it now appears on your account.");
    }

    public function badgeRejected(User $user, string $badgeName, string $reason): void
    {
        $this->dispatch($user, 'account.badge_rejected', [
            'badge'   => $badgeName,
            'reason'  => $reason,
            'message' => "Your request for the \"{$badgeName}\" badge was declined.",
            'url'     => route('user.badge-requests.index'),
        ], 'Update on your badge request',
            "Your request for the \"{$badgeName}\" badge was not approved. Reason: {$reason}");
    }

    /**
     * Profile verification (or re-verification) approved — tells the user the
     * tick they now hold and the name it was verified under (Task: auto-notify
     * on verification review). Email goes out under its own registry key so
     * admins can customise the template independently of generic notices.
     */
    public function verificationApproved(User $user, string $tickName, string $verifiedName, bool $isReverification = false): void
    {
        $message = $isReverification
            ? "Your re-verification was approved — your \"{$tickName}\" tick now shows as \"{$verifiedName}\"."
            : "Congratulations! Your profile is now verified with the \"{$tickName}\" tick as \"{$verifiedName}\".";

        $this->dispatchKeyed($user, 'account.verification_approved', 'verification.approved', [
            'tick'            => $tickName,
            'verified_name'   => $verifiedName,
            'reverification'  => $isReverification,
            'message'         => $message,
            'url'             => route('user.profile-verification.index'),
        ], $message);
    }

    /**
     * Profile verification (or re-verification) rejected — always carries the
     * admin's rejection note so the user knows what to fix.
     */
    public function verificationRejected(User $user, string $reason, bool $isReverification = false): void
    {
        $message = $isReverification
            ? "Your re-verification request was not approved, so your previously verified details remain in place. Reviewer note: {$reason}"
            : "Your verification request was not approved. Reviewer note: {$reason}";

        $this->dispatchKeyed($user, 'account.verification_rejected', 'verification.rejected', [
            'reason'          => $reason,
            'reverification'  => $isReverification,
            'message'         => $message,
            'url'             => route('user.profile-verification.index'),
        ], $message);
    }

    /**
     * Like dispatch(), but sends the email under a dedicated Emailer registry
     * key (with {{message}}/{{url}} tokens) instead of the generic
     * account.notice template.
     */
    protected function dispatchKeyed(User $user, string $type, string $emailKey, array $data, string $message): void
    {
        try {
            UserNotification::create([
                'user_id'    => $user->id,
                'type'       => $type,
                'data'       => $data,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('UserAccountNotifier in-app failed: ' . $e->getMessage(), ['user_id' => $user->id]);
        }

        if ($user->email) {
            try {
                \App\Modules\Common\Services\Emailer::send($emailKey, $user->email, [
                    'message' => $message,
                    'url'     => AppModulesCommonSupportPlatformHosts::outboundUrl($data['url'] ?? route('user.profile-verification.index')),
                ], [
                    'user' => $user->id,
                ]);
            } catch (\Throwable $e) {
                Log::info('UserAccountNotifier email skipped: ' . $e->getMessage());
            }
        }
    }

    protected function dispatch(User $user, string $type, array $data, string $subject, string $body): void
    {
        try {
            UserNotification::create([
                'user_id'    => $user->id,
                'type'       => $type,
                'data'       => $data,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('UserAccountNotifier in-app failed: ' . $e->getMessage(), ['user_id' => $user->id]);
        }

        if ($user->email) {
            try {
                \App\Modules\Common\Services\Emailer::send('account.notice', $user->email, [], [
                    'user'    => $user->id,
                    'subject' => $subject,
                    'body'    => $body,
                ]);
            } catch (\Throwable $e) {
                Log::info('UserAccountNotifier email skipped: ' . $e->getMessage());
            }
        }
    }
}
