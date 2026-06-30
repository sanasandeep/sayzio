<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\BadgeRequest;
use App\Modules\User\Models\User;

/**
 * Single entry point for opening a badge request (Task #2910), shared by
 * the user dashboard form and the public contact form's "badge request"
 * topic so both land the exact same record in the admin review queue.
 *
 * Enforces the duplicate guard: a user can't have two pending requests
 * for the same badge (existing-id or same custom name).
 */
class BadgeRequestService
{
    /**
     * @return array{ok: bool, message: string, request: ?BadgeRequest}
     */
    public function submit(User $user, ?int $badgeId, ?string $customName, string $reason): array
    {
        $customName = trim((string) $customName);
        $reason     = trim($reason);

        if ($reason === '') {
            return $this->fail('Tell us why you should get this badge.');
        }

        if ($badgeId) {
            // A specific existing badge wins over any stray custom text.
            $customName = '';

            if (! AccountBadge::whereKey($badgeId)->exists()) {
                return $this->fail('That badge no longer exists.');
            }

            if ($user->accountBadges()->where('account_badges.id', $badgeId)->exists()) {
                return $this->fail('You already have this badge.');
            }

            $dupe = BadgeRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('account_badge_id', $badgeId)
                ->exists();
            if ($dupe) {
                return $this->fail('You already have a pending request for this badge.');
            }
        } else {
            if ($customName === '') {
                return $this->fail('Choose an existing badge or describe the custom badge you want.');
            }

            $dupe = BadgeRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->whereNull('account_badge_id')
                ->whereRaw('LOWER(custom_name) = ?', [mb_strtolower($customName)])
                ->exists();
            if ($dupe) {
                return $this->fail('You already have a pending request for this badge.');
            }
        }

        $request = BadgeRequest::create([
            'user_id'          => $user->id,
            'account_badge_id' => $badgeId ?: null,
            'custom_name'      => $badgeId ? null : $customName,
            'reason'           => $reason,
            'status'           => 'pending',
        ]);

        return [
            'ok'      => true,
            'message' => 'Your badge request has been submitted for review.',
            'request' => $request,
        ];
    }

    /** @return array{ok: false, message: string, request: null} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'request' => null];
    }
}
