<?php

namespace App\Services\Dm;

use App\Modules\Common\Models\ViewerDmAttachment;
use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\DmBroadcast;
use App\Modules\User\Models\DmWelcomeRule;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Centralised "send a DM" entry point. Handles:
 *
 * - Get-or-create the conversation row (biolink- or profile-scoped).
 * - Persist the message + any attachments + (optionally) tip card.
 * - Update conversation counters, last-message preview, read pointers.
 * - Fan-out notifications (in-app rows + best-effort email).
 * - Trigger welcome-message rules (new follower / new subscriber).
 * - Run mass-message broadcasts.
 *
 * The viewer-side controller still does the "is the fan allowed to
 * send?" check via DmAccessPolicy — this service trusts its caller.
 */
class DmDispatcher
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $attachments
     */
    public function send(
        ViewerDmConversation $conv,
        User $sender,
        string $senderType,
        string $body,
        array $attachments = [],
        ?int $tipId = null,
        bool $isAi = false,
        bool $isSystem = false,
    ): ViewerDmMessage {
        $body    = trim($body);
        $preview = $body !== ''
            ? Str::limit(preg_replace('/\s+/', ' ', $body), 220, '…')
            : ($attachments ? '[Attachment]' : ($tipId ? '[Tip]' : '[Message]'));

        $kind = ViewerDmMessage::KIND_TEXT;
        if ($isSystem)              $kind = ViewerDmMessage::KIND_SYSTEM;
        elseif ($tipId)             $kind = ViewerDmMessage::KIND_TIP;
        elseif (count($attachments) > 0) $kind = ViewerDmMessage::KIND_ATTACHMENT;

        return DB::transaction(function () use ($conv, $sender, $senderType, $body, $preview, $attachments, $tipId, $isAi, $kind) {
            $msg = ViewerDmMessage::create([
                'conversation_id' => $conv->id,
                'sender_type'     => $senderType,
                'sender_user_id'  => $sender->id,
                'body'            => $body,
                'kind'            => $kind,
                'tip_id'          => $tipId,
                'has_attachments' => count($attachments) > 0,
                'is_ai'           => $isAi,
            ]);

            foreach ($attachments as $a) {
                ViewerDmAttachment::create([
                    'message_id'       => $msg->id,
                    'conversation_id'  => $conv->id,
                    'owner_user_id'    => (int) $conv->creator_user_id ?: (int) $conv->owner_user_id,
                    'kind'             => $a['kind'] ?? 'file',
                    'url'              => $a['url'],
                    'thumb_url'        => $a['thumb_url'] ?? null,
                    'blur_url'         => $a['blur_url'] ?? null,
                    'mime'             => $a['mime'] ?? null,
                    'size_bytes'       => $a['size_bytes'] ?? null,
                    'duration_seconds' => $a['duration_seconds'] ?? null,
                    'lock_price_cents' => (int) ($a['lock_price_cents'] ?? 0),
                    'lock_currency'    => $a['lock_currency'] ?? 'USD',
                ]);
            }

            // Counter + preview maintenance.
            $now = Carbon::now();
            if ($senderType === 'viewer') {
                $conv->viewer_msg_count    = (int) $conv->viewer_msg_count + 1;
                $conv->owner_unread_count  = (int) $conv->owner_unread_count + 1;
            } else {
                $conv->owner_msg_count     = (int) $conv->owner_msg_count + 1;
                $conv->viewer_unread_count = (int) $conv->viewer_unread_count + 1;
                $conv->owner_replied       = true;
            }
            $conv->last_message_at      = $now;
            $conv->last_message_preview = $preview;
            $conv->last_sender          = $senderType;
            $conv->save();

            // Notify the recipient. We swallow failures so a bad mailer
            // can't stop the message landing.
            try {
                $this->notifyRecipient($conv, $sender, $senderType, $preview);
            } catch (\Throwable $e) {
                Log::warning('dm.notify.failed', ['err' => $e->getMessage()]);
            }

            return $msg;
        });
    }

    /**
     * Get-or-create a profile-scoped conversation between fan and creator.
     * Profile threads have no link_id — they live directly under the
     * creator's /@handle surface.
     */
    public function findOrCreateProfileConversation(User $creator, User $fan): ViewerDmConversation
    {
        $conv = ViewerDmConversation::query()
            ->where('creator_user_id', $creator->id)
            ->where('viewer_user_id', $fan->id)
            ->where('source', ViewerDmConversation::SOURCE_PROFILE)
            ->first();
        if ($conv) return $conv;

        try {
            $conv = ViewerDmConversation::create([
                'link_id'         => null,
                'source'          => ViewerDmConversation::SOURCE_PROFILE,
                'owner_user_id'   => $creator->id,
                'creator_user_id' => $creator->id,
                'viewer_user_id'  => $fan->id,
                'status'          => 'active',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $conv = ViewerDmConversation::query()
                ->where('creator_user_id', $creator->id)
                ->where('viewer_user_id', $fan->id)
                ->where('source', ViewerDmConversation::SOURCE_PROFILE)
                ->firstOrFail();
        }
        return $conv;
    }

    /**
     * Mark every message from the other side in this conversation as read,
     * then zero the unread counter and stamp the read pointer.
     */
    public function markRead(ViewerDmConversation $conv, string $byType): void
    {
        $otherSide = $byType === 'viewer' ? 'owner' : 'viewer';
        $col       = $byType === 'viewer' ? 'viewer_unread_count' : 'owner_unread_count';
        $stamp     = $byType === 'viewer' ? 'viewer_last_read_at' : 'owner_last_read_at';

        $conv->messages()
            ->where('sender_type', $otherSide)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $conv->{$col}   = 0;
        $conv->{$stamp} = now();
        $conv->save();
    }

    /** Trigger welcome-message rules on a new follow. */
    public function triggerNewFollower(User $creator, User $fan): void
    {
        $rules = DmWelcomeRule::query()
            ->where('user_id', $creator->id)
            ->where('trigger', DmWelcomeRule::TRIGGER_FOLLOWER)
            ->where('is_active', true)
            ->get();
        foreach ($rules as $rule) {
            $this->fireWelcomeRule($rule, $creator, $fan);
        }
    }

    /** Trigger welcome-message rules on a new active subscription. */
    public function triggerNewSubscriber(User $creator, User $fan, ?SubscriptionTier $tier): void
    {
        $rules = DmWelcomeRule::query()
            ->where('user_id', $creator->id)
            ->where('trigger', DmWelcomeRule::TRIGGER_SUBSCRIBER)
            ->where('is_active', true)
            ->get();
        foreach ($rules as $rule) {
            if ($rule->tier_id && (int) $rule->tier_id !== (int) ($tier?->id ?? 0)) {
                continue;
            }
            $this->fireWelcomeRule($rule, $creator, $fan);
        }
    }

    protected function fireWelcomeRule(DmWelcomeRule $rule, User $creator, User $fan): void
    {
        try {
            $conv = $this->findOrCreateProfileConversation($creator, $fan);
            $attachments = [];
            if ($rule->attachment_url) {
                $attachments[] = [
                    'kind'             => $rule->attachment_kind ?: 'file',
                    'url'              => $rule->attachment_url,
                    'thumb_url'        => $rule->attachment_thumb_url,
                    'blur_url'         => $rule->attachment_thumb_url,
                    'lock_price_cents' => (int) $rule->attachment_lock_price_cents,
                    'lock_currency'    => $rule->attachment_lock_currency,
                ];
            }
            $this->send($conv, $creator, 'owner', (string) $rule->body, $attachments, null, false, false);
            $rule->increment('sent_count');
        } catch (\Throwable $e) {
            Log::warning('dm.welcome.failed', ['rule_id' => $rule->id, 'err' => $e->getMessage()]);
        }
    }

    /**
     * Resolve the audience for a broadcast and send. Returns the broadcast.
     */
    public function dispatchBroadcast(DmBroadcast $broadcast): DmBroadcast
    {
        $broadcast->status = DmBroadcast::STATUS_SENDING;
        $broadcast->save();

        $creator = User::find($broadcast->user_id);
        if (!$creator) {
            $broadcast->status = DmBroadcast::STATUS_FAILED;
            $broadcast->error  = 'Creator not found.';
            $broadcast->save();
            return $broadcast;
        }

        $fanIds = $this->resolveAudience($creator, $broadcast->audience_kind, $broadcast->audience_value);
        $broadcast->recipients_count = $fanIds->count();
        $broadcast->save();

        $sent = 0; $failed = 0;
        foreach ($fanIds as $fanId) {
            $fan = User::find($fanId);
            if (!$fan) { $failed++; continue; }
            try {
                $conv = $this->findOrCreateProfileConversation($creator, $fan);
                $atts = [];
                if ($broadcast->attachment_url) {
                    $atts[] = [
                        'kind'             => $broadcast->attachment_kind ?: 'file',
                        'url'              => $broadcast->attachment_url,
                        'thumb_url'        => $broadcast->attachment_thumb_url,
                        'blur_url'         => $broadcast->attachment_thumb_url,
                        'lock_price_cents' => (int) $broadcast->attachment_lock_price_cents,
                        'lock_currency'    => $broadcast->attachment_lock_currency,
                    ];
                }
                $this->send($conv, $creator, 'owner', (string) $broadcast->body, $atts);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('dm.broadcast.row_failed', ['broadcast_id' => $broadcast->id, 'fan_id' => $fanId, 'err' => $e->getMessage()]);
            }
        }

        $broadcast->sent_count   = $sent;
        $broadcast->failed_count = $failed;
        $broadcast->status       = DmBroadcast::STATUS_SENT;
        $broadcast->sent_at      = now();
        $broadcast->save();

        return $broadcast;
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    protected function resolveAudience(User $creator, string $kind, ?string $value)
    {
        switch ($kind) {
            case 'followers':
                return Follow::query()->where('creator_id', $creator->id)->pluck('follower_id');
            case 'subscribers':
                return DB::table('creator_subscriptions')
                    ->where('creator_user_id', $creator->id)
                    ->whereIn('status', ['active', 'trialing', 'past_due'])
                    ->pluck('fan_user_id');
            case 'tier':
                return DB::table('creator_subscriptions')
                    ->where('creator_user_id', $creator->id)
                    ->where('tier_id', (int) $value)
                    ->whereIn('status', ['active', 'trialing', 'past_due'])
                    ->pluck('fan_user_id');
            case 'all_dm_threads':
                return ViewerDmConversation::query()
                    ->where('creator_user_id', $creator->id)
                    ->orWhere('owner_user_id', $creator->id)
                    ->pluck('viewer_user_id')
                    ->unique()
                    ->values();
            default:
                return collect();
        }
    }

    protected function notifyRecipient(ViewerDmConversation $conv, User $sender, string $senderType, string $preview): void
    {
        $recipientId = $senderType === 'viewer'
            ? (int) ($conv->creator_user_id ?: $conv->owner_user_id)
            : (int) $conv->viewer_user_id;
        $recipient = User::find($recipientId);
        if (!$recipient) return;

        $payload = [
            'conversation_id' => $conv->id,
            'sender_id'       => $sender->id,
            'sender_name'     => $sender->name,
            'sender_avatar'   => $sender->avatar,
            'preview'         => $preview,
            'message'         => "{$sender->name}: {$preview}",
            'link'            => $senderType === 'viewer'
                ? route('user.inbox.dms.thread', $conv->id)
                : (function () use ($conv, $sender) {
                    return $conv->isProfileScoped()
                        ? route('creator-profile.show', ['handle' => $sender->handle ?: $sender->id]) . '#dm'
                        : '/';
                })(),
        ];

        // In-app row.
        $this->notifications->notify($recipient, 'dm.new', $payload);

        // Best-effort email if the recipient hasn't muted DMs.
        if ($recipient->email && $this->notifications->prefersChannel($recipient->id, 'dm.new', 'email')) {
            try {
                Mail::raw("{$sender->name}: {$preview}", function ($m) use ($recipient, $sender) {
                    $m->to($recipient->email)->subject('New 1INME DM from ' . $sender->name);
                });
            } catch (\Throwable $e) {
                Log::warning('dm.email.failed', ['err' => $e->getMessage()]);
            }
        }
    }

    /**
     * Notify the creator that a fan unlocked one of their locked DM
     * attachments. Mirrors notifyCreatorOfUnlock in MonetizationCheckout.
     */
    public function notifyAttachmentUnlocked(User $creator, User $fan, ViewerDmAttachment $att, int $price, string $currency, ViewerDmConversation $conv): void
    {
        $this->notifications->notify($creator, 'dm.unlocked', [
            'fan_id'         => $fan->id,
            'fan_name'       => $fan->name,
            'attachment_id'  => $att->id,
            'conversation_id'=> $conv->id,
            'amount'         => '$' . number_format($price / 100, 2),
            'message'        => $fan->name . ' unlocked your DM media for $' . number_format($price / 100, 2) . '.',
            'link'           => route('user.inbox.dms.thread', $conv->id),
        ]);
    }
}
