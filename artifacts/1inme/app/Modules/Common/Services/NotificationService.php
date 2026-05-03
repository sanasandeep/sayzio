<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Models\NotificationBroadcast;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for issuing in-app notifications and admin-driven
 * broadcasts. Centralises three things that used to be scattered
 * across feature controllers:
 *
 *   1. Inserting the user_notifications row (the actual feed item),
 *   2. Consulting the recipient's per-type preference matrix
 *      (notification_preferences) so users can mute categories they
 *      don't care about, and
 *   3. Emitting the audit row in notification_broadcasts when an
 *      operator composes a one-off message from /admin/notifications.
 *
 * The legacy trigger sites (followers, biolink updates, task reminders,
 * workspace access requests, etc.) continue to write directly to
 * user_notifications today. They can opt into preference-aware delivery
 * by switching their UserNotification::create() call to
 * NotificationService::notify() — the call signature is intentionally
 * close to a drop-in.
 */
class NotificationService
{
    /**
     * Catalogue of notification types users can toggle from their
     * preferences screen. Anything emitted with a type not in this list
     * still delivers (default-on), but won't appear in the toggle UI.
     *
     * @return array<string, array{label: string, description: string, default_in_app: bool, default_email: bool, default_push: bool, system?: bool}>
     */
    public static function catalog(): array
    {
        return [
            'system_broadcast' => [
                'label'          => 'Announcements from 1INME',
                'description'    => 'Important updates, downtime notices and product news from the 1INME team.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => true,
                'system'         => true,
            ],
            'new_follower' => [
                'label'          => 'New followers',
                'description'    => 'When someone starts following your profile.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => true,
            ],
            'follower_update' => [
                'label'          => 'Updates from creators you follow',
                'description'    => 'New posts, links or biolink changes from creators in your following list.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => false,
            ],
            'workspace_access_request' => [
                'label'          => 'Workspace access requests',
                'description'    => 'When a teammate asks for access to a workspace you own.',
                'default_in_app' => true,
                'default_email'  => true,
                'default_push'   => true,
            ],
            'task_assigned' => [
                'label'          => 'Task assignments',
                'description'    => 'When a teammate assigns a task to you.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => true,
            ],
            'task_mention' => [
                'label'          => 'Mentions',
                'description'    => 'When you are @-mentioned in a task or comment.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => true,
            ],
            'task_due' => [
                'label'          => 'Task due reminders',
                'description'    => 'Heads-up when a task you own is approaching its due date.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => false,
            ],
            'task_overdue' => [
                'label'          => 'Overdue tasks',
                'description'    => 'When one of your tasks slips past its due date.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => true,
            ],
            'social_connection_broken' => [
                'label'          => 'Connected-account issues',
                'description'    => 'When one of your connected social accounts loses authorization and needs reconnecting.',
                'default_in_app' => true,
                'default_email'  => true,
                'default_push'   => true,
            ],
            'link_failover' => [
                'label'          => 'Link Insurance failover',
                'description'    => 'When a short link\'s primary destination breaks and we promote one of your backup URLs to keep traffic flowing.',
                'default_in_app' => true,
                'default_email'  => true,
                'default_push'   => true,
            ],
            'link_restored' => [
                'label'          => 'Link Insurance restored',
                'description'    => 'When a previously broken primary destination starts working again and Link Insurance restores it.',
                'default_in_app' => true,
                'default_email'  => true,
                'default_push'   => false,
            ],
            'roadmap_idea_shipped' => [
                'label'          => 'Roadmap ideas you upvoted shipped',
                'description'    => 'When a creator marks a public roadmap idea you upvoted as "Shipped".',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => true,
            ],
            'roadmap_new_submission' => [
                'label'          => 'New roadmap submissions',
                'description'    => 'When a fan submits a new idea on one of your public roadmap blocks.',
                'default_in_app' => true,
                'default_email'  => false,
                'default_push'   => false,
            ],
        ];
    }

    /** Channels that ship today. push is reserved for the upcoming
     *  expo-notifications wiring; rows store the toggle so the UI is
     *  forward-compatible.
     *
     * @return array<int, string>
     */
    public static function channels(): array
    {
        return ['in_app', 'email', 'push'];
    }

    /**
     * Resolve the effective preference for a given user+type+channel.
     * Falls back to the catalog default when no row exists. Unknown
     * types are treated as on so back-compat code keeps working.
     */
    public function prefersChannel(int $userId, string $type, string $channel): bool
    {
        $row = NotificationPreference::where('user_id', $userId)->where('type', $type)->first();
        if ($row) {
            return (bool) ($row->{$channel} ?? true);
        }
        $cat = self::catalog()[$type] ?? null;
        if (!$cat) return true;
        $key = 'default_' . $channel;
        return (bool) ($cat[$key] ?? true);
    }

    /**
     * Insert a user_notifications row for $user, honoring their in_app
     * preference. Returns the created model, or null if the user has
     * muted this type's in-app channel.
     *
     * @param array<string, mixed> $data Free-form payload merged into user_notifications.data.
     */
    public function notify(User $user, string $type, array $data = []): ?UserNotification
    {
        if (!$this->prefersChannel($user->id, $type, 'in_app')) {
            return null;
        }

        return UserNotification::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'data'       => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Compose and deliver an admin broadcast. Resolves the target into
     * a user-id list, fans the in-app rows out in chunks, and records
     * the audit entry. Returns the persisted NotificationBroadcast.
     *
     * @param string $targetKind one of: all|plan|role|country|user
     */
    public function broadcast(
        ?int $adminId,
        string $targetKind,
        ?string $targetValue,
        string $subject,
        string $body,
        ?string $targetUrl = null,
        string $type = 'system_broadcast'
    ): NotificationBroadcast {
        $broadcast = NotificationBroadcast::create([
            'admin_id'         => $adminId,
            'target_kind'      => $targetKind,
            'target_value'     => $targetValue,
            'type'             => $type,
            'subject'          => $subject,
            'body'             => $body,
            'target_url'       => $targetUrl,
            'recipients_count' => 0,
        ]);

        $userIds = $this->resolveTargetUserIds($targetKind, $targetValue);

        if ($userIds->isEmpty()) {
            return $broadcast;
        }

        $now     = now();
        $payload = [
            'subject'      => $subject,
            'body'         => $body,
            'message'      => $body, // legacy field rendered by user_notifications view
            'target_url'   => $targetUrl,
            'broadcast_id' => $broadcast->id,
            'sender'       => '1INME Team',
        ];

        $delivered = 0;

        // We respect each recipient's in_app preference at fan-out time
        // — admin broadcasts are still notifications and should be
        // muteable by users who opted out of system_broadcast.
        $optedOutIds = NotificationPreference::whereIn('user_id', $userIds)
            ->where('type', $type)
            ->where('in_app', false)
            ->pluck('user_id');

        $deliverable = $userIds->diff($optedOutIds);

        foreach ($deliverable->chunk(500) as $chunk) {
            $rows = $chunk->map(fn ($id) => [
                'user_id'    => $id,
                'type'       => $type,
                'data'       => json_encode($payload),
                'created_at' => $now,
            ])->all();

            try {
                DB::table('user_notifications')->insert($rows);
                $delivered += count($rows);
            } catch (\Throwable $e) {
                Log::warning('Broadcast chunk insert failed', [
                    'broadcast_id' => $broadcast->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        $broadcast->forceFill(['recipients_count' => $delivered])->save();

        return $broadcast;
    }

    /** @return Collection<int, int> */
    protected function resolveTargetUserIds(string $kind, ?string $value): Collection
    {
        $q = User::query()->where('status', 'active')->select('id');

        switch ($kind) {
            case 'plan':
                if (!$value) return collect();
                $planId = DB::table('plans')->where('slug', $value)->value('id');
                if (!$planId) return collect();
                $q->where('plan_id', $planId);
                break;
            case 'role':
                if (!$value) return collect();
                $q->where('role', $value);
                break;
            case 'country':
                if (!$value) return collect();
                $q->where('country', strtoupper($value));
                break;
            case 'user':
                if (!$value) return collect();
                if (str_contains($value, '@')) {
                    $q->where('email', strtolower(trim($value)));
                } else {
                    $q->where('id', (int) $value);
                }
                break;
            case 'all':
            default:
                // unfiltered
                break;
        }

        return $q->pluck('id');
    }
}
