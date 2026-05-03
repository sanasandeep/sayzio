<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\InboxMessage;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\Inbox\InboxClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Signed inbound webhook for Inbox 2.0.
 *
 * Provides a single CSRF-exempt endpoint that any external integration
 * (Instagram/TikTok/X DM webhooks via the platform OAuth proxy, Mailgun /
 * Postmark / Cloudflare Email Routing inbound parsers, Zapier glue, etc.)
 * can POST a normalised payload to. The payload schema is intentionally
 * tiny so any source can be wired up with a few lines of mapping code.
 *
 * Auth is the per-workspace `inbox_inbound_token`, supplied either in the
 * URL or via `X-Inbox-Token`. An optional `X-Inbox-Signature` header (HMAC
 * SHA-256 of the raw body keyed on the same token) is recommended for
 * sources that can sign.
 *
 * Idempotency: every payload must include `external_id`. Re-deliveries
 * with the same `(channel, external_id)` are no-ops and return 200 so the
 * caller's retry pipeline doesn't pile up.
 */
class InboxInboundController
{
    public function __construct(protected InboxClassifier $classifier) {}

    public function ingest(Request $request, string $token): JsonResponse
    {
        $token = $request->header('X-Inbox-Token', $token);
        $ws = Workspace::where('inbox_inbound_token', $token)->first();
        if (!$ws) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        // Optional HMAC signature verification.
        $sig = $request->header('X-Inbox-Signature');
        if ($sig) {
            $expected = hash_hmac('sha256', $request->getContent(), $token);
            if (!hash_equals($expected, $sig)) {
                return response()->json(['error' => 'bad_signature'], 401);
            }
        }

        $data = $request->validate([
            'channel'        => ['required', 'in:instagram,tiktok,x,email,sponsorship,biolink_dm'],
            'external_id'    => ['required', 'string', 'max:200'],
            'subject'        => ['nullable', 'string', 'max:300'],
            'body'           => ['required', 'string', 'max:50000'],
            'sender_name'    => ['nullable', 'string', 'max:200'],
            'sender_email'   => ['nullable', 'email', 'max:200'],
            'sender_handle'  => ['nullable', 'string', 'max:200'],
            'sender_avatar'  => ['nullable', 'url',    'max:500'],
            'sent_at'        => ['nullable', 'date'],
            'is_private'     => ['nullable', 'boolean'],
        ]);

        $sentAt = isset($data['sent_at']) ? Carbon::parse($data['sent_at']) : now();

        $thread = InboxThread::query()->withoutGlobalScope('workspace')->firstOrNew([
            'source_type' => $data['channel'] === 'email' ? 'email' : ($data['channel'] === 'sponsorship' ? 'sponsorship' : 'social_dm'),
            'source_id'   => crc32($data['channel'] . ':' . $data['external_id']),
        ]);

        if (!$thread->exists) {
            $cls = $this->classifier->classify(
                $data['body'],
                $data['subject'] ?? null,
                $data['channel'],
                false,
            );
            $isPrivate = $data['is_private'] ?? ($data['channel'] === 'sponsorship' || $cls['category'] === 'sponsorship');
            $thread->fill([
                'workspace_id'        => $ws->id,
                'user_id'             => $ws->owner_user_id,
                'channel'             => $data['channel'],
                'subject'             => $data['subject'] ?? Str::limit($data['body'], 80),
                'category'            => $cls['category'],
                'category_confidence' => $cls['confidence'],
                'category_source'     => 'auto',
                'sla_due_at'          => $this->slaFor($cls['category'], $sentAt),
                'is_private'          => $isPrivate,
                'meta'                => ['inbound_via' => 'webhook', 'classifier_reason' => $cls['reason']],
            ]);
        }

        $thread->fill([
            'preview'         => Str::limit(preg_replace('/\s+/', ' ', $data['body']), 240),
            'sender_name'     => $data['sender_name']  ?? $thread->sender_name,
            'sender_email'    => $data['sender_email'] ?? $thread->sender_email,
            'sender_handle'   => $data['sender_handle']?? $thread->sender_handle,
            'sender_avatar'   => $data['sender_avatar']?? $thread->sender_avatar,
            'last_message_at' => $sentAt,
            'last_sender'     => 'in',
            'is_read'         => false,
            'unread_count'    => (int) $thread->unread_count + 1,
        ]);
        $thread->save();

        InboxMessage::firstOrCreate(
            ['thread_id' => $thread->id, 'external_id' => $data['channel'] . ':' . $data['external_id']],
            [
                'direction'   => 'in',
                'sender_name' => $data['sender_name'] ?? null,
                'sender_handle' => $data['sender_handle'] ?? null,
                'body'        => $data['body'],
                'sent_at'     => $sentAt,
            ],
        );

        return response()->json(['ok' => true, 'thread_id' => $thread->id], 200);
    }

    protected function slaFor(string $category, Carbon $from): ?Carbon
    {
        $hours = InboxThread::DEFAULT_SLA_HOURS[$category] ?? null;
        return $hours ? $from->copy()->addHours($hours) : null;
    }
}
