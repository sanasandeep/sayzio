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
