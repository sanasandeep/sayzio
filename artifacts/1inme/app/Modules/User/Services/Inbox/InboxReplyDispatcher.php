<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\InboxMessage;
use App\Modules\User\Models\InboxReply;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Single send pipeline for unified-inbox replies, shared by the human
 * composer (InboxUnifiedController::reply) and the autopilot agent. It
 * dispatches back through the original channel (biolink DM endpoint or
 * email mailer) and records the outbound message, thread state and feed
 * event so manual and AI replies are indistinguishable to the rest of the
 * app — except for the `sent_by_ai` flag we stamp for labeling.
 */
class InboxReplyDispatcher
{
    /**
     * Send back through the original channel only. Does NOT record anything.
     * Returns ['via' => string, 'error' => null|string].
     */
    public function dispatch(InboxThread $thread, string $body, User $fromUser, bool $byAi = false): array
    {
        if ($thread->source_type === 'viewer_dm') {
            $c = ViewerDmConversation::find($thread->source_id);
            if (!$c) return ['via' => 'biolink_dm', 'error' => 'Conversation not found.'];
            if ($c->isBlocked()) return ['via' => 'biolink_dm', 'error' => 'Conversation is blocked.'];
            DB::transaction(function () use ($c, $body, $fromUser) {
                ViewerDmMessage::create([
                    'conversation_id' => $c->id,
                    'sender_type'     => 'owner',
                    'sender_user_id'  => $fromUser->id,
                    'body'            => $body,
                ]);
                $c->owner_msg_count++;
                $c->owner_replied = true;
                $c->viewer_unread_count++;
                $c->last_message_at = now();
                $c->last_message_preview = Str::limit(preg_replace('/\s+/', ' ', $body), 220, '…');
                $c->last_sender = 'owner';
                $c->save();
            });
            return ['via' => 'biolink DM', 'error' => null];
        }

        // Form / subscriber / sponsorship → email reply (when we have one).
        $to = $thread->sender_email;
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['via' => 'email', 'error' => 'No usable email address on this thread.'];
        }
        $subSettings = ($fromUser->settings ?? [])['subscription'] ?? [];
        $fromName    = $subSettings['email_from_name']    ?? config('app.name');
        $fromAddress = $subSettings['email_from_address'] ?? config('mail.from.address', 'noreply@1inme.com');
        $replyTo     = $subSettings['email_reply_to']     ?? null;

        try {
            Mail::html(nl2br(e($body)), function ($m) use ($to, $thread, $fromName, $fromAddress, $replyTo) {
                $m->to($to)->subject('Re: ' . ($thread->subject ?: 'Your message'))->from($fromAddress, $fromName);
                if ($replyTo) $m->replyTo($replyTo);
            });
        } catch (\Throwable $e) {
            return ['via' => 'email', 'error' => $e->getMessage()];
        }

        $reply = InboxReply::create([
            'user_id'    => $thread->user_id,
            'item_type'  => $thread->source_type === 'form_submission' ? 'form_submission' : 'subscriber',
            'item_id'    => $thread->source_id,
            'to_email'   => $to,
            'from_email' => $fromAddress,
            'from_name'  => $fromName,
            'subject'    => 'Re: ' . ($thread->subject ?: 'Your message'),
            'body'       => $body,
            'status'     => 'sent',
            'sent_at'    => now(),
        ]);

        WorkspaceActivityRecorder::record(
            null, 'inbox.reply', 'inbox_thread', $reply->id,
            'Reply to ' . $to . ' — ' . ($thread->subject ?: 'Your message'),
            route('user.inbox.unified.index'),
            ['thread_id' => $thread->id, 'to' => $to, 'by_ai' => $byAi],
            $fromUser,
        );

        return ['via' => 'email', 'error' => null];
    }

    /**
     * High-level send: dispatch + record the outbound message, advance the
     * thread, and emit the activity feed event. Returns the dispatch result
     * (['via','error']); on error nothing is recorded.
     *
     * @param array{sent_by_ai?:bool,sender_name?:string,sender_user_id?:int|null} $opts
     */
    public function sendReply(InboxThread $thread, string $body, User $sender, array $opts = []): array
    {
        $byAi = (bool) ($opts['sent_by_ai'] ?? false);

        $res = $this->dispatch($thread, $body, $sender, $byAi);
        if ($res['error']) {
            return $res;
        }

        InboxMessage::create([
            'thread_id'      => $thread->id,
            'direction'      => 'out',
            'sender_name'    => $opts['sender_name'] ?? ($byAi ? 'Inbox Agent' : ($sender->name ?? 'You')),
            'sender_user_id' => $opts['sender_user_id'] ?? $sender->id,
            'body'           => $body,
            'sent_at'        => now(),
            'meta'           => array_filter([
                'via'        => $res['via'],
                'sent_by_ai' => $byAi ?: null,
            ]),
        ]);

        $threadUpdate = [
            'last_message_at'      => now(),
            'last_sender'          => 'out',
            'sla_due_at'           => null, // SLA satisfied by reply
            'sla_overdue_notified' => false,
        ];
        if ($byAi) {
            $threadUpdate['sent_by_ai']      = true;
            $threadUpdate['ai_handled_at']   = now();
            $threadUpdate['autopilot_state'] = InboxThread::AUTOPILOT_SENT;
            // The agent has acted; clear any staged review draft.
            $threadUpdate['ai_draft']        = null;
        } elseif ($thread->autopilot_state === InboxThread::AUTOPILOT_REVIEW) {
            // A human reviewed and sent a reply for an autopilot-staged thread:
            // resolve it out of the review queue and drop the stale AI draft so
            // it no longer shows "Awaiting AI review" or inflates the count.
            $threadUpdate['autopilot_state'] = InboxThread::AUTOPILOT_SKIPPED;
            $threadUpdate['ai_draft']        = null;
        }
        $thread->update($threadUpdate);

        // Activity feed: teammates see "<who> replied to a Sponsorship thread"
        // without the body. Private threads stay inside the team.
        FeedEvent::create([
            'user_id'      => $sender->id,
            'type'         => 'inbox.reply',
            'subject_id'   => $thread->id,
            'subject_type' => InboxThread::class,
            'data'         => [
                'channel'      => $thread->channel,
                'category'     => $thread->category,
                'workspace_id' => $thread->workspace_id,
                'sender_name'  => $thread->sender_name,
                'subject'      => $thread->subject,
                'via'          => $res['via'],
                'by_ai'        => $byAi,
            ],
            'occurred_at'  => now(),
            'visibility'   => $thread->is_private ? 'registered' : 'public',
        ]);

        return $res;
    }
}
